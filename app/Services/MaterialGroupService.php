<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\MaterialGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialGroupService
{
    public function __construct(private readonly MaterialUnitConversionService $conversionService) {}

    public function normalizedItems(array $rows): array
    {
        $materialIds = collect($rows)->pluck('barang_material_id')->map(fn ($id) => (int) $id)->filter();
        if ($materialIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Satu material cukup dimasukkan satu kali dalam kelompok.']);
        }

        $materials = BarangMaterial::query()
            ->with(['baseUnit', 'unitConversions.childUnit'])
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');

        return collect(array_values($rows))->map(function (array $row, int $index) use ($materials) {
            $material = $materials->get((int) $row['barang_material_id']);
            if (! $material) {
                throw ValidationException::withMessages(["items.{$index}.barang_material_id" => 'Material tidak ditemukan.']);
            }

            $quantity = (float) $row['quantity'];
            $normalized = $this->conversionService->normalize($material, $row['material_unit_id'], $quantity);

            return [
                'barang_material_id' => $material->id,
                'material_unit_id' => $normalized['unit_id'],
                'quantity' => $quantity,
                'conversion_to_base' => $normalized['factor_to_base'],
                'quantity_base' => $normalized['quantity_base'],
                'sort_order' => $index + 1,
            ];
        })->all();
    }

    public function syncItems(MaterialGroup $group, array $rows): void
    {
        $items = $this->normalizedItems($rows);
        DB::transaction(function () use ($group, $items) {
            $group->items()->delete();
            $group->items()->createMany($items);
        });
    }
}
