<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\PaymentSchedule;
use App\Models\QualityUpgradeContract;
use App\Models\QualityUpgradeProgress;
use App\Models\QualityUpgradeAddendum;
use App\Models\MaterialUsage;
use App\Models\QualityUpgradeHandover;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QualityUpgradeContractService
{
    public function save(QualityUpgradeContract $contract, array $data): QualityUpgradeContract
    {
        return DB::transaction(function () use ($contract, $data): QualityUpgradeContract {
            $items = collect($data['items']);
            $subtotal = round((float) $items->sum(fn (array $item) => max(0, (float) $item['volume'] * (float) $item['unit_price'] - (float) ($item['discount'] ?? 0))), 2);
            $discount = min($subtotal, (float) ($data['discount'] ?? 0));
            $tax = max(0, (float) ($data['tax_amount'] ?? 0));
            $value = max(0, $subtotal - $discount + $tax);
            $customer = \App\Models\Costumer::query()->findOrFail($data['costumer_id']);
            $unit = \App\Models\DetailRumah::query()->with('perumahan')->findOrFail($data['detail_rumah_id']);
            $company = \App\Models\CabangPerusahaan::query()->findOrFail($data['company_id']);
            $spr = filled($data['spr_id'] ?? null) ? \App\Models\Spr::query()->findOrFail($data['spr_id']) : null;

            abort_if($spr && ((int) $spr->costumer_id !== (int) $customer->id || (int) $spr->detail_rumah_id !== (int) $unit->id), 422, 'SPR tidak sesuai dengan customer dan unit yang dipilih.');

            $payload = collect($data)->except(['items', 'installments'])->all() + [
                'contract_no' => $contract->contract_no ?: $this->nextNumber(),
                'subtotal' => $subtotal,
                'contract_value' => $value,
                'installment_count' => max(1, count($data['installments'] ?? [])),
                'company_snapshot' => ['id' => $company->id, 'code' => $company->kode_cabang, 'name' => $company->nama_cabang, 'address' => $company->address, 'phone' => $company->phone, 'manager' => $company->manager_name],
                'customer_snapshot' => ['id' => $customer->id, 'name' => $customer->nama, 'identity' => $customer->no_identitas, 'phone' => $customer->telepon, 'address' => $customer->alamat],
                'unit_snapshot' => ['id' => $unit->id, 'housing' => $unit->perumahan?->nama_perusahaan, 'block' => $unit->kode_nlok, 'number' => $unit->nomor_rumah, 'type' => $unit->tipe_rumah],
                'payment_snapshot' => ['method' => $data['payment_method'], 'down_payment' => (float) ($data['down_payment'] ?? 0), 'installments' => array_values($data['installments'] ?? [])],
                'business_status' => 'draft',
                'record_status' => 'draft',
                'updated_by' => auth()->id(),
            ];
            if (! $contract->exists) {
                $payload['created_by'] = auth()->id();
            }
            $contract->fill($payload)->save();
            $contract->items()->delete();
            $contract->items()->createMany($items->map(fn (array $item, int $index) => [
                'item_code' => $item['item_code'] ?? 'PM-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'quality_upgrade_catalog_id' => $item['quality_upgrade_catalog_id'] ?? null,
                'name' => $item['name'],
                'specification' => $item['specification'] ?? null,
                'location' => $item['location'] ?? null,
                'volume' => $item['volume'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'discount' => $item['discount'] ?? 0,
                'total' => max(0, (float) $item['volume'] * (float) $item['unit_price'] - (float) ($item['discount'] ?? 0)),
                'estimated_material_cost' => $item['estimated_material_cost'] ?? 0,
                'estimated_labor_cost' => $item['estimated_labor_cost'] ?? 0,
                'estimated_other_cost' => $item['estimated_other_cost'] ?? 0,
            ])->all());

            return $contract->fresh(['items', 'customer', 'unit.perumahan', 'company']);
        });
    }

    public function approve(QualityUpgradeContract $contract): void
    {
        DB::transaction(function () use ($contract): void {
            $contract = QualityUpgradeContract::query()->with('items')->lockForUpdate()->findOrFail($contract->id);
            if ($contract->business_status === 'active') {
                return;
            }
            abort_if($contract->items->isEmpty(), 422, 'Kontrak tidak memiliki item pekerjaan.');

            $installments = collect($contract->payment_snapshot['installments'] ?? []);
            if ($installments->isEmpty()) {
                $installments = collect([['description' => $contract->payment_method === 'cash' ? 'Pelunasan Cash' : 'Termin 1', 'due_date' => $contract->contract_date->toDateString(), 'amount' => $contract->contract_value]]);
            }
            $sum = round((float) $installments->sum('amount'), 2);
            abort_if(abs($sum - (float) $contract->contract_value) > 0.01, 422, 'Total jadwal pembayaran harus sama dengan nilai kontrak.');

            foreach ($installments->values() as $index => $item) {
                $schedule = PaymentSchedule::query()->firstOrCreate(
                    ['source_type' => QualityUpgradeContract::class, 'source_id' => $contract->id, 'sequence' => $index + 1],
                    [
                        'sales_transaction_id' => null,
                        'quality_upgrade_contract_id' => $contract->id,
                        'type' => $index === 0 && (float) $contract->down_payment > 0
                            ? 'quality_upgrade_down_payment'
                            : ($contract->payment_method === 'cash' ? 'quality_upgrade_cash' : 'quality_upgrade_installment'),
                        'description' => $item['description'] ?? 'Termin '.($index + 1),
                        'issued_at' => $contract->contract_date,
                        'due_date' => $item['due_date'],
                        'amount' => $item['amount'],
                        'paid_amount' => 0,
                        'status' => 'belum_dibayar',
                        'record_status' => 'locked',
                        'locked_at' => now(),
                        'locked_by' => auth()->id(),
                    ],
                );
                if (! $schedule->invoice_no) {
                    $schedule->update(['invoice_no' => 'INV-MUTU/'.now()->format('Y').'/'.str_pad((string) $schedule->id, 7, '0', STR_PAD_LEFT)]);
                }
                $journal = app(AccountingService::class)->postJournal(
                    $schedule,
                    'quality_upgrade_invoice',
                    $contract->contract_date->toDateString(),
                    $contract->unit?->perumahan_id,
                    $contract->detail_rumah_id,
                    "{$schedule->invoice_no} - {$contract->contract_no}",
                    [
                        ['account' => ChartOfAccount::PIUTANG_PENAMBAHAN_MUTU, 'debit' => $schedule->amount, 'kredit' => 0],
                        ['account' => ChartOfAccount::PENDAPATAN_PENAMBAHAN_MUTU, 'debit' => 0, 'kredit' => $schedule->amount],
                    ],
                );
                $journal->update(['cabang_perusahaan_id' => $contract->company_id]);
            }

            $contract->update(['business_status' => 'active', 'approved_at' => now(), 'approved_by' => auth()->id(), 'updated_by' => auth()->id()]);
        });
    }

    public function reverseForUnlock(QualityUpgradeContract $contract): void
    {
        DB::transaction(function () use ($contract): void {
            $contract = QualityUpgradeContract::query()->with('schedules.allocations.receipt')->lockForUpdate()->findOrFail($contract->id);
            foreach ($contract->schedules as $schedule) {
                if ((float) $schedule->paid_amount > 0 || $schedule->allocations->contains(fn ($allocation) => in_array($allocation->receipt?->status, ['pending_approval', 'posted'], true))) {
                    throw ValidationException::withMessages(['unlock' => 'Kontrak tidak dapat di-unlock karena sudah memiliki penerimaan yang diproses. Reversal penerimaan wajib dilakukan terlebih dahulu.']);
                }
            }
            Journal::query()->where('source_type', PaymentSchedule::class)->whereIn('source_id', $contract->schedules->modelKeys())->get()->each(function (Journal $journal): void {
                $journal->details()->delete();
                $journal->delete();
            });
            $contract->schedules()->delete();
            $contract->update(['business_status' => 'draft', 'approved_at' => null, 'approved_by' => null, 'updated_by' => auth()->id()]);
        });
    }

    public function recordProgress(QualityUpgradeContract $contract, array $data): QualityUpgradeProgress
    {
        return DB::transaction(function () use ($contract, $data): QualityUpgradeProgress {
            $contract = QualityUpgradeContract::query()->with('items')->lockForUpdate()->findOrFail($contract->id);
            abort_unless($contract->business_status === 'active', 422, 'Progress hanya dapat dicatat pada kontrak aktif.');
            abort_unless($contract->items->contains('id', (int) $data['quality_upgrade_contract_item_id']), 422, 'Item pekerjaan tidak termasuk dalam kontrak ini.');
            $progress = $contract->progresses()->create($data + ['created_by' => auth()->id()]);

            foreach ($contract->items as $item) {
                $reports = $contract->progresses()->where('quality_upgrade_contract_item_id', $item->id);
                $percent = min(100, max(0, (float) $reports->max('progress_percent')));
                $costs = $reports->selectRaw('COALESCE(SUM(material_cost),0) material, COALESCE(SUM(labor_cost),0) labor, COALESCE(SUM(other_cost),0) other')->first();
                $item->update(['progress_percent' => $percent, 'work_status' => $percent >= 100 ? 'completed' : ($percent > 0 ? 'in_progress' : 'not_started'), 'material_cost' => $costs->material, 'labor_cost' => $costs->labor, 'other_cost' => $costs->other]);
            }

            $contract->load('items', 'materialUsages.details');
            $weighted = (float) $contract->items->sum(fn ($item) => $item->total * $item->progress_percent) / max(1, (float) $contract->items->sum('total'));
            $stockCost = (float) $contract->materialUsages->whereNotNull('stock_posted_at')->sum(fn ($usage) => $usage->details->sum('subtotal_snapshot'));
            $contract->update([
                'progress_percent' => round($weighted, 2),
                'actual_material_cost' => max($stockCost, (float) $contract->items->sum('material_cost')),
                'actual_labor_cost' => (float) $contract->items->sum('labor_cost'),
                'actual_other_cost' => (float) $contract->items->sum('other_cost'),
                'started_at' => $contract->started_at ?: now(),
                'completed_at' => $weighted >= 100 ? ($contract->completed_at ?: now()) : null,
                'business_status' => $weighted >= 100 ? 'completed' : 'active',
            ]);

            return $progress;
        });
    }

    public function cancel(QualityUpgradeContract $contract, string $reason): void
    {
        DB::transaction(function () use ($contract, $reason): void {
            $contract = QualityUpgradeContract::query()->with('schedules.allocations.receipt')->lockForUpdate()->findOrFail($contract->id);
            abort_if($contract->progress_percent > 0, 422, 'Kontrak yang sudah memiliki progres harus diselesaikan melalui addendum/serah terima.');
            abort_if($contract->schedules->sum('paid_amount') > 0, 422, 'Lakukan reversal/refund penerimaan terlebih dahulu.');
            $this->reverseForUnlock($contract);
            $contract->update(['business_status' => 'cancelled', 'record_status' => 'locked', 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancellation_reason' => $reason, 'updated_by' => auth()->id()]);
        });
    }

    public function approveAddendum(QualityUpgradeAddendum $addendum): void
    {
        DB::transaction(function () use ($addendum): void {
            $addendum = QualityUpgradeAddendum::query()->with('contract.unit')->lockForUpdate()->findOrFail($addendum->id);
            if ($addendum->status === 'approved' && $addendum->applied_at) {
                return;
            }
            $contract = QualityUpgradeContract::query()->lockForUpdate()->findOrFail($addendum->quality_upgrade_contract_id);
            abort_unless(in_array($contract->business_status, ['active', 'completed'], true), 422, 'Addendum hanya dapat diterapkan pada kontrak aktif atau selesai.');

            if ((float) $addendum->value_change > 0) {
                $schedule = PaymentSchedule::query()->firstOrCreate(
                    ['source_type' => QualityUpgradeAddendum::class, 'source_id' => $addendum->id, 'sequence' => 1],
                    ['quality_upgrade_contract_id' => $contract->id, 'type' => 'quality_upgrade_addendum', 'description' => "Addendum {$addendum->addendum_no}", 'issued_at' => $addendum->addendum_date, 'due_date' => $addendum->billing_due_date ?: $addendum->addendum_date, 'amount' => $addendum->value_change, 'paid_amount' => 0, 'status' => 'belum_dibayar', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id()],
                );
                if (! $schedule->invoice_no) {
                    $schedule->update(['invoice_no' => 'INV-MUTU-ADD/'.now()->format('Y').'/'.str_pad((string) $schedule->id, 7, '0', STR_PAD_LEFT)]);
                }
                $journal = app(AccountingService::class)->postJournal($schedule, 'quality_upgrade_addendum_invoice', $addendum->addendum_date->toDateString(), $contract->unit?->perumahan_id, $contract->detail_rumah_id, "{$schedule->invoice_no} - {$contract->contract_no}", [
                    ['account' => ChartOfAccount::PIUTANG_PENAMBAHAN_MUTU, 'debit' => $schedule->amount, 'kredit' => 0],
                    ['account' => ChartOfAccount::PENDAPATAN_PENAMBAHAN_MUTU, 'debit' => 0, 'kredit' => $schedule->amount],
                ]);
                $journal->update(['cabang_perusahaan_id' => $contract->company_id]);
            }

            $contract->update(['contract_value' => (float) $contract->contract_value + (float) $addendum->value_change, 'planned_finish_date' => $addendum->finish_date_change ?: $contract->planned_finish_date, 'document_version' => (int) $contract->document_version + 1, 'updated_by' => auth()->id()]);
            $addendum->update(['status' => 'approved', 'applied_at' => now(), 'applied_by' => auth()->id(), 'updated_by' => auth()->id()]);
        });
    }

    public function reverseAddendum(QualityUpgradeAddendum $addendum): void
    {
        DB::transaction(function () use ($addendum): void {
            $addendum = QualityUpgradeAddendum::query()->with('contract')->lockForUpdate()->findOrFail($addendum->id);
            if (! $addendum->applied_at) {
                $addendum->update(['status' => 'draft', 'updated_by' => auth()->id()]);
                return;
            }
            $schedule = PaymentSchedule::query()->where(['source_type' => QualityUpgradeAddendum::class, 'source_id' => $addendum->id])->first();
            abort_if($schedule && (float) $schedule->paid_amount > 0, 422, 'Invoice addendum sudah dibayar. Reversal/refund penerimaan wajib dilakukan terlebih dahulu.');
            if ($schedule) {
                Journal::query()->where('source_type', PaymentSchedule::class)->where('source_id', $schedule->id)->get()->each(function (Journal $journal): void {
                    $journal->details()->delete();
                    $journal->delete();
                });
                $schedule->delete();
            }
            $contract = QualityUpgradeContract::query()->lockForUpdate()->findOrFail($addendum->quality_upgrade_contract_id);
            $contract->update(['contract_value' => max(0, (float) $contract->contract_value - (float) $addendum->value_change), 'planned_finish_date' => $addendum->change_snapshot['before_finish_date'] ?? $contract->planned_finish_date, 'document_version' => max(1, (int) $contract->document_version - 1), 'updated_by' => auth()->id()]);
            $addendum->update(['status' => 'draft', 'applied_at' => null, 'applied_by' => null, 'updated_by' => auth()->id()]);
        });
    }

    public function syncMaterialCost(QualityUpgradeContract|int $contract): void
    {
        $contract = $contract instanceof QualityUpgradeContract ? $contract : QualityUpgradeContract::query()->findOrFail($contract);
        $cost = (float) $contract->materialUsages()->whereNotNull('stock_posted_at')->with('details')->get()->sum(fn ($usage) => $usage->details->sum('subtotal_snapshot'));
        $contract->update(['actual_material_cost' => $cost, 'updated_by' => auth()->id()]);
    }

    public function postMaterialHpp(MaterialUsage $usage): void
    {
        if (! $usage->quality_upgrade_contract_id) {
            return;
        }
        $usage->loadMissing(['details', 'qualityUpgradeContract.unit']);
        $amount = round((float) $usage->details->sum('subtotal_snapshot'), 2);
        if ($amount <= 0) {
            return;
        }
        $contract = $usage->qualityUpgradeContract;
        $journal = app(AccountingService::class)->postJournal($usage, 'quality_upgrade_material_hpp', $usage->tanggal->toDateString(), $contract->unit?->perumahan_id, $contract->detail_rumah_id, "{$usage->kode_pemakaian} - {$contract->contract_no}", [
            ['account' => ChartOfAccount::HPP_PENAMBAHAN_MUTU, 'debit' => $amount, 'kredit' => 0],
            ['account' => ChartOfAccount::PERSEDIAAN_MATERIAL, 'debit' => 0, 'kredit' => $amount],
        ]);
        $journal->update(['cabang_perusahaan_id' => $contract->company_id]);
    }

    public function reverseMaterialHpp(MaterialUsage $usage): void
    {
        Journal::query()->where(['source_type' => MaterialUsage::class, 'source_id' => $usage->id, 'type' => 'quality_upgrade_material_hpp'])->get()->each(function (Journal $journal): void {
            $journal->details()->delete();
            $journal->delete();
        });
    }

    public function approveHandover(QualityUpgradeHandover $handover): void
    {
        DB::transaction(function () use ($handover): void {
            $handover = QualityUpgradeHandover::query()->with('contract')->lockForUpdate()->findOrFail($handover->id);
            if ($handover->status === 'approved' && $handover->approved_at) {
                return;
            }
            $contract = QualityUpgradeContract::query()->withCount(['defects as open_defects_count' => fn ($query) => $query->whereNotIn('status', ['resolved', 'closed'])])->lockForUpdate()->findOrFail($handover->quality_upgrade_contract_id);
            abort_if((float) $contract->progress_percent < 100, 422, 'Serah terima hanya dapat disetujui setelah progres 100%.');
            abort_if($contract->open_defects_count > 0, 422, 'Masih ada defect yang belum diselesaikan.');
            $warrantyEnd = $handover->handover_date->copy()->addDays((int) $contract->warranty_days);
            $handover->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id(), 'updated_by' => auth()->id()]);
            $contract->update(['business_status' => 'handed_over', 'handed_over_at' => $handover->handover_date->endOfDay(), 'warranty_end_date' => $warrantyEnd, 'updated_by' => auth()->id()]);
        });
    }

    public function reverseHandover(QualityUpgradeHandover $handover): void
    {
        DB::transaction(function () use ($handover): void {
            $handover = QualityUpgradeHandover::query()->with('contract')->lockForUpdate()->findOrFail($handover->id);
            if (! $handover->approved_at) {
                $handover->update(['status' => 'draft', 'updated_by' => auth()->id()]);
                return;
            }
            $handover->contract()->update(['business_status' => 'completed', 'handed_over_at' => null, 'warranty_end_date' => null, 'updated_by' => auth()->id()]);
            $handover->update(['status' => 'draft', 'approved_at' => null, 'approved_by' => null, 'updated_by' => auth()->id()]);
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'KPM/'.now()->format('Y/m').'/';
        $last = QualityUpgradeContract::withTrashed()->where('contract_no', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $last, 5, '0', STR_PAD_LEFT);
    }
}
