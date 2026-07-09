<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_schedules', 'batch_code')) {
                $table->string('batch_code')->nullable()->after('kode_jadwal')->index();
            }
        });

        Schema::create('site_schedule_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_schedule_id')->constrained('site_schedules')->cascadeOnDelete();
            $table->unsignedInteger('periode_ke');
            $table->string('label_periode')->nullable();
            $table->decimal('bobot_persen', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['site_schedule_id', 'periode_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_schedule_allocations');

        Schema::table('site_schedules', function (Blueprint $table): void {
            if (Schema::hasColumn('site_schedules', 'batch_code')) {
                $table->dropColumn('batch_code');
            }
        });
    }
};
