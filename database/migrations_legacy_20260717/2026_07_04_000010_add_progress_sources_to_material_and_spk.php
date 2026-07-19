<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            if (! Schema::hasColumn('progress_pembangunans', 'source_type')) {
                $table->string('source_type')->nullable()->after('approved_note');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->string('source_label')->nullable()->after('source_id');
                $table->index(['source_type', 'source_id']);
            }
        });

        Schema::table('material_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_requests', 'site_schedule_id')) {
                $table->foreignId('site_schedule_id')->nullable()->after('tahapan_pembangunan_id')->constrained('site_schedules')->nullOnDelete();
            }
            if (! Schema::hasColumn('material_requests', 'progress_diakui')) {
                $table->decimal('progress_diakui', 5, 2)->default(0)->after('site_schedule_id');
            }
            if (! Schema::hasColumn('material_requests', 'progress_pembangunan_id')) {
                $table->foreignId('progress_pembangunan_id')->nullable()->after('progress_diakui')->constrained('progress_pembangunans')->nullOnDelete();
            }
        });

        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktor_payments', 'tahapan_pembangunan_id')) {
                $table->foreignId('tahapan_pembangunan_id')->nullable()->after('contractor_opname_id')->constrained('tahapan_pembangunans')->nullOnDelete();
            }
            if (! Schema::hasColumn('spk_kontraktor_payments', 'site_schedule_id')) {
                $table->foreignId('site_schedule_id')->nullable()->after('tahapan_pembangunan_id')->constrained('site_schedules')->nullOnDelete();
            }
            if (! Schema::hasColumn('spk_kontraktor_payments', 'progress_diakui')) {
                $table->decimal('progress_diakui', 5, 2)->default(0)->after('site_schedule_id');
            }
            if (! Schema::hasColumn('spk_kontraktor_payments', 'progress_pembangunan_id')) {
                $table->foreignId('progress_pembangunan_id')->nullable()->after('progress_diakui')->constrained('progress_pembangunans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            foreach (['progress_pembangunan_id', 'site_schedule_id', 'tahapan_pembangunan_id'] as $column) {
                if (Schema::hasColumn('spk_kontraktor_payments', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            if (Schema::hasColumn('spk_kontraktor_payments', 'progress_diakui')) {
                $table->dropColumn('progress_diakui');
            }
        });

        Schema::table('material_requests', function (Blueprint $table): void {
            foreach (['progress_pembangunan_id', 'site_schedule_id'] as $column) {
                if (Schema::hasColumn('material_requests', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            if (Schema::hasColumn('material_requests', 'progress_diakui')) {
                $table->dropColumn('progress_diakui');
            }
        });

        Schema::table('progress_pembangunans', function (Blueprint $table): void {
            if (Schema::hasColumn('progress_pembangunans', 'source_type')) {
                $table->dropIndex(['source_type', 'source_id']);
                $table->dropColumn(['source_type', 'source_id', 'source_label']);
            }
        });
    }
};
