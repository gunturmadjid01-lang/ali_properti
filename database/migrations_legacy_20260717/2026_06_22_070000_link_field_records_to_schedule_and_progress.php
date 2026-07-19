<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            if (! Schema::hasColumn('progress_pembangunans', 'site_schedule_id')) {
                $table->foreignId('site_schedule_id')->nullable()->after('tahapan_pembangunan_id')->constrained('site_schedules')->nullOnDelete();
            }
        });

        Schema::table('site_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_reports', 'site_schedule_id')) {
                $table->foreignId('site_schedule_id')->nullable()->after('tahapan_pembangunan_id')->constrained('site_schedules')->nullOnDelete();
            }
            if (! Schema::hasColumn('site_reports', 'progress_pembangunan_id')) {
                $table->foreignId('progress_pembangunan_id')->nullable()->after('site_schedule_id')->constrained('progress_pembangunans')->nullOnDelete();
            }
        });

        Schema::table('quality_inspections', function (Blueprint $table): void {
            if (! Schema::hasColumn('quality_inspections', 'site_schedule_id')) {
                $table->foreignId('site_schedule_id')->nullable()->after('tahapan_pembangunan_id')->constrained('site_schedules')->nullOnDelete();
            }
            if (! Schema::hasColumn('quality_inspections', 'progress_pembangunan_id')) {
                $table->foreignId('progress_pembangunan_id')->nullable()->after('site_schedule_id')->constrained('progress_pembangunans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quality_inspections', function (Blueprint $table): void {
            foreach (['progress_pembangunan_id', 'site_schedule_id'] as $column) {
                if (Schema::hasColumn('quality_inspections', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('site_reports', function (Blueprint $table): void {
            foreach (['progress_pembangunan_id', 'site_schedule_id'] as $column) {
                if (Schema::hasColumn('site_reports', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            if (Schema::hasColumn('progress_pembangunans', 'site_schedule_id')) {
                $table->dropConstrainedForeignId('site_schedule_id');
            }
        });
    }
};
