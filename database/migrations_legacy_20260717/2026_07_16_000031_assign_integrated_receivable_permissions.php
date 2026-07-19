<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tabs = Permission::query()->where('name', 'like', 'sales.transaction-detail.%.view')->get();
        Role::permission('sales.transaction-detail.view')->get()->each(fn (Role $role) => $role->givePermissionTo($tabs));
        Role::query()->whereIn('name', ['keuangan', 'admin_keuangan'])->get()->each(fn (Role $role) => $role->givePermissionTo(Permission::whereIn('name', ['receivables.view', 'receivables.print', 'customer-receipts.view', 'customer-receipts.create', 'customer-receipts.update', 'customer-receipts.lock', 'customer-receipts.unlock', 'customer-receipts.print'])->get()));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
