<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabang_perusahaans', function (Blueprint $table) {
            $table->unsignedInteger('attendance_radius_meters')->default(100)->after('longtitude');
        });
    }

    public function down(): void
    {
        Schema::table('cabang_perusahaans', fn (Blueprint $table) => $table->dropColumn('attendance_radius_meters'));
    }
};
