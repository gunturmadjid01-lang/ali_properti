<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::query()->firstOrCreate(['name' => 'receivables.settings', 'guard_name' => 'web']);
        Role::query()->whereIn('name', ['owner', 'manager', 'keuangan', 'admin_keuangan', 'super_admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'receivables.settings')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
