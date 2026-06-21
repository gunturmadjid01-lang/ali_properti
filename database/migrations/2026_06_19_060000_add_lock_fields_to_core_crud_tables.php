<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'roles',
            'gudangs',
            'barang_materials',
            'material_price_histories',
            'kontraktors',
            'costumers',
            'costumer_follow_ups',
            'sprs',
            'kpr_submissions',
            'material_requests',
            'material_purchases',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'record_status')) {
                    $blueprint->string('record_status')->default('draft');
                }

                if (! Schema::hasColumn($table, 'locked_at')) {
                    $blueprint->timestamp('locked_at')->nullable();
                }

                if (! Schema::hasColumn($table, 'locked_by')) {
                    $blueprint->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'roles',
            'gudangs',
            'barang_materials',
            'material_price_histories',
            'kontraktors',
            'costumers',
            'costumer_follow_ups',
            'sprs',
            'kpr_submissions',
            'material_requests',
            'material_purchases',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'locked_by')) {
                    $blueprint->dropConstrainedForeignId('locked_by');
                }

                if (Schema::hasColumn($table, 'locked_at')) {
                    $blueprint->dropColumn('locked_at');
                }

                if (Schema::hasColumn($table, 'record_status')) {
                    $blueprint->dropColumn('record_status');
                }
            });
        }
    }
};
