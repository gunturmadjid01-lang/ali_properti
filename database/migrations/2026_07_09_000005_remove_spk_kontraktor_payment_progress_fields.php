<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            foreach (['progress_pembangunan_id', 'site_schedule_id', 'tahapan_pembangunan_id', 'spk_kontraktor_item_id'] as $column) {
                if (Schema::hasColumn('spk_kontraktor_payments', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['pekerjaan', 'progress_diakui'] as $column) {
                if (Schema::hasColumn('spk_kontraktor_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_kontraktor_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktor_payments', 'spk_kontraktor_item_id')) {
                $table->foreignId('spk_kontraktor_item_id')->nullable()->after('contractor_opname_id')->constrained('spk_kontraktor_items')->nullOnDelete();
            }

            if (! Schema::hasColumn('spk_kontraktor_payments', 'pekerjaan')) {
                $table->string('pekerjaan')->nullable()->after('spk_kontraktor_item_id');
            }

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
};
