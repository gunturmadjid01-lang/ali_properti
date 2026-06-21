<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('progress_pembangunans')
            ->whereNull('created_by')
            ->update(['created_by' => DB::raw('users_id')]);

        DB::table('progress_pembangunans')
            ->whereNull('updated_by')
            ->update(['updated_by' => DB::raw('COALESCE(created_by, users_id)')]);

        $tables = [
            'material_requests',
            'material_purchases',
            'site_reports',
            'quality_inspections',
            'site_schedules',
            'material_usages',
            'material_returns',
        ];

        foreach ($tables as $table) {
            DB::table($table)
                ->whereNull('updated_by')
                ->whereNotNull('created_by')
                ->update(['updated_by' => DB::raw('created_by')]);
        }
    }

    public function down(): void
    {
        // Audit backfill is historical data and must not be erased.
    }
};
