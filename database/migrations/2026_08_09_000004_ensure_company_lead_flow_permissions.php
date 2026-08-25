<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $admin = collect(['admin-sales.lead.view', 'admin-sales.lead.create', 'admin-sales.lead.assign'])->map(fn ($name) => Permission::findOrCreate($name, 'web'));
        $marketing = collect(['marketing.lead-assignment.view', 'marketing.lead-assignment.respond'])->map(fn ($name) => Permission::findOrCreate($name, 'web'));
        Role::findOrCreate('admin_sales', 'web')->givePermissionTo($admin);
        foreach (['marketing', 'area_marketing'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($marketing);
        }
        Role::findOrCreate('super_admin', 'web')->givePermissionTo([...$admin, ...$marketing]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
