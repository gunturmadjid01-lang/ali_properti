<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(['payroll.view', 'payroll.manage'])->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        $role = Role::query()->where('name', 'keuangan')->where('guard_name', 'web')->first();
        $role?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'keuangan')->where('guard_name', 'web')->first();
        $role?->revokePermissionTo(['payroll.view', 'payroll.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
