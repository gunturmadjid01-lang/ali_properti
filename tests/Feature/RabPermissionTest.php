<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('permission rab tampil terpisah dan membuka halaman sesuai scope', function () {
    $role = Role::findOrCreate('editor_rab', 'web');
    $role->givePermissionTo([
        Permission::findByName('rab-perumahan.view'),
        Permission::findByName('rab-unit.view'),
    ]);
    $user = User::factory()->create(['phone' => '081234567813']);
    $user->assignRole($role);
    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-RAB',
        'nama_cabang' => 'Cabang RAB',
        'address' => 'Alamat',
        'phone' => '081234567814',
        'emaiil' => 'rab@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-RAB',
        'nama_perusahaan' => 'Perumahan RAB',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '1',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);

    $this->actingAs($user)
        ->get(route('admin.management.role-permission.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('options.permissionMatrix.2.modules.0.key', 'rab-perumahan')
            ->where('options.permissionMatrix.2.modules.0.label', 'Management RAB Perumahan')
            ->has('options.permissionMatrix.2.modules.0.permissions', 5)
            ->where('options.permissionMatrix.2.modules.0.permissions.4.name', 'rab-perumahan.manage')
            ->where('options.permissionMatrix.2.modules.1.key', 'rab-unit')
            ->where('options.permissionMatrix.2.modules.1.label', 'Management RAB Unit Rumah')
            ->has('options.permissionMatrix.2.modules.1.permissions', 5)
            ->where('options.permissionMatrix.2.modules.1.permissions.4.name', 'rab-unit.manage'));

    $this->actingAs($user)
        ->get(route('admin.management.perumahan.hpp.detail', $perumahan))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('admin.unit-rumah.hpp.detail', $unit))
        ->assertOk();
});
