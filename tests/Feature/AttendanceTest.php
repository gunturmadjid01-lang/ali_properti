<?php

use App\Models\AttendanceSetting;
use App\Models\CabangPerusahaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function attendancePhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('selfie.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
}

function attendanceEmployee(array $overrides = []): User
{
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CBG-ABSEN', 'nama_cabang' => 'Kantor Absensi', 'address' => 'Jl. Uji',
        'phone' => '08123456789', 'latitude' => -5.147665, 'longtitude' => 119.432732,
        'attendance_radius_meters' => 100, 'emaiil' => 'cabang@example.test', 'manager_name' => 'Manager',
        'status' => 'aktif', 'type' => 'cabang', 'record_status' => 'locked',
    ]);

    return User::factory()->create(array_merge([
        'kantor_cabang_id' => $branch->id, 'employee_number' => 'PGW-001',
        'phone' => '081234567890',
        'attendance_pin' => '12345678', 'employment_status' => 'aktif', 'has_login_access' => false,
    ], $overrides));
}

it('allows employee without dashboard access to enter attendance using full name and pin', function () {
    $employee = attendanceEmployee();

    $this->post('/absensi/check', ['name' => $employee->name, 'pin' => '12345678'])
        ->assertRedirect('/absensi')
        ->assertSessionHas('attendance_user_id');
});

it('does not allow a dashboard login to bypass name and attendance pin verification', function () {
    $employee = attendanceEmployee(['has_login_access' => true]);

    $this->actingAs($employee)->get('/absensi')->assertOk()->assertInertia(fn ($page) => $page->component('Attendance/Login'));
    $this->actingAs($employee)->post('/absensi/check', ['name' => $employee->name, 'pin' => '12345678'])
        ->assertRedirect('/absensi')->assertSessionHas('attendance_user_id', $employee->id);
});

it('requires an attendance pin with exactly eight digits', function () {
    $employee = attendanceEmployee();

    $this->post('/absensi/check', ['name' => $employee->name, 'pin' => '123456'])
        ->assertSessionHasErrors('pin');
});

it('rejects attendance outside the configured branch radius', function () {
    Storage::fake('public');
    $employee = attendanceEmployee();

    $this->withSession(['attendance_user_id' => $employee->id])->post('/absensi', [
        'type' => 'check_in', 'latitude' => -5.157665, 'longitude' => 119.442732,
        'accuracy_meters' => 10, 'photo' => attendancePhoto(),
    ])->assertRedirect()->assertSessionHasErrors('outside_radius_confirmed');

    $this->assertDatabaseCount('attendance_records', 0);
});

it('records photo and server-calculated distance inside the radius', function () {
    Storage::fake('public');
    $employee = attendanceEmployee();

    $this->withSession(['attendance_user_id' => $employee->id])->post('/absensi', [
        'type' => 'check_in', 'latitude' => -5.147665, 'longitude' => 119.432732,
        'accuracy_meters' => 8, 'photo' => attendancePhoto(),
    ])->assertRedirect();

    $this->assertDatabaseHas('attendance_records', [
        'user_id' => $employee->id, 'type' => 'check_in', 'record_status' => 'locked',
    ]);
});

it('allows a confirmed attendance outside radius and marks it for admin review', function () {
    Storage::fake('public');
    $employee = attendanceEmployee();

    $this->withSession(['attendance_user_id' => $employee->id])->post('/absensi', [
        'type' => 'check_in', 'latitude' => -5.157665, 'longitude' => 119.442732,
        'accuracy_meters' => 10, 'outside_radius_confirmed' => true, 'photo' => attendancePhoto(),
    ])->assertRedirect();

    $this->assertDatabaseHas('attendance_records', [
        'user_id' => $employee->id, 'is_within_radius' => false, 'outside_radius_confirmed' => true,
    ]);
});

it('protects the admin attendance module with dedicated permissions', function () {
    Storage::fake('public');
    $employee = attendanceEmployee();
    $permission = Permission::findOrCreate('attendance.view', 'web');

    $this->actingAs($employee)->get('/admin/absensi-pegawai')->assertForbidden();
    $employee->givePermissionTo($permission);
    $this->withSession(['attendance_user_id' => $employee->id])->post('/absensi', [
        'type' => 'check_in', 'latitude' => -5.147665, 'longitude' => 119.432732,
        'accuracy_meters' => 8, 'photo' => attendancePhoto(),
    ])->assertRedirect();
    $this->actingAs($employee)->get('/admin/absensi-pegawai')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Attendance/Index')
            ->has('statistics', 6)->has('chart', 7)->has('filterOptions.branches')
            ->has('rows.data', 1)->where('rows.data.0.employee', $employee->name));
});

it('classifies late check in using the configured tolerance', function () {
    Storage::fake('public');
    $employee = attendanceEmployee();
    AttendanceSetting::create([
        'cabang_perusahaan_id' => $employee->kantor_cabang_id,
        'check_in_time' => '08:00', 'check_out_time' => '17:00',
        'late_tolerance_minutes' => 15, 'checkout_tolerance_minutes' => 15,
        'work_days' => [1, 2, 3, 4, 5, 6], 'is_active' => true, 'record_status' => 'locked',
    ]);
    $this->travelTo(now()->setTime(8, 16));

    $this->withSession(['attendance_user_id' => $employee->id])->post('/absensi', [
        'type' => 'check_in', 'latitude' => -5.147665, 'longitude' => 119.432732,
        'accuracy_meters' => 8, 'photo' => attendancePhoto(),
    ])->assertRedirect();

    $this->assertDatabaseHas('attendance_records', ['user_id' => $employee->id, 'time_status' => 'late']);
});
