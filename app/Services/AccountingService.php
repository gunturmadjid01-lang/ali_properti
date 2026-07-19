<?php

namespace App\Services;

use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\EmployeeAdvance;
use App\Models\HppRealisasi;
use App\Models\Journal;
use App\Models\KelompokHpp;
use App\Models\MaterialPurchase;
use App\Models\PaymentSchedule;
use App\Models\PayrollBatch;
use App\Models\PettyCashDeposit;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFunding;
use App\Models\SpkKontraktorPayment;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public const CUSTOMER_INVOICE = 'customer_invoice';

    public const CUSTOMER_RECEIPT = 'customer_receipt';

    public const CONTRACTOR_BILL = 'contractor_bill';

    public const CONTRACTOR_PAYMENT = 'contractor_payment';

    public const MATERIAL_CASH_PURCHASE = 'material_cash_purchase';

    public const SUPPLIER_BILL = 'supplier_bill';

    public const SUPPLIER_PAYMENT = 'supplier_payment';

    public const CASH_TRANSACTION = 'cash_transaction';

    public const PETTY_CASH_FUNDING = 'petty_cash_funding';

    public const PETTY_CASH_EXPENSE = 'petty_cash_expense';

    public const PETTY_CASH_DEPOSIT = 'petty_cash_deposit';

    public function recordPettyCashDeposit(PettyCashDeposit $deposit): Journal
    {
        return $this->postJournal($deposit, self::PETTY_CASH_DEPOSIT, $deposit->deposit_date->toDateString(), $deposit->masterBank?->perumahan_id, null, "Penyetoran Kas Kecil {$deposit->number} ke Kas Perusahaan", [
            ['account' => ChartOfAccount::KAS_BANK, 'debit' => $deposit->amount, 'kredit' => 0],
            ['account' => ChartOfAccount::KAS_KECIL, 'debit' => 0, 'kredit' => $deposit->amount],
        ], $deposit->master_bank_id);
    }

    public const EMPLOYEE_PAYROLL = 'employee_payroll';

    public const EMPLOYEE_ADVANCE = 'employee_advance';

    public function recordEmployeePayroll(PayrollBatch $batch): Journal
    {
        $advance = (float) $batch->items()->sum('advance_deduction');
        $other = max(0, (float) $batch->total_deductions - $advance);
        $lines = [['account' => ChartOfAccount::BEBAN_GAJI, 'debit' => $batch->total_gross, 'kredit' => 0], ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => $batch->total_net]];
        if ($advance > 0) {
            $lines[] = ['account' => '1-1200', 'debit' => 0, 'kredit' => $advance];
        }
        if ($other > 0) {
            $lines[] = ['account' => '2-2300', 'debit' => 0, 'kredit' => $other];
        }

        return $this->postJournal(
            source: $batch,
            type: self::EMPLOYEE_PAYROLL,
            tanggal: $batch->payment_date->toDateString(),
            perumahanId: $batch->perumahan_id,
            detailRumahId: null,
            keterangan: "Penggajian pegawai {$batch->batch_number} periode {$batch->period}",
            lines: $lines,
            masterBankId: $batch->master_bank_id,
        );
    }

    public function recordEmployeeAdvance(EmployeeAdvance $advance): Journal
    {
        return $this->postJournal($advance, self::EMPLOYEE_ADVANCE, $advance->advance_date->toDateString(), $advance->perumahan_id, null, "Panjar pegawai {$advance->advance_number}", [['account' => '1-1200', 'debit' => $advance->amount, 'kredit' => 0], ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => $advance->amount]], $advance->master_bank_id);
    }

    public function recordCustomerInvoice(PaymentSchedule $invoice): Journal
    {
        $invoice->loadMissing(['salesTransaction', 'housingReservation.unit']);

        return $this->postJournal($invoice, self::CUSTOMER_INVOICE, $invoice->issued_at?->toDateString() ?? now()->toDateString(), $invoice->salesTransaction?->perumahan_id ?? $invoice->housingReservation?->unit?->perumahan_id, $invoice->salesTransaction?->detail_rumah_id ?? $invoice->housingReservation?->detail_rumah_id, "Tagihan customer {$invoice->invoice_no}", [
            ['account' => ChartOfAccount::PIUTANG_CUSTOMER, 'debit' => $invoice->amount, 'kredit' => 0],
            ['account' => ChartOfAccount::UANG_MUKA_CUSTOMER, 'debit' => 0, 'kredit' => $invoice->amount],
        ]);
    }

    public function recordPettyCashFunding(PettyCashFunding $funding): Journal
    {
        return $this->postJournal(
            source: $funding,
            type: self::PETTY_CASH_FUNDING,
            tanggal: $funding->approved_at?->toDateString() ?? now()->toDateString(),
            perumahanId: null,
            detailRumahId: null,
            keterangan: "Pengisian kas kecil {$funding->number}",
            lines: [
                ['account' => ChartOfAccount::KAS_KECIL, 'debit' => $funding->amount, 'kredit' => 0],
                ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => $funding->amount],
            ],
        );
    }

    public function recordPettyCashExpense(PettyCashExpense $expense): Journal
    {
        $debitAccount = $expense->cost_type === 'operational'
            ? $this->operationalExpenseAccount($expense->category)
            : ChartOfAccount::PERSEDIAAN_PROYEK;

        return $this->postJournal(
            source: $expense,
            type: self::PETTY_CASH_EXPENSE,
            tanggal: $expense->expense_date->toDateString(),
            perumahanId: $expense->perumahan_id,
            detailRumahId: $expense->detail_rumah_id,
            keterangan: "Pengeluaran kas kecil {$expense->number} - {$expense->description}",
            lines: [
                ['account' => $debitAccount, 'debit' => $expense->amount, 'kredit' => 0],
                ['account' => ChartOfAccount::KAS_KECIL, 'debit' => 0, 'kredit' => $expense->amount],
            ],
        );
    }

    private function operationalExpenseAccount(string $category): string
    {
        return match ($category) {
            'utilitas' => '6-4000',
            'lainnya' => '6-9000',
            default => ChartOfAccount::BEBAN_OPERASIONAL,
        };
    }

    public function recordFinancialTransaction(TransaksiKeuangan $transaction): ?Journal
    {
        $transaction->loadMissing(['tipePost.debitAccount', 'tipePost.creditAccount', 'masterBank']);
        $post = $transaction->tipePost;

        if (! $post?->debitAccount || ! $post?->creditAccount || (float) $transaction->nominal <= 0) {
            throw ValidationException::withMessages([
                'accounting' => 'Tipe transaksi belum memiliki pasangan akun debit dan kredit.',
            ]);
        }

        $perumahanId = $transaction->perumahan_id;
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
            masterBankId: $transaction->master_bank_id,
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

        $tanggal = $payment->requested_at?->toDateString() ?? now()->toDateString();
        $journal = $this->postJournal(
            source: $payment,
            type: self::CONTRACTOR_BILL,
            tanggal: $tanggal,
            perumahanId: $spk->perumahan_id,
            detailRumahId: $spk->detail_rumah_id,
            keterangan: "Tagihan termin {$payment->termin_ke} SPK {$spk->nomor_spk}",
            lines: [
                ['account' => ChartOfAccount::PERSEDIAAN_PROYEK, 'debit' => $payment->nominal, 'kredit' => 0],
                ['account' => ChartOfAccount::HUTANG_KONTRAKTOR, 'debit' => 0, 'kredit' => $payment->nominal],
            ],
        );

        $this->recordContractorHpp($payment, $tanggal);

        return $journal;
    }

    public function recordContractorPayment(SpkKontraktorPayment $payment): ?Journal
    {
        $payment->loadMissing('spkKontraktor.perumahan');
        $spk = $payment->spkKontraktor;

        if (! $spk || (float) $payment->nominal <= 0) {
            return null;
        }

        $tanggal = $payment->tanggal_pembayaran?->toDateString() ?? now()->toDateString();
        $journal = $this->postJournal(
            source: $payment,
            type: self::CONTRACTOR_PAYMENT,
            tanggal: $tanggal,
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
            tanggal: $tanggal,
            nominal: (float) $payment->nominal,
            keterangan: "Pembayaran termin {$payment->termin_ke} SPK {$spk->nomor_spk}",
            cabangId: $spk->perumahan?->cabang_id,
        );

        return $journal;
    }

    protected function recordContractorHpp(SpkKontraktorPayment $payment, string $tanggal): void
    {
        $payment->loadMissing(['spkKontraktor.detailRumah', 'spkKontraktor.perumahan']);
        $spk = $payment->spkKontraktor;

        if (! $spk) {
            return;
        }

        $kelompokName = match ($spk->jenis_pekerjaan) {
            'jalan' => 'Jalan Kawasan',
            'pembukaan_lahan' => 'Biaya Pematangan Lahan',
            default => 'Biaya Subkontraktor',
        };
        $kelompokId = KelompokHpp::query()->finalized()->where('nama_hpp', $kelompokName)->value('id');
        $target = $spk->detailRumah ?: $spk->perumahan;

        if (! $target) {
            return;
        }

        HppRealisasi::query()->firstOrCreate(
            [
                'sumber_type' => SpkKontraktorPayment::class,
                'sumber_id' => $payment->id,
            ],
            [
                'target_type' => $target::class,
                'target_id' => $target->getKey(),
                'perumahan_id' => $spk->perumahan_id,
                'detail_rumah_id' => $spk->detail_rumah_id,
                'tahapan_pembangunan_id' => null,
                'kelompok_hpp_id' => $kelompokId,
                'tanggal' => $tanggal,
                'nominal' => $payment->nominal,
                'keterangan' => "Realisasi HPP termin {$payment->termin_ke} SPK {$spk->nomor_spk}",
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ],
        );
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
            masterBankId: $purchase->payment_master_bank_id,
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
            masterBankId: $purchase->payment_master_bank_id,
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

    public function postJournal(Model $source, string $type, string $tanggal, ?int $perumahanId, ?int $detailRumahId, string $keterangan, array $lines, ?int $masterBankId = null): Journal
    {
        return DB::transaction(function () use ($source, $type, $tanggal, $perumahanId, $detailRumahId, $keterangan, $lines, $masterBankId) {
            $existing = Journal::query()
                ->where('source_type', $source::class)
                ->where('source_id', $source->getKey())
                ->where('type', $type)
                ->first();

            if ($existing) {
                if ($masterBankId && ! $existing->master_bank_id) {
                    $existing->update(['master_bank_id' => $masterBankId]);
                }

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
                'master_bank_id' => $masterBankId,
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
    ): void {
        $tipePost = TipePost::query()->finalized()->where('nama_post', $tipePostName)->first();
        $cabangId = $cabangId ?: CabangPerusahaan::query()->finalized()->value('id');
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
