<?php

namespace App\Services;

use App\Models\CashSale;
use App\Models\Costumer;
use App\Models\SalesProcessStep;
use App\Models\SalesTransaction;
use App\Models\UnitOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnitOwnershipService
{
    public function syncSalesTransaction(SalesTransaction $transaction, ?SalesProcessStep $step = null): ?UnitOwnership
    {
        $transaction->loadMissing('customer');
        $data = $step?->metadata['data'] ?? [];

        return $this->activateFromTransaction(
            $transaction->customer,
            $transaction->detail_rumah_id,
            $transaction->spr_id,
            'sales_process',
            $transaction,
            $transaction->payment_method,
            $step?->actual_date ?? now(),
            $data['contract_number'] ?? $data['handover_number'] ?? $transaction->transaction_no,
        );
    }

    public function syncCashHandover(CashSale $sale): ?UnitOwnership
    {
        $sale->loadMissing('costumer', 'spr');

        return $this->activateFromTransaction(
            $sale->costumer,
            $sale->detail_rumah_id,
            $sale->spr_id,
            'cash_handover',
            $sale,
            'cash',
            now(),
            $sale->kode_cash,
        );
    }

    public function createLegacy(array $payload, Costumer $customer, ?int $userId): UnitOwnership
    {
        return DB::transaction(function () use ($payload, $customer, $userId): UnitOwnership {
            $this->deactivateCurrent((int) $payload['detail_rumah_id'], $payload['acquired_at'], $userId);

            $ownership = UnitOwnership::query()->create([
                ...$this->snapshot($customer, $payload),
                'detail_rumah_id' => $payload['detail_rumah_id'],
                'costumer_id' => $customer->id,
                'spr_id' => null,
                'source_type' => 'legacy',
                'source_id' => null,
                'acquisition_method' => $payload['acquisition_method'] ?? 'data_lama',
                'acquired_at' => $payload['acquired_at'],
                'document_number' => $payload['document_number'] ?? null,
                'attachment_path' => $payload['attachment_path'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $ownership->detailRumah()->update(['status_penjualan' => 'terjual']);

            return $ownership;
        });
    }

    public function deactivate(UnitOwnership $ownership, ?int $userId, ?string $date = null): void
    {
        DB::transaction(function () use ($ownership, $userId, $date): void {
            $ownership->update([
                'is_active' => false,
                'ended_at' => $date ?? now()->toDateString(),
                'updated_by' => $userId,
            ]);
            $this->restoreLatestOrReleaseUnit($ownership->detailRumah, $ownership->id, $userId);
        });
    }

    public function deactivateSource(string $sourceType, int $sourceId, ?int $userId): void
    {
        $ownership = UnitOwnership::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->first();

        if ($ownership) {
            $this->deactivate($ownership, $userId);
        }
    }

    private function activateFromTransaction(?Costumer $customer, mixed $unitId, mixed $sprId, string $sourceType, Model $source, string $method, mixed $date, ?string $document): ?UnitOwnership
    {
        if (! $customer || ! $unitId) {
            return null;
        }

        return DB::transaction(function () use ($customer, $unitId, $sprId, $sourceType, $source, $method, $date, $document): UnitOwnership {
            $existing = UnitOwnership::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $source->getKey())
                ->first();
            $acquiredAt = optional($date)->format('Y-m-d') ?? now()->toDateString();

            $this->deactivateCurrent((int) $unitId, $acquiredAt, auth()->id(), $existing?->id);

            $ownership = UnitOwnership::query()->updateOrCreate(
                ['source_type' => $sourceType, 'source_id' => $source->getKey()],
                [
                    ...$this->snapshot($customer),
                    'detail_rumah_id' => $unitId,
                    'costumer_id' => $customer->id,
                    'spr_id' => $sprId,
                    'acquisition_method' => $method,
                    'acquired_at' => $acquiredAt,
                    'ended_at' => null,
                    'document_number' => $document,
                    'is_active' => true,
                    'record_status' => 'locked',
                    'created_by' => $existing?->created_by ?? auth()->id(),
                    'updated_by' => auth()->id(),
                ],
            );

            $ownership->detailRumah()->update(['status_penjualan' => 'terjual']);

            return $ownership;
        });
    }

    private function deactivateCurrent(int $unitId, mixed $endedAt, ?int $userId, ?int $exceptId = null): void
    {
        UnitOwnership::query()
            ->where('detail_rumah_id', $unitId)
            ->where('is_active', true)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_active' => false, 'ended_at' => $endedAt, 'updated_by' => $userId]);
    }

    private function restoreLatestOrReleaseUnit($unit, int $exceptId, ?int $userId): void
    {
        $previous = UnitOwnership::query()
            ->where('detail_rumah_id', $unit->id)
            ->whereKeyNot($exceptId)
            ->orderByDesc('acquired_at')
            ->orderByDesc('id')
            ->first();

        if ($previous) {
            $previous->update(['is_active' => true, 'ended_at' => null, 'updated_by' => $userId]);
            $unit->update(['status_penjualan' => 'terjual']);
        } else {
            $unit->update(['status_penjualan' => 'tersedia']);
        }
    }

    private function snapshot(Costumer $customer, array $overrides = []): array
    {
        return [
            'owner_name' => $overrides['owner_name'] ?? $customer->nama,
            'identity_type' => $overrides['identity_type'] ?? $customer->jenis_identitas,
            'identity_number' => $overrides['identity_number'] ?? $customer->no_identitas,
            'phone' => $overrides['phone'] ?? $customer->telepon,
            'email' => $overrides['email'] ?? $customer->email,
            'address' => $overrides['address'] ?? $customer->alamat,
            'spouse_name' => $overrides['spouse_name'] ?? $customer->nama_lengkap_pasangan,
        ];
    }
}
