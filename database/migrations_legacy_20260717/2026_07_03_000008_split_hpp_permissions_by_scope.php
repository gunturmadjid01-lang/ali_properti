<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $actions = ['view', 'create', 'update', 'delete'];

        foreach (['rab-perumahan', 'rab-unit'] as $module) {
            foreach ($actions as $action) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => "{$module}.{$action}", 'guard_name' => 'web'],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        foreach ($actions as $action) {
            $oldId = DB::table('permissions')->where('name', "hpp.{$action}")->where('guard_name', 'web')->value('id');

            if (! $oldId) {
                continue;
            }

            foreach (['rab-perumahan', 'rab-unit'] as $module) {
                $newId = DB::table('permissions')->where('name', "{$module}.{$action}")->where('guard_name', 'web')->value('id');
                $assignments = DB::table('role_has_permissions')->where('permission_id', $oldId)->get();

                foreach ($assignments as $assignment) {
                    DB::table('role_has_permissions')->updateOrInsert([
                        'permission_id' => $newId,
                        'role_id' => $assignment->role_id,
                    ]);
                }

                $directAssignments = DB::table('model_has_permissions')->where('permission_id', $oldId)->get();

                foreach ($directAssignments as $assignment) {
                    DB::table('model_has_permissions')->updateOrInsert([
                        'permission_id' => $newId,
                        'model_type' => $assignment->model_type,
                        'model_id' => $assignment->model_id,
                    ]);
                }
            }
        }

        DB::table('permissions')->where('name', 'like', 'hpp.%')->delete();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'like', 'rab-perumahan.%')
            ->orWhere('name', 'like', 'rab-unit.%')
            ->delete();
    }
};
