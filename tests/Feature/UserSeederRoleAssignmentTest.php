<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('akun manager dan manajer pimpro memakai role yang berbeda', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $manager = User::query()->where('email', 'manager@ptali.com')->firstOrFail();
    $pimpro = User::query()->where('email', 'pimpro@ptali.com')->firstOrFail();

    expect($manager->hasRole('manager'))->toBeTrue()
        ->and($manager->hasRole('manajer_pimpro'))->toBeFalse()
        ->and($manager->getAllPermissions())->not->toBeEmpty()
        ->and($pimpro->hasRole('manajer_pimpro'))->toBeTrue()
        ->and($pimpro->hasRole('manager'))->toBeFalse();
});
