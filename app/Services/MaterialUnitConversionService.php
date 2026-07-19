<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\MaterialUnit;
use Illuminate\Validation\ValidationException;

class MaterialUnitConversionService
{
    public function sync(BarangMaterial $material, array $rows): void
    {
        $material->unitConversions()->delete();
        $parentUnitId = (int) $material->base_unit_id;
        $parentPrice = (float) $material->harga_hpp;
        $cumulative = 1.0;
        $seen = [$parentUnitId];

        foreach (array_values($rows) as $index => $row) {
            $childUnitId = (int) ($row['unit_id'] ?? 0);
            $factor = (float) ($row['factor'] ?? 0);
            if (! $childUnitId || $factor <= 0 || in_array($childUnitId, $seen, true)) {
                throw ValidationException::withMessages(['conversions' => 'Satuan konversi harus unik dan faktor isi harus lebih besar dari nol.']);
            }
            MaterialUnit::query()->finalized()->whereKey($childUnitId)->where('status', 'aktif')->firstOrFail();
            $cumulative *= $factor;
            $childPrice = $parentPrice / $factor;
            $material->unitConversions()->create([
                'level' => $index + 2,
                'parent_unit_id' => $parentUnitId,
                'child_unit_id' => $childUnitId,
                'factor' => $factor,
                'cumulative_factor' => $cumulative,
                'parent_price' => $parentPrice,
                'child_price' => $childPrice,
            ]);
            $seen[] = $childUnitId;
            $parentUnitId = $childUnitId;
            $parentPrice = $childPrice;
        }
    }

    public function options(BarangMaterial $material): array
    {
        $material->loadMissing(['baseUnit', 'unitConversions.childUnit']);
        $base = [[
            'value' => (string) $material->base_unit_id,
            'label' => $material->baseUnit?->name ?? $material->satuan,
            'symbol' => $material->baseUnit?->symbol ?? $material->satuan,
            'level' => 1,
            'factor_to_base' => 1,
            'standard_price' => (float) $material->harga_hpp,
        ]];

        return collect($base)->concat($material->unitConversions->map(fn ($row) => [
            'value' => (string) $row->child_unit_id,
            'label' => $row->childUnit?->name ?? '-',
            'symbol' => $row->childUnit?->symbol ?? '-',
            'level' => (int) $row->level,
            'factor_to_base' => (float) $row->cumulative_factor,
            'standard_price' => (float) $row->child_price,
        ]))->values()->all();
    }

    public function normalize(BarangMaterial $material, int|string|null $unitId, float $quantity, float $unitPrice = 0): array
    {
        $option = collect($this->options($material))->firstWhere('value', (string) ($unitId ?: $material->base_unit_id));
        if (! $option) {
            throw ValidationException::withMessages(['items' => "Satuan tidak terdaftar untuk material {$material->nama_barang}."]);
        }
        $factor = max(1e-9, (float) $option['factor_to_base']);

        return [
            'unit_id' => filled($option['value']) ? (int) $option['value'] : null,
            'unit_symbol' => $option['symbol'],
            'factor_to_base' => $factor,
            'quantity_base' => $quantity / $factor,
            'unit_price_base' => $unitPrice * $factor,
        ];
    }
}
