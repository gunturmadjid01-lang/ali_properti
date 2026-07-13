<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            if (! Schema::hasColumn('progress_pembangunans', 'schedule_stage_key')) {
                $table->string('schedule_stage_key')->nullable()->after('site_schedule_id');
            }

            if (! Schema::hasColumn('progress_pembangunans', 'schedule_stage_name')) {
                $table->string('schedule_stage_name')->nullable()->after('schedule_stage_key');
            }

            if (! Schema::hasColumn('progress_pembangunans', 'schedule_stage_target')) {
                $table->decimal('schedule_stage_target', 8, 2)->default(0)->after('schedule_stage_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            foreach (['schedule_stage_target', 'schedule_stage_name', 'schedule_stage_key'] as $column) {
                if (Schema::hasColumn('progress_pembangunans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
