<?php

namespace App\Services;

use App\Models\SalesProcessStep;
use App\Models\SalesTransaction;
use App\Models\SalesWorkflowHistory;
use App\Models\UnitOwnership;
use App\Support\SalesProcessDefinitions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesProcessService
{
    public function initialize(SalesTransaction $transaction): void
    {
        $specific = match ($transaction->payment_method) {
            'cash_bertahap' => [['contract_review', 'Pemeriksaan Kontrak Cash Bertahap', 'contract'], ['contract_signing', 'Penandatanganan Kontrak', 'contract'], ['installment_monitoring', 'Monitoring Angsuran dan Pelunasan', 'finance']],
            'kpr_developer' => [['affordability_analysis', 'Analisis Kemampuan Bayar', 'financing'], ['document_validation', 'Validasi Dokumen', 'document'], ['internal_approval', 'Persetujuan Pembiayaan Developer', 'approval'], ['contract_signing', 'Penandatanganan Kontrak KPR Developer', 'contract'], ['installment_monitoring', 'Monitoring Angsuran dan Pelunasan', 'finance']],
            'kpr_bank' => [['document_collection', 'Pengumpulan dan Validasi Dokumen Bank', 'document'], ['slik', 'Proses SLIK', 'bank'], ['appraisal', 'Appraisal dan Survei Bank', 'bank'], ['bank_decision', 'Keputusan Kredit Bank', 'bank'], ['sp3k', 'Penerbitan SP3K', 'bank'], ['contract_preparation', 'Persiapan Akad', 'contract'], ['contract_signing', 'Pelaksanaan Akad Kredit', 'contract'], ['bank_disbursement', 'Pencairan Dana Bank', 'finance']],
            default => [['cash_settlement', 'Pelunasan Transaksi Cash', 'finance']],
        };
        $unitReady = (float) ($transaction->housingUnit?->progress_terakhir ?? 0) >= 100;
        $construction = $unitReady ? [] : [['construction_preparation', 'Persiapan Pembangunan Unit', 'construction'], ['construction', 'Pelaksanaan dan Monitoring Pembangunan', 'construction']];
        $common = [...$construction, ['quality_inspection', 'Inspeksi Mutu dan Daftar Perbaikan', 'quality'], ['internal_handover', 'Serah Terima Internal dari Proyek', 'handover'], ['customer_handover', 'BAST dan Penyerahan Kunci ke Customer', 'handover'], ['move_in', 'Konfirmasi Customer Mulai Menempati Unit', 'occupancy'], ['warranty', 'Masa Pemeliharaan dan After Sales', 'after_sales'], ['completed', 'Transaksi dan Layanan Penjualan Selesai', 'completion']];
        $workflow = [...$specific, ...$common];
        $workflowCodes = collect($workflow)->pluck(0);
        foreach ($workflow as $index => [$code,$label,$category]) {
            $dependencies = collect(SalesProcessDefinitions::dependencies($code, $transaction->payment_method))->filter(fn ($dependency) => $workflowCodes->contains($dependency))->values()->all();
            $step = SalesProcessStep::firstOrCreate(['sales_transaction_id' => $transaction->id, 'code' => $code], ['sequence' => $index + 1, 'label' => $label, 'category' => $category, 'description' => $this->description($code), 'status' => empty($dependencies) ? 'available' : 'waiting', 'metadata' => ['data' => [], 'dependencies' => $dependencies], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
            $this->syncContext($step);
            foreach (SalesProcessDefinitions::get($code)['checklist'] as $item) {
                $step->checklistItems()->firstOrCreate(['item_key' => $item['key']], ['label' => $item['label'], 'is_required' => $item['required']]);
            }
        }
    }

    public function syncContext(SalesProcessStep $step): SalesProcessStep
    {
        $step->loadMissing(['salesTransaction.spr', 'salesTransaction.customer', 'salesTransaction.housingUnit', 'salesTransaction.paymentSchedules', 'salesTransaction.customerReceipts']);
        $transaction = $step->salesTransaction;
        $spr = $transaction?->spr;
        $customer = $transaction?->customer;
        $unit = $transaction?->housingUnit;
        $final = (float) ($spr?->nilai_pengajuan_akhir ?: $transaction?->sale_price_snapshot ?: 0);
        $financing = (float) ($spr?->nilai_pengajuan_kpr ?: max(0, $final - (float) $spr?->booking_fee - (float) $spr?->uang_muka));
        $master = $spr?->payment_configuration_snapshot['master'] ?? $transaction?->payment_snapshot['master'] ?? [];
        $previous = SalesProcessStep::where('sales_transaction_id', $step->sales_transaction_id)->where('sequence', '<', $step->sequence)->where('status', 'completed')->orderByDesc('sequence')->first();
        $prior = $previous?->metadata['data'] ?? [];
        $schedules = $transaction?->paymentSchedules ?? collect();
        $receipts = $transaction?->customerReceipts ?? collect();
        $totalBill = (float) $schedules->sum('amount');
        $totalPaid = (float) $schedules->sum('paid_amount');
        $customerIncome = (float) ($customer?->penghasilan ?? 0);
        $spouseIncome = (float) ($customer?->penghasilan_pasangan ?? 0);
        $monthlyExpense = (float) ($customer?->pengeluaran_bulanan ?? 0)
            + (float) ($customer?->pengeluaran_bulanan_pasangan ?? 0);
        $existingInstallment = (float) collect($customer?->daftar_cicilan ?? [])->sum('angsuran_bulanan');
        $totalIncome = $customerIncome + $spouseIncome;
        $common = ['customer_name' => $customer?->nama, 'spouse_name' => $customer?->nama_lengkap_pasangan, 'recipient_name' => $customer?->nama, 'occupant_name' => $customer?->nama, 'occupant_phone' => $customer?->telepon, 'final_price' => $final, 'final_contract_value' => $final, 'booking_fee' => (float) $spr?->booking_fee, 'down_payment' => (float) $spr?->uang_muka, 'financed_amount' => $financing, 'requested_financing' => $financing, 'installment_count' => $spr?->jumlah_termin, 'first_due_date' => $spr?->tanggal_jatuh_tempo_angsuran?->format('Y-m-d')];
        $specific = match ($step->code) {
            'contract_review' => ['grace_days' => $master['grace_period_days'] ?? 0, 'penalty_terms' => $this->penaltySummary($master), 'early_settlement_terms' => $this->advancedTerm($master, 'early_settlement', 'early_settlement_terms', 'Pelunasan dipercepat'), 'cancellation_terms' => $this->advancedTerm($master, 'cancellation', 'cancellation_terms', 'Pembatalan kontrak')],
            'affordability_analysis' => ['customer_income' => $customerIncome, 'spouse_income' => $spouseIncome, 'monthly_expense' => $monthlyExpense, 'existing_installment' => $existingInstallment, 'net_disposable_income' => max(0, $totalIncome - $monthlyExpense - $existingInstallment), 'dsr_percent' => $totalIncome > 0 ? round(($existingInstallment / $totalIncome) * 100, 2) : 0, 'requested_financing' => $financing, 'recommended_tenor' => $spr?->kpr_tenor_bulan, 'recommended_installment' => $spr?->nominal_termin],
            'internal_approval' => ['approved_limit' => $financing, 'approved_tenor' => $spr?->kpr_tenor_bulan, 'required_dp' => (float) $spr?->uang_muka],
            'bank_decision' => ['approved_limit' => $financing, 'approved_tenor' => $spr?->kpr_tenor_bulan, 'interest_rate' => $spr?->kpr_bunga_tahunan, 'required_dp' => (float) $spr?->uang_muka],
            'sp3k' => ['approved_limit' => $prior['approved_limit'] ?? $financing, 'tenor_months' => $prior['approved_tenor'] ?? $spr?->kpr_tenor_bulan, 'interest_rate' => $prior['interest_rate'] ?? $spr?->kpr_bunga_tahunan, 'installment' => $prior['installment'] ?? null],
            'contract_preparation' => ['dp_paid' => (float) $spr?->uang_muka, 'shortfall_paid' => 0],
            'contract_signing' => ['customer_name' => $customer?->nama, 'spouse_name' => $customer?->nama_lengkap_pasangan, 'final_contract_value' => $step->salesTransaction?->payment_method === 'kpr_bank' ? $financing : $final, 'notary_name' => $prior['notary_name'] ?? null, 'location' => $prior['contract_location'] ?? null],
            'installment_monitoring' => ['total_bill' => $totalBill, 'total_paid' => $totalPaid, 'outstanding' => max(0, $totalBill - $totalPaid), 'overdue_amount' => (float) $schedules->where('due_date', '<', today())->whereNotIn('status', ['paid', 'cancelled'])->sum(fn ($row) => max(0, (float) $row->amount - (float) $row->paid_amount)), 'payment_condition' => $totalBill > 0 && $totalPaid >= $totalBill ? 'paid_off' : ($totalPaid > 0 ? 'partial' : 'current')],
            'construction' => ['progress_percent' => (float) ($unit?->progress_terakhir ?? 0)],
            'customer_handover' => ['recipient_name' => $customer?->nama],
            'move_in' => ['occupant_name' => $customer?->nama, 'occupant_phone' => $customer?->telepon, 'occupant_relation' => 'Pemilik'],
            'warranty' => ['warranty_start' => $prior['warranty_start'] ?? null, 'warranty_end' => $prior['warranty_end'] ?? null],
            'completed' => ['completion_date' => today()->format('Y-m-d'), 'financial_status' => $totalBill > 0 && $totalPaid >= $totalBill ? 'paid_off' : null],
            default => [],
        };
        $allowed = collect(SalesProcessDefinitions::get($step->code)['fields'])->pluck('name');
        $automatic = collect([...$common, ...$specific])->only($allowed)->filter(fn ($value) => $value !== null && $value !== '')->all();
        $metadata = $step->metadata ?? [];
        $current = $metadata['data'] ?? [];
        $merged = $current;
        $oldSources = $metadata['sources'] ?? [];
        $alwaysLinked = $step->code === 'contract_review' ? ['final_price', 'booking_fee', 'down_payment', 'financed_amount', 'installment_count', 'first_due_date', 'grace_days', 'penalty_terms', 'early_settlement_terms', 'cancellation_terms'] : [];
        foreach ($automatic as $key => $value) {
            if (in_array($key, $alwaysLinked, true) || array_key_exists($key, $oldSources) || ! array_key_exists($key, $merged) || $merged[$key] === null || $merged[$key] === '') {
                $merged[$key] = $value;
            }
        }
        $metadata['data'] = $merged;
        $metadata['dependencies'] = $metadata['dependencies'] ?? SalesProcessDefinitions::dependencies($step->code, $transaction?->payment_method);
        $metadata['sources'] = array_fill_keys(array_keys($automatic), 'Otomatis dari SPR/transaksi/master metode');
        if ($metadata !== $step->metadata) {
            $step->update(['metadata' => $metadata]);
        }

        return $step->fresh();
    }

    public function approve(SalesProcessStep $step): void
    {
        DB::transaction(function () use ($step) {
            $step = SalesProcessStep::with('salesTransaction.housingUnit')->lockForUpdate()->findOrFail($step->id);
            if ($step->status === 'completed') {
                return;
            }
            if (! $this->dependenciesMet($step)) {
                throw ValidationException::withMessages(['step' => 'Prasyarat tahap belum selesai.']);
            }
            $step->update(['status' => 'completed', 'actual_date' => $step->actual_date ?: today(), 'completed_by' => auth()->id()]);
            SalesProcessStep::where('sales_transaction_id', $step->sales_transaction_id)->where('status', 'waiting')->get()->each(function ($candidate) {
                if ($this->dependenciesMet($candidate)) {
                    $candidate->update(['status' => 'available']);
                }
            });
            SalesWorkflowHistory::firstOrCreate(['sales_transaction_id' => $step->sales_transaction_id, 'process' => 'sales_process_'.$step->code, 'notes' => $step->label.' disetujui final.'], ['to_status' => 'completed', 'user_id' => auth()->id(), 'occurred_at' => now()]);
            $this->finalizeBillingAtBusinessMilestone($step);
            if ($step->code === 'customer_handover') {
                $step->salesTransaction->housingUnit?->update(['status_penjualan' => 'terjual']);
                $this->ownership($step->salesTransaction, 'handover');
            }
            if ($step->code === 'move_in') {
                $step->salesTransaction->housingUnit?->update(['status_penjualan' => 'ditempati']);
                $this->ownership($step->salesTransaction, 'occupied');
            }
            if ($step->code === 'completed') {
                $step->salesTransaction->update(['status' => 'completed']);
            }
            app(HousingReservationService::class)->syncProcessStep($step->fresh());
        });
    }

    private function finalizeBillingAtBusinessMilestone(SalesProcessStep $step): void
    {
        $transaction = $step->salesTransaction;
        if (! $transaction) {
            return;
        }

        $source = match (true) {
            $transaction->payment_method === 'cash_bertahap' && $step->code === 'contract_signing'
                => $transaction->cashInstallmentContract,
            $transaction->payment_method === 'kpr_developer' && $step->code === 'internal_approval'
                => $transaction->developerKprApplication,
            default => null,
        };

        if ($source) {
            if ($transaction->spr) {
                app(CustomerReceivableService::class)->createDownPaymentInvoice($transaction->spr);
            }
            app(CustomerReceivableService::class)->finalizeSchedule($source);
        }
    }

    public function dependenciesMet(SalesProcessStep $step): bool
    {
        $codes = collect($step->metadata['dependencies'] ?? SalesProcessDefinitions::dependencies($step->code, $step->salesTransaction?->payment_method));

        return $codes->isEmpty() || ! SalesProcessStep::where('sales_transaction_id', $step->sales_transaction_id)->whereIn('code', $codes)->where('status', '!=', 'completed')->exists();
    }

    private function ownership(SalesTransaction $transaction, string $status): void
    {
        $customer = $transaction->customer;
        UnitOwnership::updateOrCreate(['detail_rumah_id' => $transaction->detail_rumah_id, 'costumer_id' => $transaction->costumer_id], ['spr_id' => $transaction->spr_id, 'source_type' => 'sales_process', 'source_id' => $transaction->id, 'acquisition_method' => $transaction->payment_method, 'owner_name' => $customer?->nama ?? 'Customer', 'identity_type' => $customer?->jenis_identitas, 'identity_number' => $customer?->no_identitas, 'phone' => $customer?->telepon, 'email' => $customer?->email, 'address' => $customer?->alamat, 'spouse_name' => $customer?->nama_lengkap_pasangan, 'notes' => 'Status hunian: '.$status, 'is_active' => true, 'record_status' => 'locked', 'acquired_at' => today(), 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
    }

    private function penaltySummary(array $master): string
    {
        $method = $master['penalty_method'] ?? 'none';
        $value = (float) ($master['penalty_value'] ?? 0);
        $grace = (int) ($master['grace_period_days'] ?? 0);
        $rule = match ($method) {
            'fixed' => 'Denda tetap Rp '.number_format($value, 0, ',', '.'),'invoice_percentage' => 'Denda '.$value.'% dari nilai tagihan','installment_percentage' => 'Denda '.$value.'% dari nilai angsuran','daily_percentage' => 'Denda '.$value.'% per hari','monthly_percentage' => 'Denda '.$value.'% per bulan',default => 'Tidak dikenakan denda'
        };

        return $rule.'. Denda mulai berlaku setelah masa tenggang '.$grace.' hari dari tanggal jatuh tempo.';
    }

    private function advancedTerm(array $master, string $flag, string $term, string $label): string
    {
        $config = $master['advanced_config'] ?? [];

        return ! ($config[$flag] ?? false) ? $label.' tidak diperbolehkan sesuai master skema.' : ($config[$term] ?? $label.' diperbolehkan dan wajib mengikuti approval perusahaan.');
    }

    private function description(string $code): string
    {
        return match ($code) {
            'customer_handover' => 'Unggah BAST, catat tanggal penyerahan kunci, meter/utilitas, dan kondisi unit.','move_in' => 'Konfirmasi tanggal efektif customer mulai menempati unit; tahap ini mengubah status hunian.','quality_inspection' => 'Pastikan pekerjaan selesai, defect tercatat dan perbaikan wajib telah ditutup.','completed' => 'Tutup transaksi setelah kewajiban pembayaran, dokumen, serah terima, dan masa pemeliharaan selesai.',default => 'Lengkapi tanggal, catatan, dan dokumen bukti sebelum mengajukan approval tahap ini.'
        };
    }
}
