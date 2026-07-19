<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            foreach (['kpr_milestone_documents', 'kpr_milestones', 'spr_billing_schedules', 'spr_payments'] as $table) {
                Schema::dropIfExists($table);
            }
            DB::table('approval_settings')->whereIn('module_key', ['spr-payment', 'kpr-milestone'])->delete();
            DB::table('approval_requests')->whereIn('module_key', ['spr-payment', 'kpr-milestone'])->delete();
            $ids = DB::table('permissions')->where(fn ($q) => $q->where('name', 'like', 'spr-payment.%')->orWhere('name', 'like', 'refund-spr.%')->orWhere('name', 'like', 'kpr-akad.%')->orWhere('name', 'like', 'handover-customer.%'))->pluck('id');
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    { /* Modul legacy sengaja tidak dipulihkan. */
    }
};
