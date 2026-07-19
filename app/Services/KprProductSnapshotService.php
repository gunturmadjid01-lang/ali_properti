<?php

namespace App\Services;

use App\Models\BankCreditProduct;
use App\Models\KprSubmission;

class KprProductSnapshotService
{
    public function apply(KprSubmission $submission, BankCreditProduct $product): void
    {
        $version = $product->versions()->latest('version_number')->first();
        if (! $version) {
            $version = app(BankCreditProductService::class)->createVersion($product);
        }
        $submission->update([
            'bank_kredit_id' => $product->bank_kredit_id,
            'bank_branch_id' => $product->bank_branch_id,
            'bank_credit_product_id' => $product->id,
            'bank_credit_product_version_id' => $version->id,
            'bank_product_snapshot' => $version->terms_snapshot,
        ]);
    }

    public function applyBestAvailable(KprSubmission $submission, ?int $productId = null): void
    {
        $product = BankCreditProduct::query()
            ->where('status', 'aktif')
            ->when($productId, fn ($query) => $query->whereKey($productId), fn ($query) => $query->where('bank_kredit_id', $submission->bank_kredit_id))
            ->whereDate('effective_from', '<=', $submission->tanggal_pengajuan ?? now()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $submission->tanggal_pengajuan ?? now()->toDateString()))
            ->orderByDesc('effective_from')
            ->first();
        if ($product) {
            $this->apply($submission, $product);
        }
    }
}
