<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_perusahaan_id')->unique()->constrained('cabang_perusahaans')->cascadeOnDelete();
            $table->time('check_in_time')->default('08:00');
            $table->time('check_out_time')->default('17:00');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
            $table->json('work_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('attendance_settings'); }
};
