<?php

namespace App\Services;

use App\Models\BankHousingPartnership;

class BankPartnershipService
{
    public function createVersion(BankHousingPartnership $partnership): void
    {
        $partnership->loadMissing(['bank:id,kode_bank,nama_bank', 'branch:id,branch_code,branch_name', 'housing:id,nama_perusahaan']);
        $partnership->versions()->create([
            'version_number' => $partnership->current_version,
            'agreement_snapshot' => [
                ...$partnership->only(['bank_kredit_id', 'bank_branch_id', 'perumahan_id', 'agreement_number', 'agreement_name', 'effective_from', 'effective_until', 'status', 'notes']),
                'bank_name' => $partnership->bank?->nama_bank,
                'branch_name' => $partnership->branch?->branch_name,
                'housing_name' => $partnership->housing?->nama_perusahaan,
            ],
            'effective_from' => $partnership->effective_from,
            'effective_until' => $partnership->effective_until,
            'created_by' => auth()->id(),
        ]);
    }

    public function updateWithVersion(BankHousingPartnership $partnership, array $payload): void
    {
        $changed = collect($payload)->contains(fn ($value, $field) => (string) $partnership->{$field} !== (string) $value);
        $partnership->fill($payload);
        if ($changed) {
            $partnership->current_version = ((int) $partnership->current_version) + 1;
        }
        $partnership->save();
        if ($changed) {
            $this->createVersion($partnership);
        }
    }
}
