<?php

use App\Support\MarketingPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $ensure = fn (array $names) => collect($names)->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate('marketing', 'web')->givePermissionTo($ensure(MarketingPermissions::operational()));

        $monitor = $ensure([
            'dashboard.view', 'customer.view', 'customer-follow-up.view', 'marketing-reminder.view',
            'marketing-visit.view', 'marketing-action-plan.view', 'customer-document-checklist.view',
            'marketing-survey.view', 'marketing.activity.view', 'marketing.activity.view-all',
            'marketing.pipeline.view', 'marketing.lead-report.view', 'marketing.owner-report.view',
            'marketing-evaluation.view',
        ]);
        foreach (['owner', 'manager', 'supervisor_marketing'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($monitor);
        }

        Role::findOrCreate('manager', 'web')->givePermissionTo($ensure(['marketing-evaluation.manage']));
        Role::findOrCreate('supervisor_marketing', 'web')->givePermissionTo($ensure(['marketing-evaluation.manage']));

        Role::findOrCreate('admin_sales', 'web')->givePermissionTo($ensure([
            ...MarketingPermissions::operational(),
            'marketing.activity.view-all', 'marketing.lead-distribution.manage',
            'marketing.lead-source.manage', 'marketing.campaign.manage', 'marketing.target-commission.manage',
        ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
