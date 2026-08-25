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
        $permissions = collect(MarketingPermissions::operational())
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate('marketing', 'web')->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'marketing')->where('guard_name', 'web')->first();
        if ($role) {
            $role->revokePermissionTo(Permission::query()->where('guard_name', 'web')->whereIn('name', MarketingPermissions::operational())->get());
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
