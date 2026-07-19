<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $financeRoleId = DB::table('roles')->whereRaw('LOWER(name) = ?', ['keuangan'])->value('id');

        if (! $financeRoleId) {
            $financeRoleId = DB::table('roles')->insertGetId([
                'name' => 'keuangan',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('approval_settings')->updateOrInsert(
            ['module_key' => 'housing-reservation', 'action' => 'lock'],
            [
                'module_label' => 'Reservasi & Penerimaan Booking Fee',
                'requires_approval' => true,
                'approval_stages' => 1,
                'approver_role_ids' => json_encode([(int) $financeRoleId]),
                'approval_steps' => json_encode([['step' => 1, 'role_ids' => [(int) $financeRoleId]]]),
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('approval_settings')->where('module_key', 'housing-reservation-payment')->delete();
    }

    public function down(): void
    {
        // Konfigurasi approver valid dipertahankan saat rollback.
    }
};
