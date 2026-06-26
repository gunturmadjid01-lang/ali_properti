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

        $permissions = collect([
            'spk-payment.view',
            'spk-payment.create',
            'spk-payment.update',
        ])->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->whereIn('name', ['keuangan', 'owner', 'super_admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['spk-payment.view', 'spk-payment.create', 'spk-payment.update'])
            ->get()
            ->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
