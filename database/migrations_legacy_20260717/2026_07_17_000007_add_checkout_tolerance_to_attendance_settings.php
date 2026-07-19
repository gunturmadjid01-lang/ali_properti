<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('attendance_settings', fn (Blueprint $table) => $table->unsignedSmallInteger('checkout_tolerance_minutes')->default(15)->after('late_tolerance_minutes')); }
    public function down(): void { Schema::table('attendance_settings', fn (Blueprint $table) => $table->dropColumn('checkout_tolerance_minutes')); }
};
