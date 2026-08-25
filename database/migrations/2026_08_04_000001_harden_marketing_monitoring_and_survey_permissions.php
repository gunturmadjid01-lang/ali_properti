<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $surveyUnlock = Permission::findOrCreate('marketing-survey.unlock', 'web');
        $monitorAll = Permission::findOrCreate('marketing.activity.view-all', 'web');
        $cashOperational = collect(['cash-sale.view', 'cash-sale.create', 'cash-sale.update', 'cash-sale.lock'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
        $cashUnlock = Permission::findOrCreate('cash-sale.unlock', 'web');

        foreach (['marketing', 'area_marketing'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->givePermissionTo($cashOperational);
            $role->revokePermissionTo([$surveyUnlock, $monitorAll, $cashUnlock]);
        }

        foreach (['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo([$surveyUnlock, $monitorAll, $cashUnlock]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
