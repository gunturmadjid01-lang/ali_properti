<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('dashboard dapat dibuka semua role meskipun belum memiliki permission modul', function () {
    $user = User::factory()->create(['phone'=>'081277700001']);
    $user->assignRole(Role::findOrCreate('petugas_minimal', 'web'));

    $this->actingAs($user)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('sections', 0)
            ->has('charts', 0));
});

test('isi dashboard mengikuti permission modul bukan nama role', function () {
    $role = Role::findOrCreate('role_custom_lapangan', 'web');
    $role->givePermissionTo([
        Permission::findOrCreate('detail-rumah.view', 'web'),
        Permission::findOrCreate('company-inventory.view', 'web'),
    ]);
    $user = User::factory()->create(['phone'=>'081277700002']);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('sections', 2)
            ->where('sections.0.key', 'property')
            ->where('sections.1.key', 'assets')
            ->where('sections', fn ($sections) => collect($sections)->doesntContain(fn ($section) => $section['key'] === 'marketing')));
});

test('filter dashboard mendukung hari bulan dan tahun dengan titik chart yang sesuai', function () {
    $role = Role::findOrCreate('role_progress_dashboard', 'web');
    $role->givePermissionTo(Permission::findOrCreate('progress.view', 'web'));
    $user = User::factory()->create(['phone'=>'081277700003']);
    $user->assignRole($role);

    foreach ([['day','2026-07-14',7],['month','2026-07',5],['year','2026',12]] as [$period,$value,$points]) {
        $this->actingAs($user)->get(route('admin.dashboard', ['period'=>$period,'value'=>$value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('filters.period', $period)
                ->where('filters.value', $value)
                ->has('charts.0.labels', $points)
                ->has('charts.1.labels', $points));
    }
});

test('dashboard non owner mengikuti perumahan aktif sedangkan owner tetap konsolidasi', function () {
    $branch = CabangPerusahaan::create([
        'kode_cabang' => 'DASH',
        'nama_cabang' => 'Cabang Dashboard',
        'address' => '-',
        'phone' => '-',
        'emaiil' => 'dashboard@test.local',
        'manager_name' => 'Manager',
        'status' => 'aktif',
        'record_status' => 'locked',
    ]);
    $housingA = Perumahan::create([
        'cabang_id' => $branch->id,
        'nama_perusahaan' => 'Dashboard A',
        'alamat' => '-',
        'luas_lahan' => 1000,
        'jumlah_unit' => 1,
        'tanggal_mulai' => '2026-01-01',
        'status' => 'aktif',
        'record_status' => 'locked',
    ]);
    $housingB = Perumahan::create([
        'cabang_id' => $branch->id,
        'nama_perusahaan' => 'Dashboard B',
        'alamat' => '-',
        'luas_lahan' => 1000,
        'jumlah_unit' => 1,
        'tanggal_mulai' => '2026-01-01',
        'status' => 'aktif',
        'record_status' => 'locked',
    ]);
    foreach ([$housingA, $housingB] as $index => $housing) {
        DetailRumah::create([
            'perumahan_id' => $housing->id,
            'kode_nlok' => chr(65 + $index),
            'nomor_rumah' => '01',
            'tipe_rumah' => '36',
            'luas_tanah' => 72,
            'status' => 'aktif',
            'status_penjualan' => 'tersedia',
            'record_status' => 'locked',
        ]);
    }

    $propertyRole = Role::findOrCreate('role_property_scoped', 'web');
    $propertyRole->givePermissionTo(Permission::findOrCreate('detail-rumah.view', 'web'));
    $scopedUser = User::factory()->create(['phone' => '081277700004']);
    $scopedUser->assignRole($propertyRole);
    $scopedUser->perumahans()->attach([$housingA->id, $housingB->id]);

    $this->actingAs($scopedUser)
        ->withSession(['active_perumahan_id' => $housingA->id])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('context.active_perumahan_id', $housingA->id)
            ->where('sections.0.stats.1.value', 1));

    $owner = User::factory()->create(['phone' => '081277700005']);
    $owner->assignRole(Role::findOrCreate('owner', 'web'));
    $owner->givePermissionTo(Permission::findOrCreate('detail-rumah.view', 'web'));

    $this->actingAs($owner)
        ->withSession(['active_perumahan_id' => $housingA->id])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSessionMissing('active_perumahan_id')
        ->assertInertia(fn (Assert $page) => $page
            ->where('context.active_perumahan_id', null)
            ->where('sections', fn ($sections) => collect($sections)
                ->firstWhere('key', 'property')['stats'][1]['value'] === 2)
            ->where('auth.active_perumahan', null));
});

test('ringkasan keuangan diprioritaskan untuk role eksekutif', function () {
    foreach (['owner', 'manager', 'admin', 'super_admin', 'keuangan'] as $index => $roleName) {
        $user = User::factory()->create(['phone' => '081277701'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)]);
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.0.key', 'finance')
                ->where('charts.0.title', fn (string $title) => str_contains($title, 'Cash In vs Cash Out')));
    }
});
