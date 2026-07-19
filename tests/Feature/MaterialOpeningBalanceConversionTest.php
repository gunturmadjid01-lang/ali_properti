<?php

use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialOpeningBalance;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Models\StokMaterial;
use App\Models\User;
use App\Services\MaterialUnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('saldo awal menerima satuan konversi dan menyimpan stok level satu', function () {
    $permissions = collect(['view', 'create', 'update', 'delete', 'unlock'])->map(fn ($action) => Permission::findOrCreate("material-opening-balance.{$action}", 'web'));
    $role = Role::findOrCreate('user_area_gudang', 'web');
    $role->givePermissionTo($permissions);
    $gudang = Gudang::create(['kode_gudang' => 'GDG-SA', 'nama_gudang' => 'Gudang Saldo Awal', 'status' => 'aktif']);
    $user = User::factory()->create(['phone' => '081200000011', 'gudang_id' => $gudang->id]);
    $user->assignRole($role);
    $user->gudangs()->attach($gudang->id);

    $type = MaterialType::create(['code' => 'JNS-SA', 'name' => 'Saldo Awal Test', 'status' => 'aktif']);
    $dus = MaterialUnit::create(['code' => 'STN-SA-D', 'name' => 'Dus Saldo', 'symbol' => 'DUS-SA', 'status' => 'aktif']);
    $pak = MaterialUnit::create(['code' => 'STN-SA-P', 'name' => 'Pak Saldo', 'symbol' => 'PAK-SA', 'status' => 'aktif']);
    $material = BarangMaterial::create(['nama_barang' => 'Baut Saldo Awal', 'material_type_id' => $type->id, 'base_unit_id' => $dus->id, 'jenis_material' => $type->name, 'kategori_material' => $type->name, 'satuan' => $dus->symbol, 'harga_hpp' => 120000, 'status' => 'aktif']);
    app(MaterialUnitConversionService::class)->sync($material, [['unit_id' => $pak->id, 'factor' => 12]]);

    $this->actingAs($user)->post('/admin/saldo-awal-material/sync', [
        'gudang_id' => $gudang->id,
        'tanggal_saldo' => '2026-07-15',
        'items' => [['barang_material_id' => $material->id, 'material_unit_id' => $pak->id, 'qty' => 12, 'harga_satuan' => 10000, 'catatan' => 'Input 12 pak']],
    ])->assertRedirect();

    $balance = MaterialOpeningBalance::firstOrFail();
    expect($balance->input_qty)->toBe(12.0)
        ->and($balance->input_unit_id)->toBe($pak->id)
        ->and($balance->input_unit_symbol)->toBe('PAK-SA')
        ->and($balance->qty)->toBe(1.0)
        ->and($balance->harga_satuan)->toBe(120000.0)
        ->and((float) StokMaterial::where('gudang_id', $gudang->id)->where('barang_material_id', $material->id)->value('qty'))->toBe(1.0);
});

test('halaman dan endpoint saldo awal menolak user tanpa permission', function () {
    $user = User::factory()->create(['phone' => '081200000012']);
    $this->actingAs($user)->get('/admin/saldo-awal-material')->assertForbidden();
    $this->actingAs($user)->post('/admin/saldo-awal-material/sync', [])->assertForbidden();
});
