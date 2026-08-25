<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'admin-sales.dashboard.view', 'admin-sales.monitoring.view', 'admin-sales.lead.view', 'admin-sales.lead.create', 'admin-sales.lead.verify', 'admin-sales.lead.assign',
        'admin-sales.follow-up.review', 'admin-sales.visit.review', 'admin-sales.work-item.view',
        'admin-sales.work-item.create', 'admin-sales.work-item.update', 'admin-sales.report.view', 'admin-sales.report.export', 'marketing.lead-assignment.view', 'marketing.lead-assignment.respond',
    ];

    public function up(): void
    {
        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        Role::findOrCreate('admin_sales', 'web')->givePermissionTo($permissions);
        foreach (['marketing', 'area_marketing'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permissions->whereIn('name', ['marketing.lead-assignment.view', 'marketing.lead-assignment.respond']));
        }
        foreach (['manager', 'owner'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permissions->whereIn('name', ['admin-sales.dashboard.view', 'admin-sales.monitoring.view', 'admin-sales.work-item.view', 'admin-sales.report.view', 'admin-sales.report.export']));
        }
        Role::findOrCreate('super_admin', 'web')->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['admin_sales', 'marketing', 'area_marketing', 'manager', 'owner', 'super_admin'] as $role) {
            Role::findByName($role, 'web')->revokePermissionTo(self::PERMISSIONS);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
