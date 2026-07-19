<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_schedules', 'spk_kontraktor_id')) {
                $table->foreignId('spk_kontraktor_id')
                    ->nullable()
                    ->after('detail_rumah_id')
                    ->constrained('spk_kontraktors')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('site_schedules', 'spk_kontraktor_id')) {
                $table->dropConstrainedForeignId('spk_kontraktor_id');
            }
        });
    }
};
