<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $modules = ['material-type', 'material-brand', 'material-unit'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect($this->modules)->flatMap(fn ($module) => collect(['view', 'create', 'update', 'delete'])->map(fn ($action) => Permission::findOrCreate("{$module}.{$action}", 'web')));
        Role::query()->where('name', 'user_area_gudang')->first()?->givePermissionTo($permissions);
        Role::query()->where('name', 'super_admin')->first()?->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', collect($this->modules)->flatMap(fn ($module) => collect(['view', 'create', 'update', 'delete'])->map(fn ($action) => "{$module}.{$action}")))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
