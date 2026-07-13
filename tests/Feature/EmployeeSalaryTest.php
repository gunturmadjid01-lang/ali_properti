<?php

use App\Models\EmployeeSalary;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('owner and manager can open employee salary settings', function (string $roleName) {
    Role::findOrCreate($roleName, 'web');
    $user = User::factory()->create(['phone' => '081111111111']);
    $user->assignRole($roleName);

    $this->actingAs($user)
        ->get('/admin/gaji-pegawai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmployeeSalary/Index')
            ->has('employees'));
})->with(['owner', 'manager']);

test('other roles cannot manage employee salaries', function () {
    Role::findOrCreate('petugas', 'web');
    $user = User::factory()->create(['phone' => '081111111112']);
    $user->assignRole('petugas');

    $this->actingAs($user)->get('/admin/gaji-pegawai')->assertForbidden();
});

test('salary raise is scheduled and closes the previous active period', function () {
    Role::findOrCreate('owner', 'web');
    $owner = User::factory()->create(['phone' => '081111111113']);
    $owner->assignRole('owner');
    $employee = User::factory()->create([
        'phone' => '081111111114',
        'job_title' => 'Site Manager',
        'join_date' => '2025-01-01',
    ]);

    $this->actingAs($owner)->post('/admin/gaji-pegawai', [
        'user_id' => $employee->id,
        'basic_salary' => 5000000,
        'fixed_allowance' => 500000,
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'notes' => 'Gaji awal',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->post('/admin/gaji-pegawai', [
        'user_id' => $employee->id,
        'basic_salary' => 6000000,
        'fixed_allowance' => 750000,
        'effective_from' => '2026-08-01',
        'is_active' => true,
        'notes' => 'Kenaikan gaji',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $oldSalary = EmployeeSalary::query()->whereDate('effective_from', '2026-01-01')->firstOrFail();
    $newSalary = EmployeeSalary::query()->whereDate('effective_from', '2026-08-01')->firstOrFail();

    expect($oldSalary->effective_until->format('Y-m-d'))->toBe('2026-07-31')
        ->and($newSalary->effective_until)->toBeNull()
        ->and((float) $newSalary->basic_salary)->toBe(6000000.0);

    $this->patch('/admin/gaji-pegawai/'.$newSalary->id.'/status', [
        'is_active' => false,
    ])->assertRedirect();

    expect($newSalary->fresh()->is_active)->toBeFalse()
        ->and($oldSalary->fresh()->effective_until)->toBeNull();
});

test('employee can be created without login credentials', function () {
    $admin = User::factory()->create(['phone' => '081111111115']);

    $this->actingAs($admin)->post('/admin/management/user', [
        'name' => 'Pegawai Tanpa Login',
        'phone' => '081234567890',
        'employee_number' => 'PEG-NOLOGIN',
        'job_title' => 'Staf Lapangan',
        'join_date' => '2026-07-13',
        'employment_type' => 'kontrak',
        'employment_status' => 'aktif',
        'has_login_access' => false,
        'role_ids' => [],
        'perumahan_ids' => [],
        'gudang_ids' => [],
    ])->assertRedirect();

    $employee = User::query()->where('employee_number', 'PEG-NOLOGIN')->firstOrFail();
    expect($employee->email)->toBeNull()
        ->and($employee->password)->toBeNull()
        ->and($employee->has_login_access)->toBeFalse()
        ->and($employee->jobPosition?->name)->toBe('Staf Lapangan')
        ->and(JobPosition::query()->where('normalized_name', 'staf lapangan')->count())->toBe(1);

    $this->post('/admin/management/user', [
        'name' => 'Pegawai Jabatan Sama',
        'phone' => '081234567891',
        'employee_number' => 'PEG-NOLOGIN-2',
        'job_title' => '  staf lapangan  ',
        'join_date' => '2026-07-14',
        'employment_type' => 'kontrak',
        'employment_status' => 'aktif',
        'has_login_access' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(JobPosition::query()->where('normalized_name', 'staf lapangan')->count())->toBe(1)
        ->and(User::query()->where('employee_number', 'PEG-NOLOGIN-2')->firstOrFail()->job_title)->toBe('Staf Lapangan');
});

test('disabled login access rejects otherwise valid credentials', function () {
    $employee = User::factory()->create([
        'phone' => '081111111116',
        'email' => 'disabled@example.test',
        'password' => Hash::make('Password123'),
        'has_login_access' => false,
    ]);

    $this->post('/login', [
        'email' => $employee->email,
        'password' => 'Password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
