<?php

use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('management user create uses a dedicated form page', function () {
    $admin = User::factory()->create(['phone' => '081200000001']);
    JobPosition::create([
        'name' => 'Manager Keuangan',
        'normalized_name' => 'manager keuangan',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/management/user/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/User/FormPage')
            ->where('method', 'post')
            ->where('actionUrl', '/admin/management/user')
            ->where('initialData.has_login_access', true)
            ->where('initialData.employment_type', 'tetap')
            ->where('options.job_positions.0.label', 'Manager Keuangan'));
});

test('management user edit uses a dedicated form page with existing assignments', function () {
    $admin = User::factory()->create(['phone' => '081200000002']);
    $employee = User::factory()->create([
        'phone' => '081200000003',
        'job_title' => 'Pengawas Lapangan',
        'join_date' => '2026-01-15',
        'employment_type' => 'kontrak',
        'employment_status' => 'aktif',
        'has_login_access' => false,
    ]);

    $this->actingAs($admin)
        ->get("/admin/management/user/{$employee->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/User/FormPage')
            ->where('method', 'put')
            ->where('initialData.id', $employee->id)
            ->where('initialData.job_title', 'Pengawas Lapangan')
            ->where('initialData.join_date', '2026-01-15')
            ->where('initialData.has_login_access', false));
});

test('management user index exposes page navigation urls instead of modal data flow', function () {
    $admin = User::factory()->create(['phone' => '081200000004']);

    $this->actingAs($admin)
        ->get('/admin/management/user')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/User/Index')
            ->where('createUrl', '/admin/management/user/create')
            ->where('rows.data.0.edit_url', "/admin/management/user/{$admin->id}/edit"));
});
