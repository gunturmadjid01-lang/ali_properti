<?php

namespace App\Services;

use App\Models\SalesProcessStep;
use App\Models\SalesTransaction;
use App\Models\SalesWorkflowHistory;
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
        $construction = [['construction_preparation', 'Persiapan Pembangunan Unit', 'construction'], ['construction', 'Pelaksanaan dan Monitoring Pembangunan', 'construction']];
        $common = [...$construction, ['quality_inspection', 'Inspeksi Mutu dan Daftar Perbaikan', 'quality'], ['internal_handover', 'Serah Terima Internal dari Proyek', 'handover'], ['customer_handover', 'BAST dan Penyerahan Kunci ke Customer', 'handover'], ['move_in', 'Konfirmasi Customer Mulai Menempati Unit', 'occupancy'], ['warranty', 'Masa Pemeliharaan dan After Sales', 'after_sales'], ['completed', 'Transaksi dan Layanan Penjualan Selesai', 'completion']];
        $workflow = [...$specific, ...$common];
        $workflowCodes = collect($workflow)->pluck(0);
        foreach ($workflow as $index => [$code,$label,$category]) {
            $dependencies = collect(SalesProcessDefinitions::dependencies($code, $transaction->payment_method))->filter(fn ($dependency) => $workflowCodes->contains($dependency))->values()->all();
            $step = SalesProcessStep::firstOrCreate(['sales_transaction_id' => $transaction->id, 'code' => $code], ['sequence' => $index + 1, 'label' => $label, 'category' => $category, 'description' => $this->description($code), 'status' => empty($dependencies) ? 'available' : 'waiting', 'metadata' => ['data' => [], 'dependencies' => $dependencies], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
            $metadata = $step->metadata ?? ['data' => []];
            $metadata['dependencies'] = $dependencies;
            $step->update(['sequence' => $index + 1, 'label' => $label, 'category' => $category, 'description' => $this->description($code), 'metadata' => $metadata, 'updated_by' => auth()->id()]);
            $this->syncContext($step);
            foreach (SalesProcessDefinitions::get($code)['checklist'] as $item) {
                $step->checklistItems()->updateOrCreate(['item_key' => $item['key']], ['label' => $item['label'], 'is_required' => $item['required']]);
            }
        }
        $transaction = $transaction->fresh(['housingUnit', 'processSteps']);
        $this->syncUnitState($transaction);
        if ($transaction->housingUnit?->status_pembangunan === 'selesai' || (float) $transaction->housingUnit?->progress_terakhir >= 100) {
            app(UnitOwnershipService::class)->syncSalesTransaction($transaction);
        }
    }

    public function syncContext(SalesProcessStep $step): SalesProcessStep
    {
        $step->loadMissing(['salesTransaction.spr', 'salesTransaction.customer', 'salesTransaction.housingUnit', 'salesTransaction.paymentSchedules', 'salesTransaction.customerReceipts']);
        $transaction = $step->salesTransaction;
        $spr = $transaction?->spr;
        $customer = $transaction?->customer;
        $unit = $transaction?->housingUnit;
        $latestProgress = $unit ? DB::table('progress_pembangunans')->where('detail_rumah_id', $unit->id)->whereNull('deleted_at')->orderByDesc('tanggal')->orderByDesc('id')->first() : null;
        $latestInspection = $unit ? DB::table('quality_inspections')->where('detail_rumah_id', $unit->id)->whereNull('deleted_at')->orderByDesc('tanggal')->orderByDesc('id')->first() : null;
        $defects = $unit ? DB::table('field_defects')->where('detail_rumah_id', $unit->id)->whereNull('deleted_at')->get() : collect();
        $openCriticalDefects = $defects->whereIn('prioritas', ['urgent', 'critical', 'high'])->whereNotIn('status', ['selesai', 'closed', 'resolved'])->count();
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
        $constructionReady = $unit && ($unit->status_pembangunan === 'selesai' || (float) $unit->progress_terakhir >= 100);
        $qualityReady = (bool) $latestInspection && in_array($latestInspection->record_status ?? null, ['locked'], true) && $openCriticalDefects === 0;
        $specific = match ($step->code) {
            'contract_review' => ['grace_days' => $master['grace_period_days'] ?? 0, 'penalty_terms' => $this->penaltySummary($master), 'early_settlement_terms' => $this->advancedTerm($master, 'early_settlement', 'early_settlement_terms', 'Pelunasan dipercepat'), 'cancellation_terms' => $this->advancedTerm($master, 'cancellation', 'cancellation_terms', 'Pembatalan kontrak')],
            'affordability_analysis' => ['customer_income' => $customerIncome, 'spouse_income' => $spouseIncome, 'monthly_expense' => $monthlyExpense, 'existing_installment' => $existingInstallment, 'net_disposable_income' => max(0, $totalIncome - $monthlyExpense - $existingInstallment), 'dsr_percent' => $totalIncome > 0 ? round(($existingInstallment / $totalIncome) * 100, 2) : 0, 'requested_financing' => $financing, 'recommended_tenor' => $spr?->kpr_tenor_bulan, 'recommended_installment' => $spr?->nominal_termin],
            'internal_approval' => ['approved_limit' => $financing, 'approved_tenor' => $spr?->kpr_tenor_bulan, 'required_dp' => (float) $spr?->uang_muka],
            'bank_decision' => ['approved_limit' => $financing, 'approved_tenor' => $spr?->kpr_tenor_bulan, 'interest_rate' => $spr?->kpr_bunga_tahunan, 'required_dp' => (float) $spr?->uang_muka],
            'sp3k' => ['approved_limit' => $prior['approved_limit'] ?? $financing, 'tenor_months' => $prior['approved_tenor'] ?? $spr?->kpr_tenor_bulan, 'interest_rate' => $prior['interest_rate'] ?? $spr?->kpr_bunga_tahunan, 'installment' => $prior['installment'] ?? null],
            'contract_preparation' => ['dp_paid' => (float) $spr?->uang_muka, 'shortfall_paid' => 0],
            'contract_signing' => ['customer_name' => $customer?->nama, 'spouse_name' => $customer?->nama_lengkap_pasangan, 'final_contract_value' => $step->salesTransaction?->payment_method === 'kpr_bank' ? $financing : $final, 'notary_name' => $prior['notary_name'] ?? null, 'location' => $prior['contract_location'] ?? null],
            'installment_monitoring' => ['total_bill' => $totalBill, 'total_paid' => $totalPaid, 'outstanding' => max(0, $totalBill - $totalPaid), 'overdue_amount' => (float) $schedules->where('due_date', '<', today())->whereNotIn('status', ['paid', 'cancelled'])->sum(fn ($row) => max(0, (float) $row->amount - (float) $row->paid_amount)), 'payment_condition' => $totalBill > 0 && $totalPaid >= $totalBill ? 'paid_off' : ($totalPaid > 0 ? 'partial' : 'current')],
            'construction_preparation' => ['planned_start' => $unit?->tanggal_mulai_bangun?->format('Y-m-d'), 'planned_finish' => $unit?->tanggal_selesai_bangun?->format('Y-m-d'), 'progress_percent' => (float) ($latestProgress?->persentase_total ?? $unit?->progress_terakhir ?? 0), 'unit_status' => $unit?->status_pembangunan, 'skip_reason' => $constructionReady ? 'Unit sudah selesai / ready stock, tahap ini bisa dilewati.' : null],
            'construction' => ['start_date' => $unit?->tanggal_mulai_bangun?->format('Y-m-d'), 'finish_date' => $unit?->tanggal_selesai_bangun?->format('Y-m-d'), 'progress_percent' => (float) ($latestProgress?->persentase_total ?? $unit?->progress_terakhir ?? 0), 'unit_status' => $unit?->status_pembangunan, 'skip_reason' => $constructionReady ? 'Progress unit sudah selesai, tahap ini bisa dilewati.' : null],
            'quality_inspection' => ['inspection_number' => $latestInspection?->kode_inspeksi, 'inspection_date' => $latestInspection?->tanggal, 'inspection_items' => $latestInspection?->item_pemeriksaan, 'findings' => $latestInspection?->temuan, 'corrective_action' => $latestInspection?->tindakan_perbaikan, 'target_finish' => $latestInspection?->target_selesai, 'critical_defects' => $defects->whereIn('prioritas', ['urgent', 'critical'])->count(), 'major_defects' => $defects->where('prioritas', 'high')->count(), 'minor_defects' => $defects->whereIn('prioritas', ['medium', 'low'])->count(), 'result' => match ($latestInspection?->hasil) {'lulus', 'passed' => 'passed', 'perbaikan', 'conditional' => 'conditional', 'gagal', 'failed' => 'failed', default => null}],
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
        $sourceLabel = in_array($step->code, ['construction_preparation', 'construction'], true) ? 'Otomatis dari unit dan Progress Pembangunan' : ($step->code === 'quality_inspection' ? 'Otomatis dari Inspeksi Mutu dan Daftar Perbaikan' : 'Otomatis dari SPR/transaksi/master metode');
        $metadata['sources'] = array_fill_keys(array_keys($automatic), $sourceLabel);
        if ($step->code === 'quality_inspection') {
            $metadata['skip_reason'] = $qualityReady ? 'Inspeksi mutu sudah final dan defect kritis sudah tertutup.' : ($metadata['skip_reason'] ?? null);
            $metadata['inspection_status'] = $latestInspection?->record_status ?? null;
            $metadata['open_critical_defects'] = $openCriticalDefects;
        }
        if ($metadata !== $step->metadata) {
            $step->update(['metadata' => $metadata]);
        }
        if ($step->code === 'quality_inspection') {
            $inspectionFinal = $latestInspection && ($latestInspection->record_status === 'locked' || $latestInspection->approval_status === 'approved');
            $openCritical = $openCriticalDefects;
            $step->checklistItems()->updateOrCreate(['item_key' => 'inspection_linked'], ['label' => 'Inspeksi mutu unit sudah final', 'is_required' => true, 'is_completed' => $inspectionFinal]);
            $step->checklistItems()->updateOrCreate(['item_key' => 'critical_defects_closed'], ['label' => 'Seluruh defect mayor/kritis ditutup', 'is_required' => true, 'is_completed' => $inspectionFinal && $openCritical === 0]);
        }

        return $step->fresh();
    }

    public function approve(SalesProcessStep $step): void
    {
        DB::transaction(function () use ($step) {
            $step = SalesProcessStep::with('salesTransaction.housingUnit')->lockForUpdate()->findOrFail($step->id);
            if ($step->status === 'completed') {
                $this->syncCommercialUnitStatus($step->salesTransaction, $step);
                app(HousingReservationService::class)->syncProcessStep($step);

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
            $this->syncCommercialUnitStatus($step->salesTransaction, $step);
            if ($step->code === 'completed') {
                $step->salesTransaction->update(['status' => 'completed']);
            }
            app(HousingReservationService::class)->syncProcessStep($step->fresh());
        });
    }

    public function syncCommercialUnitStatus(SalesTransaction $transaction, ?SalesProcessStep $sourceStep = null): void
    {
        $transaction->loadMissing(['housingUnit', 'processSteps', 'customer']);
        $unit = $transaction->housingUnit;
        if (! $unit || in_array($transaction->status, ['cancelled', 'closed_lost'], true)) {
            return;
        }

        $moveInCompleted = $transaction->processSteps->contains(fn (SalesProcessStep $step) => $step->code === 'move_in' && $step->status === 'completed');
        if ($moveInCompleted || ($sourceStep?->code === 'move_in' && $sourceStep->status === 'completed')) {
            app(UnitOwnershipService::class)->syncSalesTransaction($transaction, $sourceStep);
            $unit->update(['status_penjualan' => 'ditempati']);

            return;
        }

        $contractCompleted = $transaction->processSteps->contains(fn (SalesProcessStep $step) => in_array($step->code, ['contract_signing', 'customer_handover'], true) && $step->status === 'completed');
        $readyStock = $unit->status_pembangunan === 'selesai' || (float) $unit->progress_terakhir >= 100;
        if ($contractCompleted || $readyStock) {
            app(UnitOwnershipService::class)->syncSalesTransaction($transaction, $sourceStep);

            return;
        }

        if (in_array($unit->status_penjualan, [null, 'tersedia', 'available'], true)) {
            $unit->update(['status_penjualan' => 'booking', 'booking_at' => $unit->booking_at ?? now()]);
        }
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

        return $codes->isEmpty() || ! SalesProcessStep::where('sales_transaction_id', $step->sales_transaction_id)->whereIn('code', $codes)->whereNotIn('status', ['completed', 'skipped'])->exists();
    }

    public function syncUnitState(SalesTransaction $transaction): void
    {
        $transaction->loadMissing(['housingUnit', 'processSteps']);
        $unit = $transaction->housingUnit;
        if (! $unit) return;
        $ready = $unit->status_pembangunan === 'selesai' || (float) $unit->progress_terakhir >= 100;
        $latestInspection = DB::table('quality_inspections')
            ->where('detail_rumah_id', $unit->id)
            ->whereNull('deleted_at')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first();
        $openCriticalDefects = DB::table('field_defects')
            ->where('detail_rumah_id', $unit->id)
            ->whereNull('deleted_at')
            ->whereIn('prioritas', ['urgent', 'critical', 'high'])
            ->whereNotIn('status', ['selesai', 'closed', 'resolved'])
            ->count();
        $inspectionFinal = (bool) $latestInspection && in_array($latestInspection->record_status ?? null, ['locked'], true) && $openCriticalDefects === 0;
        foreach ($transaction->processSteps->whereIn('code', ['construction_preparation', 'construction']) as $step) {
            $metadata = $step->metadata ?? [];
            if ($ready && $step->status !== 'completed') {
                $metadata['skip_reason'] = 'Unit sudah selesai / ready stock sehingga tahap pembangunan tidak diperlukan.';
                $metadata['data']['skip_reason'] = $metadata['skip_reason'];
                $metadata['unit_condition'] = 'ready_to_occupy';
                $step->update(['status' => 'skipped', 'actual_date' => $unit->tanggal_selesai_bangun ?: today(), 'metadata' => $metadata]);
            } elseif (! $ready && $step->status === 'skipped') {
                unset($metadata['skip_reason']);
                unset($metadata['data']['skip_reason']);
                $metadata['unit_condition'] = 'under_construction';
                $step->update(['status' => $this->dependenciesMet($step) ? 'available' : 'waiting', 'actual_date' => null, 'metadata' => $metadata]);
            }
        }
        foreach ($transaction->processSteps->where('code', 'quality_inspection') as $step) {
            $metadata = $step->metadata ?? [];
            if ($inspectionFinal && $step->status !== 'completed') {
                $metadata['skip_reason'] = 'Inspeksi mutu sudah final dan defect kritis sudah tertutup.';
                $metadata['inspection_status'] = $latestInspection->record_status ?? null;
                $metadata['open_critical_defects'] = $openCriticalDefects;
                $step->update(['status' => 'skipped', 'actual_date' => $latestInspection->tanggal ?? today(), 'metadata' => $metadata]);
            } elseif (! $inspectionFinal && $step->status === 'skipped') {
                unset($metadata['skip_reason'], $metadata['inspection_status'], $metadata['open_critical_defects']);
                $step->update(['status' => $this->dependenciesMet($step) ? 'available' : 'waiting', 'actual_date' => null, 'metadata' => $metadata]);
            }
        }
        $transaction->processSteps()->where('status', 'waiting')->get()->each(function ($candidate) {
            if ($this->dependenciesMet($candidate)) $candidate->update(['status' => 'available']);
        });
    }

    public function syncLinkedUnitData(int $unitId): void
    {
        SalesTransaction::query()->where('detail_rumah_id', $unitId)->with(['housingUnit', 'processSteps'])->get()->each(function ($transaction) {
            $this->syncUnitState($transaction);
            $transaction->processSteps->whereIn('code', ['construction_preparation', 'construction', 'quality_inspection'])->each(fn ($step) => $this->syncContext($step));
        });
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
