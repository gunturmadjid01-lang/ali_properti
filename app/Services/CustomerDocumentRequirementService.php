<?php

namespace App\Services;

use App\Models\Costumer;
use App\Models\CustomerDocument;
use App\Models\DocumentRequirementSet;
use App\Models\SalesProcessStep;
use App\Models\Spr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerDocumentRequirementService
{
    public function forChecklist(Costumer $customer): Collection
    {
        $spr = Spr::query()
            ->with(['costumer', 'detailRumah.perumahan', 'bankCreditProduct'])
            ->where('costumer_id', $customer->id)
            ->latest('id')
            ->first();

        if (! $spr) {
            return collect();
        }

        $applications = collect(['spr', $spr->metode_pembayaran])
            ->filter(fn (?string $application) => in_array($application, ['spr', 'cash_bertahap', 'kpr_developer', 'kpr_bank'], true))
            ->unique()
            ->values();
        $repository = CustomerDocument::query()
            ->where('costumer_id', $customer->id)
            ->where('status', 'active')
            ->latest('id')
            ->get()
            ->unique(fn (CustomerDocument $document) => $document->dokumen_costumer_id.'|'.($document->party_scope ?: 'customer'))
            ->keyBy(fn (CustomerDocument $document) => $document->dokumen_costumer_id.'|'.($document->party_scope ?: 'customer'));
        $candidateSets = $this->matchingSets($spr);

        return $applications
            ->flatMap(fn (string $application) => $this->setsFromCandidates($candidateSets, $application))
            ->unique('id')
            ->flatMap(fn ($set) => $set->items
                ->filter(fn ($item) => $this->condition($item, $spr))
                ->map(function ($item) use ($set, $repository) {
                    $partyScope = $item->party_scope ?: 'customer';
                    $file = $repository->get($item->dokumen_costumer_id.'|'.$partyScope);

                    return [
                        ...$this->row($item, $set, (bool) $file, $file?->nama_file, []),
                        'requirement_item_id' => $item->id,
                        'customer_document_id' => $file?->id,
                        'file_path' => $file?->path_file,
                        'expires_at' => $file?->expires_at?->format('Y-m-d'),
                    ];
                }))
            ->pipe(fn (Collection $rows) => $this->merge($rows));
    }

    public function forSpr(Spr $spr): Collection
    {
        $spr->loadMissing(['costumer', 'detailRumah.perumahan', 'berkasCostumers.dokumen', 'bankCreditProduct']);
        $uploaded = $spr->berkasCostumers->keyBy('dokumen_costumer_id');

        return $this->sets($spr, 'spr')->flatMap(fn ($set) => $set->items
            ->filter(fn ($item) => $this->condition($item, $spr))
            ->map(function ($item) use ($set, $uploaded) {
                $file = $uploaded->get($item->dokumen_costumer_id);

                return $this->row($item, $set, (bool) $file, $file?->nama_file, []);
            }))->pipe(fn ($rows) => $this->merge($rows));
    }

    public function forStage(SalesProcessStep $step): Collection
    {
        $step->loadMissing(['salesTransaction.spr.costumer', 'salesTransaction.spr.detailRumah.perumahan', 'salesTransaction.spr.bankCreditProduct', 'customerDocuments']);
        $spr = $step->salesTransaction->spr;
        $method = $step->salesTransaction->payment_method;
        $checks = DB::table('sales_stage_document_checklists')->where('sales_process_step_id', $step->id)->get()->keyBy('document_requirement_set_item_id');

        return $this->sets($spr, $method)->flatMap(fn ($set) => $set->items
            ->filter(fn ($item) => $this->condition($item, $spr) && $this->belongsToStage($item->process_stage_code, $step->code, $method))
            ->map(function ($item) use ($set, $checks) {
                $check = $checks->get($item->id);

                return [...$this->row($item, $set, (bool) ($check?->is_complete), null, []), 'requirement_item_id' => $item->id, 'complete' => (bool) ($check?->is_complete), 'check_notes' => $check?->notes];
            }))->pipe(fn ($rows) => $this->merge($rows));
    }

    private function sets(Spr $spr, string $application): Collection
    {
        return $this->setsFromCandidates($this->matchingSets($spr), $application);
    }

    private function matchingSets(Spr $spr): Collection
    {
        return DocumentRequirementSet::with(['items.document', 'banks:id', 'products:id', 'housings:id', 'companies:id', 'partnerships:id,bank_kredit_id,perumahan_id'])
            ->where('status', 'aktif')->where('record_status', 'locked')->whereHas('approvalRequests', fn ($q) => $q->where('status', 'approved'))->get()
            ->filter(fn ($set) => $this->matchesScope($set, $spr));
    }

    private function setsFromCandidates(Collection $candidates, string $application): Collection
    {
        $matches = $candidates
            ->filter(fn ($set) => in_array($application, $set->application_types ?? [], true))
            ->values();
        if ($application === 'spr' || $matches->isEmpty()) {
            return $matches;
        }
        $scores = $matches->mapWithKeys(fn ($set) => [$set->id => $this->specificity($set)]);
        $highest = (int) $scores->max();

        return $highest > 0 ? $matches->filter(fn ($set) => $scores[$set->id] === $highest)->values() : $matches;
    }

    private function specificity($set): int
    {
        return ($set->partnerships->isNotEmpty() ? 8 : 0) + ($set->products->isNotEmpty() ? 4 : 0) + ($set->banks->isNotEmpty() ? 2 : 0) + ($set->housings->isNotEmpty() ? 1 : 0) + ($set->companies->isNotEmpty() ? 1 : 0);
    }

    private function belongsToStage(?string $configured, string $stage, string $method): bool
    {
        if ($configured) {
            return $configured === $stage;
        }

        return $stage === match ($method) {
            'kpr_bank' => 'document_collection','kpr_developer' => 'document_validation','cash_bertahap' => 'contract_review',default => 'contract_signing'
        };
    }

    private function row($item, $set, bool $uploaded, ?string $fileName, array $choices): array
    {
        return ['document_id' => $item->dokumen_costumer_id, 'code' => $item->document?->kode_dokumen, 'label' => $item->document?->nama_dokumen, 'party_scope' => $item->party_scope, 'required' => (bool) $item->is_required, 'uploaded' => $uploaded, 'file_name' => $fileName, 'validity_days' => $item->validity_days, 'source' => $set->name, 'notes' => $item->notes, 'repository_options' => $choices];
    }

    private function merge(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($row) => $row['document_id'].'|'.$row['party_scope'])->map(function ($group) {
            $first = $group->first();
            $first['required'] = $group->contains('required', true);
            $first['source'] = $group->pluck('source')->unique()->join(', ');
            $first['notes'] = $group->pluck('notes')->filter()->unique()->join(' ');

            return $first;
        })->values();
    }

    private function matchesScope($set, Spr $spr): bool
    {
        foreach ([[$set->banks, $spr->bank_kredit_id], [$set->products, $spr->bank_credit_product_id], [$set->housings, $spr->detailRumah?->perumahan_id], [$set->companies, $spr->detailRumah?->perumahan?->cabang_id]] as [$values,$id]) {
            if ($values->isNotEmpty() && ! $values->contains('id', (int) $id)) {
                return false;
            }
        }

        return $set->partnerships->isEmpty() || $set->partnerships->contains(fn ($p) => (int) $p->bank_kredit_id === (int) $spr->bank_kredit_id && (int) $p->perumahan_id === (int) $spr->detailRumah?->perumahan_id);
    }

    private function condition($item, Spr $spr): bool
    {
        $jobs = $item->employment_categories ?? [];
        $marital = $item->marital_statuses ?? [];

        return (! $jobs || in_array($spr->costumer?->employment_category, $jobs, true)) && (! $marital || in_array($spr->costumer?->status_perkawinan, $marital, true));
    }
}
