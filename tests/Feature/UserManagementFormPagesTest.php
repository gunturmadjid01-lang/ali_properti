<?php

use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

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
    $admin->assignRole(Role::findOrCreate('marketing', 'web'));

    $this->actingAs($admin)
        ->get('/admin/management/user')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/User/Index')
            ->where('section', 'marketing')
            ->where('createUrl', '/admin/management/user/create?section=marketing')
            ->has('tabs', 11)
            ->has('statistics', 5)
            ->where('rows.data.0.edit_url', "/admin/management/user/{$admin->id}/edit?section=marketing"));
});

test('panel role dan pegawai memakai daftar statistik serta form yang terpisah', function () {
    $admin = User::factory()->create(['phone' => '081200000005']);
    $marketing = User::factory()->create(['phone' => '081200000006', 'has_login_access' => true]);
    $marketing->assignRole(Role::findOrCreate('marketing', 'web'));
    User::factory()->create(['phone' => '081200000007', 'employee_number' => 'PEG-007', 'has_login_access' => false, 'email' => null, 'password' => null]);

    $this->actingAs($admin)
        ->get('/admin/management/user?section=marketing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'marketing')
            ->where('statistics.1.label', 'Marketing')
            ->where('rows.data.0.id', $marketing->id));

    $this->get('/admin/management/user?section=pegawai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'pegawai')
            ->where('statistics.0.label', 'Total Pegawai')
            ->where('rows.data.0.employee_number', 'PEG-007'));

    $this->get('/admin/management/user/create?section=gudang')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'gudang')
            ->where('initialData.profile_section', 'gudang')
            ->where('fields', fn ($fields) => collect($fields)->pluck('name')->contains('gudang_id')
                && ! collect($fields)->pluck('name')->contains('gudang_ids')
                && collect($fields)->pluck('name')->contains('create_employee_profile')
                && collect($fields)->pluck('name')->contains('employee_number')));

    $this->get('/admin/management/user/create?section=pegawai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'pegawai')
            ->where('initialData.profile_section', 'pegawai')
            ->where('fields', fn ($fields) => collect($fields)->pluck('name')->contains('employee_number')
                && ! collect($fields)->pluck('name')->contains('email')));
});

test('pembuatan akun mengunci role dan pembuatan pegawai tidak membuat akses login', function () {
    $admin = User::factory()->create(['phone' => '081200000008']);
    $this->actingAs($admin)->post('/admin/management/user', [
        'profile_section' => 'manager',
        'name' => 'Manager Baru',
        'phone' => '081211110001',
        'email' => 'manager.baru@example.test',
        'password' => 'password123',
    ])->assertRedirect('/admin/management/user?section=manager');

    $manager = User::query()->where('email', 'manager.baru@example.test')->firstOrFail();
    expect($manager->has_login_access)->toBeTrue()
        ->and($manager->hasRole('manager'))->toBeTrue()
        ->and($manager->employee_number)->toBeNull();

    $this->post('/admin/management/user', [
        'profile_section' => 'pegawai',
        'kantor_cabang_id' => null,
        'employee_number' => 'PEG-SEPARATE-001',
        'attendance_pin' => '12345678',
        'name' => 'Pegawai Baru',
        'job_title' => 'Staf Administrasi',
        'join_date' => '2026-07-17',
        'employment_type' => 'tetap',
        'employment_status' => 'aktif',
        'phone' => '081211110002',
    ])->assertRedirect('/admin/management/user?section=pegawai');

    $employee = User::query()->where('employee_number', 'PEG-SEPARATE-001')->firstOrFail();
    expect($employee->has_login_access)->toBeFalse()
        ->and($employee->email)->toBeNull()
        ->and($employee->password)->toBeNull()
        ->and($employee->roles)->toHaveCount(0)
        ->and($employee->jobPosition?->name)->toBe('Staf Administrasi')
        ->and(JobPosition::query()->where('normalized_name', 'staf administrasi')->count())->toBe(1);
});

test('setiap tab role memiliki form tanpa pilihan role dan menetapkan role otomatis', function () {
    $admin=User::factory()->create(['phone'=>'081200000099']);
    $this->actingAs($admin)->get('/admin/management/user/create?section=keuangan')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('section','keuangan')->where('fields',fn($fields)=>!collect($fields)->pluck('name')->contains('role_ids')));
    $this->post('/admin/management/user',['profile_section'=>'keuangan','name'=>'Keuangan Otomatis','phone'=>'081299999999','email'=>'keuangan.otomatis@example.test','password'=>'password123','perumahan_ids'=>[]])->assertRedirect('/admin/management/user?section=keuangan')->assertSessionHasNoErrors();
    expect(User::where('email','keuangan.otomatis@example.test')->firstOrFail()->hasRole('keuangan'))->toBeTrue();
});

test('akun role dapat sekaligus dibuat sebagai data pegawai', function () {
    $admin = User::factory()->create(['phone' => '081200000109']);
    $this->actingAs($admin)->post('/admin/management/user', [
        'profile_section' => 'manager', 'create_employee_profile' => true,
        'name' => 'Manager Pegawai', 'phone' => '081299999998',
        'email' => 'manager.pegawai@example.test', 'password' => 'password123',
        'employee_number' => 'MGR-PEG-001', 'attendance_pin' => '87654321',
        'job_title' => 'Manager Operasional', 'join_date' => '2026-07-17',
        'employment_type' => 'tetap', 'employment_status' => 'aktif', 'perumahan_ids' => [],
    ])->assertRedirect('/admin/management/user?section=manager')->assertSessionHasNoErrors();

    $user = User::where('email', 'manager.pegawai@example.test')->firstOrFail();
    expect($user->hasRole('manager'))->toBeTrue()
        ->and($user->employee_number)->toBe('MGR-PEG-001')
        ->and($user->has_login_access)->toBeTrue();
});
