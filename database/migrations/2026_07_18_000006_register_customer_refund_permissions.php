<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['view', 'update', 'lock', 'unlock', 'print'] as $action) {
            DB::table('permissions')->insertOrIgnore(['name' => "customer-refunds.{$action}", 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $roleIds = DB::table('roles')->whereIn('name', ['admin', 'manager', 'keuangan'])->pluck('id');
        $permissionIds = DB::table('permissions')->where('name', 'like', 'customer-refunds.%')->pluck('id');
        foreach ($roleIds as $roleId) foreach ($permissionIds as $permissionId) DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);

        $financeRoleId = DB::table('roles')->where('name', 'keuangan')->value('id');
        DB::table('approval_settings')->insertOrIgnore(['module_key' => 'customer-refund', 'module_label' => 'Refund Booking Fee & Uang Muka', 'action' => 'lock', 'requires_approval' => (bool) $financeRoleId, 'approval_stages' => $financeRoleId ? 1 : 0, 'approver_role_ids' => json_encode($financeRoleId ? [$financeRoleId] : []), 'approval_steps' => json_encode($financeRoleId ? [['step' => 1, 'role_ids' => [$financeRoleId]]] : []), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', 'like', 'customer-refunds.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        DB::table('approval_settings')->where('module_key', 'customer-refund')->delete();
    }
};
