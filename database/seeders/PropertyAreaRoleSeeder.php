<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PropertyAreaRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'owner',
            'manajer_pimpro',
            'admin_keuangan',
            'admin_konsumen',
            'user_area_gudang',
            'pengawas',
            'bag_legal',
            'supervisor_marketing',
            'teknik',
            'admin_kpr',
            'area_marketing',
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
