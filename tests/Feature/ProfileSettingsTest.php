<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('profile settings require authentication', function () {
    $this->get('/admin/profile')->assertRedirect('/login');
    $this->put('/admin/profile', [])->assertRedirect('/login');
    $this->put('/admin/profile/password', [])->assertRedirect('/login');
});

test('authenticated petugas can update own profile and avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create([
        'name' => 'Petugas Lama',
        'email' => 'petugas.lama@example.test',
        'phone' => '081111111111',
        'job_title' => 'Staf Administrasi',
        'join_date' => '2025-01-01',
    ]);

    $this->actingAs($user)
        ->get('/admin/profile')
        ->assertOk();

    $this->post('/admin/profile', [
        '_method' => 'put',
        'name' => 'Petugas Baru',
        'email' => 'petugas.baru@example.test',
        'phone' => '082222222222',
        'employee_number' => 'PEG-001',
        'job_title' => 'Pengawas Lapangan',
        'join_date' => '2026-01-15',
        'tax_number' => 'NPWP-001',
        'payroll_bank_name' => 'Bank Sulselbar',
        'payroll_bank_account' => '1234567890',
        'payroll_bank_holder' => 'Petugas Baru',
        'avatar' => UploadedFile::fake()->createWithContent(
            'avatar.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        ),
    ])->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('Petugas Baru')
        ->and($user->email)->toBe('petugas.baru@example.test')
        ->and($user->phone)->toBe('082222222222')
        ->and($user->employee_number)->toBe('PEG-001')
        ->and($user->job_title)->toBe('Staf Administrasi')
        ->and($user->join_date->format('Y-m-d'))->toBe('2025-01-01')
        ->and($user->payroll_bank_account)->toBe('1234567890')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->avatar)->not->toBeNull();

    Storage::disk('public')->assertExists($user->avatar);
});

test('profile email must remain unique', function () {
    $user = User::factory()->create(['phone' => '081111111111']);
    $other = User::factory()->create(['phone' => '082222222222']);

    $this->actingAs($user)
        ->from('/admin/profile')
        ->put('/admin/profile', [
            'name' => $user->name,
            'email' => $other->email,
            'phone' => $user->phone,
        ])
        ->assertRedirect('/admin/profile')
        ->assertSessionHasErrors('email');
});

test('petugas can change password using the current password', function () {
    $user = User::factory()->create([
        'phone' => '081111111111',
        'password' => 'Password123',
    ]);

    $this->actingAs($user)
        ->put('/admin/profile/password', [
            'current_password' => 'Password123',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
        ->assertRedirect();

    expect(Hash::check('PasswordBaru123', $user->fresh()->password))->toBeTrue();
});

test('wrong current password cannot change password', function () {
    $user = User::factory()->create([
        'phone' => '081111111111',
        'password' => 'Password123',
    ]);

    $this->actingAs($user)
        ->from('/admin/profile')
        ->put('/admin/profile/password', [
            'current_password' => 'PasswordSalah123',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
        ->assertRedirect('/admin/profile')
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('Password123', $user->fresh()->password))->toBeTrue();
});
