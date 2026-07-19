<?php

use App\Models\BarangMaterial;
use App\Models\MaterialGroup;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Services\MaterialGroupService;
use App\Services\MaterialUnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('kelompok material menyimpan satuan input dan ekuivalen level satu', function () {
    $type = MaterialType::create(['code' => 'JNS-K', 'name' => 'Kelompok Test', 'status' => 'aktif']);
    $dus = MaterialUnit::create(['code' => 'STN-KD', 'name' => 'Dus Kelompok', 'symbol' => 'DUS-K', 'status' => 'aktif']);
    $pak = MaterialUnit::create(['code' => 'STN-KP', 'name' => 'Pak Kelompok', 'symbol' => 'PAK-K', 'status' => 'aktif']);
    $pcs = MaterialUnit::create(['code' => 'STN-KC', 'name' => 'Pcs Kelompok', 'symbol' => 'PCS-K', 'status' => 'aktif']);
    $material = BarangMaterial::create([
        'nama_barang' => 'Baut Rangka Test', 'material_type_id' => $type->id, 'base_unit_id' => $dus->id,
        'jenis_material' => $type->name, 'kategori_material' => $type->name, 'satuan' => $dus->symbol,
        'harga_hpp' => 120000, 'status' => 'aktif',
    ]);
    app(MaterialUnitConversionService::class)->sync($material, [
        ['unit_id' => $pak->id, 'factor' => 12],
        ['unit_id' => $pcs->id, 'factor' => 10],
    ]);
    $group = MaterialGroup::create(['name' => 'Kelompok Baut', 'base_quantity' => 1, 'base_unit' => 'item', 'status' => 'aktif']);

    app(MaterialGroupService::class)->syncItems($group, [[
        'barang_material_id' => $material->id,
        'material_unit_id' => $pcs->id,
        'quantity' => 60,
    ]]);

    $item = $group->items()->firstOrFail();
    expect($item->quantity)->toBe(60.0)
        ->and($item->material_unit_id)->toBe($pcs->id)
        ->and($item->conversion_to_base)->toBe(120.0)
        ->and($item->quantity_base)->toBe(0.5);
});
