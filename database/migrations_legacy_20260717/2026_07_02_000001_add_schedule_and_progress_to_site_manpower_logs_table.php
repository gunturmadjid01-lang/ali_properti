<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_manpower_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_manpower_logs', 'tahapan_pembangunan_id')) {
                $table->foreignId('tahapan_pembangunan_id')->nullable()->after('detail_rumah_id')->constrained('tahapan_pembangunans')->nullOnDelete();
            }

            if (! Schema::hasColumn('site_manpower_logs', 'site_schedule_id')) {
                $table->foreignId('site_schedule_id')->nullable()->after('tahapan_pembangunan_id')->constrained('site_schedules')->nullOnDelete();
            }

            if (! Schema::hasColumn('site_manpower_logs', 'progress_pembangunan_id')) {
                $table->foreignId('progress_pembangunan_id')->nullable()->after('site_schedule_id')->constrained('progress_pembangunans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_manpower_logs', function (Blueprint $table): void {
            foreach (['progress_pembangunan_id', 'site_schedule_id', 'tahapan_pembangunan_id'] as $column) {
                if (Schema::hasColumn('site_manpower_logs', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
