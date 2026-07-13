<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('management master modules use dedicated create form pages', function (string $url) {
    $user = User::factory()->create(['phone' => fake()->unique()->numerify('0812########')]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/Components/SeparatedManagementFormPage')
            ->where('method', 'post'));
})->with([
    '/admin/management/cabang-perusahaan/create',
    '/admin/management/dokumen-legalitas/create',
    '/admin/management/master-dokumen-customer/create',
    '/admin/management/master-bank/create',
    '/admin/management/tipe-post/create',
]);

test('employee salary uses a dedicated create form page', function () {
    Role::findOrCreate('owner', 'web');
    $owner = User::factory()->create(['phone' => '081299991111']);
    $owner->assignRole('owner');

    $this->actingAs($owner)
        ->get('/admin/gaji-pegawai/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmployeeSalary/FormPage')
            ->where('method', 'post'));
});

test('akad and handover have distinct form pages and urls', function (string $type, string $title) {
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create(['phone' => fake()->unique()->numerify('0821########')]);
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get("/admin/kpr/proses/{$type}/create")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Kpr/Milestone/FormPage')
            ->where('type', $type)
            ->where('title', "Tambah {$title}"));
})->with([
    ['akad', 'Akad KPR'],
    ['serah_terima', 'Serah Terima Unit'],
]);

test('refund spr sidebar url is available and no longer returns 404', function () {
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create(['phone' => '081299992222']);
    $user->assignRole('super_admin');

    $this->actingAs($user)
        ->get('/admin/refund-spr')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketing/SprPayment/Refund'));
});
