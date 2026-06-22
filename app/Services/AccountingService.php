<?php

namespace App\Services;

use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\MaterialPurchase;
use App\Models\SpkKontraktorPayment;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public const CONTRACTOR_BILL = 'contractor_bill';
    public const CONTRACTOR_PAYMENT = 'contractor_payment';
    public const MATERIAL_CASH_PURCHASE = 'material_cash_purchase';
    public const SUPPLIER_BILL = 'supplier_bill';
    public const SUPPLIER_PAYMENT = 'supplier_payment';
    public const CASH_TRANSACTION = 'cash_transaction';

    public function recordFinancialTransaction(TransaksiKeuangan $transaction): ?Journal
    {
        $transaction->loadMissing(['tipePost.debitAccount', 'tipePost.creditAccount', 'masterBank']);
        $post = $transaction->tipePost;

        if (! $post?->debitAccount || ! $post?->creditAccount || (float) $transaction->nominal <= 0) {
            throw ValidationException::withMessages([
                'accounting' => 'Tipe transaksi belum memiliki pasangan akun debit dan kredit.',
            ]);
        }

        $perumahanId = $transaction->perumahan_id ?: $transaction->masterBank?->perumahan_id;
        $journal = $this->postJournal(
            source: $transaction,
            type: self::CASH_TRANSACTION,
            tanggal: $transaction->tanggal?->toDateString() ?? now()->toDateString(),
            perumahanId: $perumahanId,
            detailRumahId: null,
            keterangan: $transaction->keterangan,
            lines: [
                ['account' => $post->debitAccount->kode_akun, 'debit' => $transaction->nominal, 'kredit' => 0],
                ['account' => $post->creditAccount->kode_akun, 'debit' => 0, 'kredit' => $transaction->nominal],
            ],
        );

        $transaction->forceFill([
            'perumahan_id' => $perumahanId,
            'journal_id' => $journal->id,
        ])->save();

        return $journal;
    }

    public function recordContractorBill(SpkKontraktorPayment $payment): ?Journal
    {
        $payment->loadMissing('spkKontraktor');
        $spk = $payment->spkKontraktor;

        if (! $spk || (float) $payment->nominal <= 0) {
            return null;
        }

        return $this->postJournal(
            source: $payment,
            type: self::CONTRACTOR_BILL,
            tanggal: $payment->tanggal_pembayaran?->toDateString() ?? now()->toDateString(),
            perumahanId: $spk->perumahan_id,
            detailRumahId: $spk->detail_rumah_id,
            keterangan: "Tagihan termin {$payment->termin_ke} SPK {$spk->nomor_spk}",
            lines: [
                ['account' => ChartOfAccount::HPP_KONSTRUKSI, 'debit' => $payment->nominal, 'kredit' => 0],
                ['account' => ChartOfAccount::HUTANG_KONTRAKTOR, 'debit' => 0, 'kredit' => $payment->nominal],
            ],
        );
    }

    public function recordContractorPayment(SpkKontraktorPayment $payment): ?Journal
    {
        $payment->loadMissing('spkKontraktor.perumahan');
        $spk = $payment->spkKontraktor;

        if (! $spk || (float) $payment->nominal <= 0) {
            return null;
        }

        $journal = $this->postJournal(
            source: $payment,
            type: self::CONTRACTOR_PAYMENT,
            tanggal: now()->toDateString(),
            perumahanId: $spk->perumahan_id,
            detailRumahId: $spk->detail_rumah_id,
            keterangan: "Pembayaran termin {$payment->termin_ke} SPK {$spk->nomor_spk}",
            lines: [
                ['account' => ChartOfAccount::HUTANG_KONTRAKTOR, 'debit' => $payment->nominal, 'kredit' => 0],
                ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => $payment->nominal],
            ],
        );

        $this->recordCashflow(
            tipePostName: 'Pembayaran Hutang Kontraktor',
            tanggal: now()->toDateString(),
            nominal: (float) $payment->nominal,
            keterangan: "Pembayaran termin {$payment->termin_ke} SPK {$spk->nomor_spk}",
            cabangId: $spk->perumahan?->cabang_id,
        );

        return $journal;
    }

    public function recordMaterialCashPurchase(MaterialPurchase $purchase): ?Journal
    {
        if ((float) $purchase->total_nominal <= 0) {
            return null;
        }

        $journal = $this->postJournal(
            source: $purchase,
            type: self::MATERIAL_CASH_PURCHASE,
            tanggal: now()->toDateString(),
            perumahanId: $purchase->perumahan_id,
            detailRumahId: $purchase->detail_rumah_id,
            keterangan: "Pembelian material tunai {$purchase->kode_pembelian}",
            lines: [
                ['account' => ChartOfAccount::PERSEDIAAN_MATERIAL, 'debit' => $purchase->total_nominal, 'kredit' => 0],
                ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => $purchase->total_nominal],
            ],
        );

        $this->recordCashflow(
            tipePostName: 'Pembelian Material',
            tanggal: now()->toDateString(),
            nominal: (float) $purchase->total_nominal,
            keterangan: "Pembelian material tunai {$purchase->kode_pembelian}",
            cabangId: $purchase->perumahan?->cabang_id,
            masterBankId: $purchase->payment_master_bank_id,
        );

        return $journal;
    }

    public function recordSupplierBill(MaterialPurchase $purchase): ?Journal
    {
        if ((float) $purchase->total_nominal <= 0) {
            return null;
        }

        return $this->postJournal(
            source: $purchase,
            type: self::SUPPLIER_BILL,
            tanggal: $purchase->tanggal?->toDateString() ?? now()->toDateString(),
            perumahanId: $purchase->perumahan_id,
            detailRumahId: $purchase->detail_rumah_id,
            keterangan: "Tagihan supplier pembelian {$purchase->kode_pembelian}",
            lines: [
                ['account' => ChartOfAccount::PERSEDIAAN_MATERIAL, 'debit' => $purchase->total_nominal, 'kredit' => 0],
                ['account' => ChartOfAccount::HUTANG_SUPPLIER, 'debit' => 0, 'kredit' => $purchase->total_nominal],
            ],
        );
    }

    public function recordSupplierPayment(MaterialPurchase $purchase): ?Journal
    {
        if ((float) $purchase->total_nominal <= 0) {
            return null;
        }

        $journal = $this->postJournal(
            source: $purchase,
            type: self::SUPPLIER_PAYMENT,
            tanggal: now()->toDateString(),
            perumahanId: $purchase->perumahan_id,
            detailRumahId: $purchase->detail_rumah_id,
            keterangan: "Pembayaran hutang supplier {$purchase->kode_pembelian}",
            lines: [
                ['account' => ChartOfAccount::HUTANG_SUPPLIER, 'debit' => $purchase->total_nominal, 'kredit' => 0],
                ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => $purchase->total_nominal],
            ],
        );

        $this->recordCashflow(
            tipePostName: 'Pembayaran Hutang Supplier',
            tanggal: now()->toDateString(),
            nominal: (float) $purchase->total_nominal,
            keterangan: "Pembayaran hutang supplier {$purchase->kode_pembelian}",
            cabangId: $purchase->perumahan?->cabang_id,
            masterBankId: $purchase->payment_master_bank_id,
        );

        return $journal;
    }

    public function postJournal(Model $source, string $type, string $tanggal, ?int $perumahanId, ?int $detailRumahId, string $keterangan, array $lines): Journal
    {
        return DB::transaction(function () use ($source, $type, $tanggal, $perumahanId, $detailRumahId, $keterangan, $lines) {
            $existing = Journal::query()
                ->where('source_type', $source::class)
                ->where('source_id', $source->getKey())
                ->where('type', $type)
                ->first();

            if ($existing) {
                return $existing;
            }

            $resolvedLines = collect($lines)->map(function (array $line) {
                $account = ChartOfAccount::query()
                    ->where('kode_akun', $line['account'])
                    ->where('status', 'aktif')
                    ->first();

                if (! $account) {
                    throw ValidationException::withMessages(['accounting' => "Akun {$line['account']} belum tersedia atau tidak aktif."]);
                }

                return [
                    'chart_of_account_id' => $account->id,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'kredit' => (float) ($line['kredit'] ?? 0),
                    'keterangan' => $line['keterangan'] ?? null,
                ];
            });

            $totalDebit = round($resolvedLines->sum('debit'), 2);
            $totalKredit = round($resolvedLines->sum('kredit'), 2);

            if ($totalDebit <= 0 || $totalDebit !== $totalKredit) {
                throw ValidationException::withMessages(['accounting' => 'Jurnal tidak balance. Total debit harus sama dengan total kredit.']);
            }

            $journal = Journal::query()->create([
                'nomor_jurnal' => $this->nextJournalNumber($type),
                'tanggal' => $tanggal,
                'type' => $type,
                'source_type' => $source::class,
                'source_id' => $source->getKey(),
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'keterangan' => $keterangan,
                'created_by' => auth()->id(),
            ]);

            $journal->details()->createMany($resolvedLines->all());

            return $journal;
        });
    }

    protected function recordCashflow(
        string $tipePostName,
        string $tanggal,
        float $nominal,
        string $keterangan,
        ?int $cabangId = null,
        ?int $masterBankId = null,
    ): void
    {
        $tipePost = TipePost::query()->where('nama_post', $tipePostName)->first();
        $cabangId = $cabangId ?: CabangPerusahaan::query()->value('id');
        $userId = auth()->id();

        if (! $tipePost || ! $cabangId || ! $userId) {
            return;
        }

        TransaksiKeuangan::query()->firstOrCreate(
            [
                'tipe_post_id' => $tipePost->id,
                'tanggal' => $tanggal,
                'nominal' => $nominal,
                'keterangan' => $keterangan,
            ],
            [
                'cabang_id' => $cabangId,
                'master_bank_id' => $masterBankId,
                'user_id' => $userId,
            ],
        );
    }

    protected function nextJournalNumber(string $type): string
    {
        return 'JRN-'.str($type)->upper()->replace('_', '-').'-'.now()->format('YmdHis').'-'.str_pad((string) (Journal::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
