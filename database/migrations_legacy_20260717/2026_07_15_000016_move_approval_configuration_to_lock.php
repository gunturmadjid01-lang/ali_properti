<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $existingLockModules = DB::table('approval_settings')
                ->where('action', 'lock')
                ->pluck('module_key');

            if ($existingLockModules->isNotEmpty()) {
                DB::table('approval_settings')
                    ->where('action', 'create')
                    ->whereIn('module_key', $existingLockModules)
                    ->delete();
            }

            DB::table('approval_settings')
                ->where('action', 'create')
                ->update(['action' => 'lock']);

            DB::table('approval_requests')
                ->where('action', 'create')
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejection_note' => 'Ditutup saat migrasi approval berbasis finalisasi/lock.',
                    'reviewed_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        DB::table('approval_settings')->where('action', 'lock')->update(['action' => 'create']);
    }
};
