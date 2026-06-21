<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sprs', 'costumer_follow_ups'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'record_status')) {
                    $blueprint->string('record_status')->default('draft')->after('status');
                }

                if (! Schema::hasColumn($table, 'locked_at')) {
                    $blueprint->timestamp('locked_at')->nullable()->after('record_status');
                }

                if (! Schema::hasColumn($table, 'locked_by')) {
                    $blueprint->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['costumer_follow_ups', 'sprs'] as $table) {
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
