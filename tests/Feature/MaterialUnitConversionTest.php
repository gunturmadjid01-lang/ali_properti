<?php

use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\StokMaterial;
use App\Models\TransaksiLogistik;
use App\Services\LogistikService;
use App\Services\MaterialUnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('pembelian berbagai satuan dinormalisasi ke stok level satu', function () {
    $type = MaterialType::create(['code' => 'JNS-T', 'name' => 'Test', 'status' => 'aktif']);
    $dus = MaterialUnit::create(['code' => 'STN-D', 'name' => 'Dus Test', 'symbol' => 'DUS-T', 'status' => 'aktif']);
    $pak = MaterialUnit::create(['code' => 'STN-P', 'name' => 'Pak Test', 'symbol' => 'PAK-T', 'status' => 'aktif']);
    $pcs = MaterialUnit::create(['code' => 'STN-C', 'name' => 'Pcs Test', 'symbol' => 'PCS-T', 'status' => 'aktif']);
    $material = BarangMaterial::create([
        'kode_barang' => 'MAT-CONV', 'nama_barang' => 'Material Konversi',
        'material_type_id' => $type->id, 'base_unit_id' => $dus->id,
        'jenis_material' => $type->name, 'kategori_material' => $type->name,
        'satuan' => $dus->symbol, 'harga_hpp' => 100000, 'status' => 'aktif',
    ]);
    app(MaterialUnitConversionService::class)->sync($material, [
        ['unit_id' => $pak->id, 'factor' => 12],
        ['unit_id' => $pcs->id, 'factor' => 2],
    ]);
    $gudang = Gudang::create(['kode_gudang' => 'GDG-CONV', 'nama_gudang' => 'Gudang Konversi', 'status' => 'aktif']);

    $transaction = app(LogistikService::class)->simpanTransaksi([
        'tanggal' => now()->toDateString(), 'jenis' => TransaksiLogistik::JENIS_MASUK,
        'gudang_id' => $gudang->id, 'items' => [
            ['barang_material_id' => $material->id, 'qty' => 1, 'material_unit_id' => $pak->id, 'harga_satuan' => 8333.33],
            ['barang_material_id' => $material->id, 'qty' => 2, 'material_unit_id' => $pcs->id, 'harga_satuan' => 4166.67],
        ],
    ]);

    expect(round((float) StokMaterial::where('barang_material_id', $material->id)->value('qty'), 6))->toBe(0.166667)
        ->and($transaction->details()->count())->toBe(2)
        ->and((float) $transaction->details()->first()->input_qty)->toBe(1.0)
        ->and(round((float) $transaction->details()->first()->qty, 6))->toBe(0.083333);
});
