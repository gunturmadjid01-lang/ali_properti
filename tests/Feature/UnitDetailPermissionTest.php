<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function unitDetailFixture(): array
{
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-DETAIL-UNIT', 'nama_cabang' => 'Cabang Detail Unit',
        'address' => '-', 'phone' => '-', 'emaiil' => 'unit-detail@example.test',
        'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Permission',
        'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1,
        'tanggal_mulai' => '2026-07-01', 'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $project->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01',
        'harga_jual' => 750000000, 'progress_terakhir' => 35,
        'status_penjualan' => 'tersedia', 'status_pembangunan' => 'proses',
    ]);

    return [$project, $unit];
}

test('unit detail requires base unit permission', function () {
    [$project, $unit] = unitDetailFixture();
    $user = User::factory()->create(['phone' => '081288880001']);

    $this->actingAs($user)
        ->get(route('admin.management.perumahan.rumah.detail', [$project->id, $unit->id]))
        ->assertForbidden();
});

test('unit detail does not expose section data without its permission', function () {
    [$project, $unit] = unitDetailFixture();
    $user = User::factory()->create(['phone' => '081288880002']);
    $user->givePermissionTo(Permission::findOrCreate('detail-rumah.view', 'web'));

    $this->actingAs($user)
        ->get(route('admin.management.perumahan.rumah.detail', [$project->id, $unit->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/Perumahan/UnitDetail')
            ->where('rumah.harga_jual', null)
            ->where('rumah.progress_terakhir', null)
            ->where('rumah.pemilik', null)
            ->where('visibility.price', false)
            ->where('visibility.progress', false)
            ->where('visibility.sales', false)
            ->where('visibility.spk', false)
            ->has('progressRows', 0)
            ->has('salesRows', 0)
            ->has('spkRows', 0));
});

test('unit detail enables only the sections explicitly permitted', function () {
    [$project, $unit] = unitDetailFixture();
    $user = User::factory()->create(['phone' => '081288880003']);
    $user->givePermissionTo(collect(['detail-rumah.view', 'progress.view', 'spk-kontraktor.view'])
        ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    $this->actingAs($user)
        ->get(route('admin.management.perumahan.rumah.detail', [$project->id, $unit->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('visibility.progress', true)
            ->where('visibility.spk', true)
            ->where('visibility.price', false)
            ->where('visibility.sales', false)
            ->where('rumah.progress_terakhir', 35)
            ->where('rumah.harga_jual', null));
});
