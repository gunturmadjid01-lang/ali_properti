<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('marketing-calendar.view', 'web');
        foreach (['marketing', 'area_marketing', 'admin_sales', 'manager', 'owner', 'supervisor_marketing', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
