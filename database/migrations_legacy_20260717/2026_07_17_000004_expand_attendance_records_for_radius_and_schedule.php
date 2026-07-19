<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('is_within_radius')->default(true)->after('distance_meters');
            $table->boolean('outside_radius_confirmed')->default(false)->after('is_within_radius');
            $table->string('time_status')->default('on_time')->after('type');
            $table->integer('schedule_difference_minutes')->nullable()->after('time_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn([
            'is_within_radius', 'outside_radius_confirmed', 'time_status', 'schedule_difference_minutes',
        ]));
    }
};
