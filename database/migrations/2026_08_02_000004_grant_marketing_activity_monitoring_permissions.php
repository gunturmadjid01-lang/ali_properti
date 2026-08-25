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
        $operational = collect(MarketingPermissions::operational())->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
        Role::findOrCreate('marketing', 'web')->givePermissionTo($operational);

        $monitor = Permission::findOrCreate('marketing.activity.view', 'web');
        $monitorAll = Permission::findOrCreate('marketing.activity.view-all', 'web');
        foreach (['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo([$monitor, $monitorAll]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->revokePermissionTo(['marketing.activity.view', 'marketing.activity.view-all']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
