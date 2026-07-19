<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rab-perumahan', 'rab-unit'] as $module) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "{$module}.manage", 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()],
            );

            $updateId = DB::table('permissions')
                ->where('name', "{$module}.update")
                ->where('guard_name', 'web')
                ->value('id');
            $manageId = DB::table('permissions')
                ->where('name', "{$module}.manage")
                ->where('guard_name', 'web')
                ->value('id');

            if (! $updateId || ! $manageId) {
                continue;
            }

            DB::table('role_has_permissions')
                ->where('permission_id', $updateId)
                ->get(['role_id'])
                ->each(function ($row) use ($manageId): void {
                    DB::table('role_has_permissions')->updateOrInsert([
                        'permission_id' => $manageId,
                        'role_id' => $row->role_id,
                    ]);
                });

            DB::table('model_has_permissions')
                ->where('permission_id', $updateId)
                ->get(['model_type', 'model_id'])
                ->each(function ($row) use ($manageId): void {
                    DB::table('model_has_permissions')->updateOrInsert([
                        'permission_id' => $manageId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ]);
                });
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', ['rab-perumahan.manage', 'rab-unit.manage'])
            ->delete();
    }
};
