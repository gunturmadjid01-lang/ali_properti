<?php

use App\Models\Tukang;
use App\Models\TukangGaji;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::findOrCreate("tukang.{$action}");
    }

    $role = Role::findOrCreate('admin');
    $role->givePermissionTo([
        'tukang.view',
        'tukang.create',
        'tukang.update',
        'tukang.delete',
    ]);

    $this->admin = User::factory()->create(['phone' => '081234567890']);
    $this->admin->assignRole($role);
});

test('admin dapat melihat dan menambahkan tukang', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.tukang.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tukang/Index')
            ->has('positions', 4));

    $this->actingAs($this->admin)
        ->post(route('admin.tukang.store'), [
            'nama' => 'Budi Santoso',
            'alamat' => 'Jl. Merdeka No. 10',
            'posisi' => 'tukang',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tukangs', [
        'nama' => 'Budi Santoso',
        'posisi' => 'tukang',
        'gaji' => 0,
        'created_by' => $this->admin->id,
    ]);
});

test('admin dapat memperbarui dan menghapus tukang', function () {
    $tukang = Tukang::query()->create([
        'nama' => 'Joko',
        'alamat' => 'Alamat lama',
        'posisi' => 'kenek',
        'gaji' => 100000,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.tukang.update', $tukang), [
            'nama' => 'Joko Prasetyo',
            'alamat' => 'Alamat baru',
            'posisi' => 'kepala_tukang',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tukangs', [
        'id' => $tukang->id,
        'nama' => 'Joko Prasetyo',
        'posisi' => 'kepala_tukang',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.tukang.destroy', $tukang))
        ->assertRedirect();

    $this->assertSoftDeleted('tukangs', ['id' => $tukang->id]);
});

test('posisi tukang harus berasal dari dropdown yang tersedia', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.tukang.store'), [
            'nama' => 'Tukang Uji',
            'alamat' => 'Alamat uji',
            'posisi' => 'posisi_tidak_valid',
        ])
        ->assertSessionHasErrors('posisi');

    $this->assertDatabaseCount('tukangs', 0);
});

test('gaji aktif terbaru menjadi referensi dan menonaktifkan gaji sebelumnya', function () {
    $tukang = Tukang::query()->create([
        'nama' => 'Tukang Bergaji',
        'alamat' => 'Alamat tukang',
        'posisi' => 'tukang',
        'gaji' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.tukang.gaji.store', $tukang), [
            'nominal' => 150000,
            'tanggal_berlaku' => '2026-07-01',
            'status' => 'aktif',
        ])
        ->assertRedirect();

    $gajiLama = TukangGaji::query()->where('tukang_id', $tukang->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.tukang.gaji.store', $tukang), [
            'nominal' => 175000,
            'tanggal_berlaku' => '2026-08-01',
            'status' => 'aktif',
        ])
        ->assertRedirect();

    expect($gajiLama->fresh()->status)->toBe('nonaktif');
    expect($tukang->fresh()->gajiAktif->nominal)->toBe('175000.00');

    $this->actingAs($this->admin)
        ->get(route('admin.tukang.gaji.index', $tukang))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tukang/Gaji')
            ->has('gajis', 2)
            ->where('gajis.0.status', 'aktif'));
});

test('daftar tukang tersedia pada matriks role permission', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.management.role-permission.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Management/RolePermission/Index')
            ->where('options.permissionMatrix.0.modules.10.key', 'tukang')
            ->where('options.permissionMatrix.0.modules.10.label', 'Daftar Tukang')
            ->has('options.permissionMatrix.0.modules.10.permissions', 4));
});
