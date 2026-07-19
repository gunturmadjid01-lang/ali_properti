<?php

namespace App\Services;

use App\Models\CashInstallmentContract;
use App\Models\CashInstallmentScheme;
use App\Models\CashSale;
use App\Models\DeveloperKprApplication;
use App\Models\DeveloperKprProduct;
use App\Models\KprSubmission;
use App\Models\PaymentSchedule;
use App\Models\SalesMethodAttempt;
use App\Models\SalesTransaction;
use App\Models\SalesWorkflowHistory;
use App\Models\Spr;
use App\Services\Marketing\MarketingOperationsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesPaymentWorkflowService
{
    public function processApprovedSpr(Spr $spr, ?int $actorId = null): SalesTransaction
    {
        return DB::transaction(function () use ($spr, $actorId): SalesTransaction {
            $spr = Spr::query()->with(['costumer', 'detailRumah.perumahan.cabang', 'creator', 'bankKredit', 'bankCreditProduct'])
                ->lockForUpdate()->findOrFail($spr->id);
            if ($spr->status !== Spr::STATUS_DISETUJUI) {
                throw ValidationException::withMessages(['status' => 'Proses penjualan hanya dapat dibuat dari SPR yang sudah disetujui.']);
            }

            $method = $spr->metode_pembayaran === 'bertahap' ? 'cash_bertahap' : $spr->metode_pembayaran;
            $paymentSnapshot = $this->paymentSnapshot($spr, $method);
            $sourceSpr = $spr->revision_no > 0 ? Spr::query()->where('superseded_by_spr_id', $spr->id)->latest('revision_no')->first() : null;
            $transaction = $sourceSpr?->salesTransaction;
            if ($transaction) {
                $transaction->paymentSubmissions()->where('status', 'in_progress')->update(['status' => 'redirected', 'outcome' => 'payment_method_changed', 'ended_at' => now()]);
                $transaction->processSteps()->where('status', '!=', 'completed')->delete();
                $transaction->update(['spr_id' => $spr->id, 'payment_method' => $method, 'payment_snapshot' => $paymentSnapshot, 'status' => 'active', 'outcome' => null, 'failure_stage' => null, 'failure_category' => null, 'failure_reason' => null, 'closed_at' => null]);
                $sourceSpr->update(['revision_status' => 'superseded']);
                $spr->update(['revision_status' => 'current']);
            }
            $transaction ??= SalesTransaction::query()->firstOrCreate(
                ['spr_id' => $spr->id],
                [
                    'transaction_no' => $this->businessNumber('TRX', $spr->id), 'costumer_id' => $spr->costumer_id,
                    'cabang_perusahaan_id' => $spr->detailRumah?->perumahan?->cabang_id, 'perumahan_id' => $spr->detailRumah?->perumahan_id,
                    'detail_rumah_id' => $spr->detail_rumah_id, 'marketing_user_id' => $spr->created_by, 'payment_method' => $method,
                    'sale_price_snapshot' => $spr->nilai_pengajuan_akhir ?: $spr->harga_jual,
                    'party_snapshot' => $this->partySnapshot($spr), 'payment_snapshot' => $paymentSnapshot,
                    'status' => 'active', 'approved_at' => now(), 'approved_by' => $actorId,
                ]
            );
            if (! $spr->payment_configuration_snapshot) {
                $spr->update(['payment_configuration_snapshot' => $paymentSnapshot]);
            }
            if ($transaction->wasRecentlyCreated) {
                SalesWorkflowHistory::create(['sales_transaction_id' => $transaction->id, 'process' => 'spr_approval', 'to_status' => 'active', 'notes' => 'Transaksi penjualan otomatis dibuat dari SPR yang disetujui.', 'user_id' => $actorId, 'occurred_at' => now()]);
            }
            if (! $transaction->paymentSubmissions()->where(['status' => 'in_progress', 'payment_method' => $method])->exists()) {
                $submissionNo = ((int) $transaction->paymentSubmissions()->max('attempt_no')) + 1;
                SalesMethodAttempt::create(['sales_transaction_id' => $transaction->id, 'attempt_no' => $submissionNo,
                    'payment_method' => $method, 'bank_kredit_id' => $spr->bank_kredit_id, 'bank_credit_product_id' => $spr->bank_credit_product_id,
                    'status' => 'in_progress', 'current_stage' => 'spr_approved', 'started_at' => now(), 'created_by' => $actorId,
                ]);
            }

            match ($method) {
                'cash' => $this->createCashProcess($spr, $transaction, $actorId),
                'cash_bertahap' => $this->createInstallmentProcess($spr, $transaction),
                'kpr_developer' => $this->createDeveloperKprProcess($spr, $transaction),
                'kpr_bank' => $this->createBankKprProcess($spr, $transaction, $actorId),
                default => throw ValidationException::withMessages(['metode_pembayaran' => 'Metode pembayaran SPR tidak didukung workflow penjualan.']),
            };
            app(CustomerReceivableService::class)->createDownPaymentInvoice($spr);
            app(SalesProcessService::class)->initialize($transaction);

            return $transaction->fresh();
        }, 3);
    }

    private function createCashProcess(Spr $spr, SalesTransaction $transaction, ?int $actorId): void
    {
        CashSale::query()->firstOrCreate(['spr_id' => $spr->id], [
            'kode_cash' => $this->businessNumber('CASH', $spr->id), 'costumer_id' => $spr->costumer_id, 'detail_rumah_id' => $spr->detail_rumah_id,
            'handled_by' => $actorId, 'tanggal_transaksi' => now()->toDateString(), 'harga_rumah' => $transaction->sale_price_snapshot,
            'total_tagihan' => $transaction->sale_price_snapshot, 'total_dibayar' => 0, 'sisa_tagihan' => $transaction->sale_price_snapshot,
            'status_pembayaran' => CashSale::STATUS_MENUNGGU_PEMBAYARAN, 'catatan' => 'Dibuat otomatis dari transaksi penjualan; pembayaran aktual dicatat oleh Keuangan.',
        ]);
        // Jadwal resmi dibuat setelah transaksi cash memperoleh approval final.
    }

    private function createInstallmentProcess(Spr $spr, SalesTransaction $transaction): void
    {
        $scheme = CashInstallmentScheme::query()->find($spr->cash_installment_scheme_id);
        $snapshot = $spr->payment_configuration_snapshot['master'] ?? ($scheme ? $scheme->toArray() : $transaction->payment_snapshot);
        $contract = CashInstallmentContract::query()->firstOrCreate(['sales_transaction_id' => $transaction->id], [
            'contract_no' => $this->businessNumber('CB', $transaction->id), 'cash_installment_scheme_id' => $scheme?->id,
            'scheme_snapshot' => $snapshot, 'contract_value' => $this->remainingPrincipal($spr, (float) $transaction->sale_price_snapshot), 'status' => 'draft', 'start_date' => $spr->tanggal_spr,
        ]);
        // Termin dibuat dari snapshot kontrak sesudah approval final kontrak.
    }

    private function createDeveloperKprProcess(Spr $spr, SalesTransaction $transaction): void
    {
        $product = DeveloperKprProduct::query()->find($spr->developer_kpr_product_id);
        $snapshot = $spr->payment_configuration_snapshot['master'] ?? ($product?->toArray() ?: $transaction->payment_snapshot);
        $remainingPrincipal = $this->remainingPrincipal($spr, (float) $transaction->sale_price_snapshot);
        $principal = $this->cents($spr->nilai_pengajuan_kpr > 0 ? min((float) $spr->nilai_pengajuan_kpr, $remainingPrincipal) : $remainingPrincipal);
        $tenor = max(1, (int) ($spr->kpr_tenor_bulan ?: ($snapshot['maximum_tenor_months'] ?? 1)));
        $margin = (float) ($spr->kpr_bunga_tahunan ?: ($snapshot['annual_margin'] ?? 0));
        $total = $principal + (int) round($principal * $margin / 100 * $tenor / 12);
        $installment = $this->splitCents($total, $tenor, 1);
        DeveloperKprApplication::query()->firstOrCreate(['sales_transaction_id' => $transaction->id], [
            'application_no' => $this->businessNumber('KPRD', $transaction->id), 'developer_kpr_product_id' => $product?->id, 'product_snapshot' => $snapshot,
            'financing_amount' => $this->money($principal), 'tenor_months' => $tenor, 'estimated_installment' => $this->money($installment), 'status' => 'draft',
        ]);
    }

    private function createBankKprProcess(Spr $spr, SalesTransaction $transaction, ?int $actorId): void
    {
        $submission = KprSubmission::query()->firstOrCreate(['spr_id' => $spr->id], [
            'kode_kpr' => $this->businessNumber('KPRB', $spr->id), 'bank_kredit_id' => $spr->bank_kredit_id, 'handled_by' => $actorId,
            'tanggal_pengajuan' => now()->toDateString(), 'nilai_pengajuan' => $spr->nilai_pengajuan_kpr, 'status' => 'pengumpulan_dokumen', 'catatan' => 'Otomatis dibuat dari transaksi penjualan.',
        ]);
        if ($submission->wasRecentlyCreated) {
            app(KprProductSnapshotService::class)->applyBestAvailable($submission, $spr->bank_credit_product_id);
            app(MarketingOperationsService::class)->recordKprStage($submission, 'pengumpulan_dokumen', 'Pengajuan otomatis dibuat dari transaksi penjualan.', $actorId);
        }
    }

    private function schedule(SalesTransaction $transaction, mixed $source, int $sequence, string $type, string $description, mixed $dueDate, mixed $amount, ?array $calculation = null): void
    {
        if ($this->cents($amount) <= 0) {
            return;
        }
        PaymentSchedule::query()->firstOrCreate(['sales_transaction_id' => $transaction->id, 'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(), 'sequence' => $sequence], [
            'type' => $type, 'description' => $description, 'due_date' => Carbon::parse($dueDate)->toDateString(), 'amount' => $amount, 'status' => 'belum_dibayar', 'calculation_snapshot' => $calculation,
        ]);
    }

    private function paymentSnapshot(Spr $spr, string $method): array
    {
        return $spr->payment_configuration_snapshot ? [...$spr->payment_configuration_snapshot, 'method' => $method] : ['method' => $method, 'captured_at' => now()->toISOString(), 'booking_fee' => $spr->booking_fee, 'down_payment' => $spr->uang_muka, 'tenor_months' => $spr->kpr_tenor_bulan, 'bank_id' => $spr->bank_kredit_id, 'bank_name' => $spr->bankKredit?->nama_bank, 'bank_product_id' => $spr->bank_credit_product_id, 'bank_product_name' => $spr->bankCreditProduct?->product_name, 'cash_installment_scheme_id' => $spr->cash_installment_scheme_id, 'developer_kpr_product_id' => $spr->developer_kpr_product_id];
    }

    private function partySnapshot(Spr $spr): array
    {
        return ['customer_code' => $spr->costumer?->kode_costumer, 'customer_name' => $spr->costumer?->nama, 'customer_phone' => $spr->costumer?->telepon, 'branch_name' => $spr->detailRumah?->perumahan?->cabang?->nama_cabang, 'housing_code' => $spr->detailRumah?->perumahan?->kode_proyek, 'housing_name' => $spr->detailRumah?->perumahan?->nama_perusahaan, 'unit_code' => trim(($spr->detailRumah?->kode_nlok.' '.$spr->detailRumah?->nomor_rumah)), 'unit_type' => $spr->detailRumah?->tipe_rumah, 'marketing_name' => $spr->creator?->name];
    }

    private function businessNumber(string $prefix, int $id): string
    {
        return $prefix.'/'.now()->format('Y').'/'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) ($amount ?: 0) * 100);
    }

    private function remainingPrincipal(Spr $spr, float $salePrice): float
    {
        $upfront = $spr->booking_fee_includes_dp ? max((float) $spr->booking_fee, (float) $spr->uang_muka) : (float) $spr->booking_fee + (float) $spr->uang_muka;
        return max(0, $salePrice - $upfront);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function splitCents(int $total, int $parts, int $position): int
    {
        $base = intdiv($total,$parts);

        return $base + ($position <= $total % $parts ? 1 : 0);
    }
}
