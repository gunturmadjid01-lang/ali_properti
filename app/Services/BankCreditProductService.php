<?php

namespace App\Services;

use App\Models\BankCreditProduct;
use App\Models\BankCreditProductVersion;

class BankCreditProductService
{
    public const VERSIONED_FIELDS = ['bank_kredit_id', 'bank_branch_id', 'product_code', 'product_name', 'product_type', 'subsidy_type', 'scheme_type', 'minimum_ceiling', 'maximum_ceiling', 'minimum_down_payment', 'maximum_tenor_months', 'indicative_interest_margin', 'provision_fee', 'administration_fee', 'appraisal_fee', 'insurance_fee', 'notary_fee', 'disbursement_method', 'estimated_sla_days', 'effective_from', 'effective_until', 'status', 'notes'];

    public function createVersion(BankCreditProduct $product): BankCreditProductVersion
    {
        $version = max(1, (int) $product->current_version);
        $product->updateQuietly(['current_version' => $version]);

        return $product->versions()->create([
            'version_number' => $version,
            'terms_snapshot' => $this->snapshot($product),
            'effective_from' => $product->effective_from,
            'effective_until' => $product->effective_until,
            'created_by' => auth()->id(),
        ]);
    }

    public function updateWithVersion(BankCreditProduct $product, array $payload): void
    {
        $changed = collect(self::VERSIONED_FIELDS)->contains(fn (string $field) => array_key_exists($field, $payload) && (string) $product->{$field} !== (string) $payload[$field]);
        $product->fill($payload);
        if ($changed) {
            $product->current_version = ((int) $product->current_version) + 1;
        }
        $product->save();
        if ($changed) {
            $this->createVersion($product);
        }
    }

    public function snapshot(BankCreditProduct $product): array
    {
        $product->loadMissing(['bank:id,kode_bank,nama_bank,jenis_bank', 'branch:id,branch_code,branch_name']);

        return [
            ...$product->only(self::VERSIONED_FIELDS),
            'product_id' => $product->id,
            'version_number' => (int) $product->current_version,
            'bank' => $product->bank?->only(['id', 'kode_bank', 'nama_bank', 'jenis_bank']),
            'branch' => $product->branch?->only(['id', 'branch_code', 'branch_name']),
            'snapshotted_at' => now()->toISOString(),
        ];
    }
}
