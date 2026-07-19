<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_receipts', fn (Blueprint $table) => $table->string('receipt_purpose')->default('invoice_payment')->after('payment_method'));
        $permissions = Permission::query()->whereIn('name', ['customer-receipts.view', 'customer-receipts.create', 'customer-receipts.lock', 'customer-receipts.print', 'receivables.view'])->get();
        Role::query()->whereIn('name', ['marketing', 'area_marketing', 'admin_marketing'])->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('customer_receipts', fn (Blueprint $table) => $table->dropColumn('receipt_purpose'));
    }
};
