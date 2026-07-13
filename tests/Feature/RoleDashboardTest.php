<?php

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
