<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $modules = ['bank-credit-master', 'bank-branch', 'bank-credit-product', 'bank-housing-partnership', 'bank-document-requirement'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect($this->modules)->flatMap(fn (string $module) => collect(['view', 'create', 'update', 'delete'])->map(fn (string $action) => Permission::findOrCreate("{$module}.{$action}", 'web')));
        $permissions->push(Permission::findOrCreate('bank-partnership-history.view', 'web'));
        Role::query()->whereIn('name', ['owner', 'manager', 'super_admin'])->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = collect($this->modules)->flatMap(fn (string $module) => collect(['view', 'create', 'update', 'delete'])->map(fn (string $action) => "{$module}.{$action}"))->push('bank-partnership-history.view');
        Permission::query()->whereIn('name', $names)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
