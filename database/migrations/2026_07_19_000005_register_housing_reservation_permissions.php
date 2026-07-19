<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $actions = ['view', 'create', 'update', 'delete', 'lock', 'print'];

        foreach ($actions as $action) {
            DB::table('permissions')->insertOrIgnore([
                'name' => "housing-reservation.{$action}",
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sources = [
            'view' => ['booking.view', 'booking.manage'],
            'create' => ['booking.create', 'booking.manage'],
            'update' => ['booking.update', 'booking.manage'],
            'delete' => ['booking.delete', 'booking.manage'],
            'lock' => ['booking.update', 'booking.manage'],
            'print' => ['booking.view', 'booking.manage'],
        ];

        foreach ($sources as $action => $sourceNames) {
            $targetId = DB::table('permissions')->where('name', "housing-reservation.{$action}")->value('id');
            $sourceIds = DB::table('permissions')->whereIn('name', $sourceNames)->pluck('id');
            $roleIds = DB::table('role_has_permissions')->whereIn('permission_id', $sourceIds)->pluck('role_id')->unique();

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('name', 'like', 'housing-reservation.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
