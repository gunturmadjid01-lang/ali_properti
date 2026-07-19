<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status')->index();
            $table->string('process_stage')->nullable()->after('payment_status');
            $table->dateTime('completed_at')->nullable()->after('cancelled_at');
            $table->string('cancellation_type')->nullable()->after('cancelled_by');
        });

        DB::table('housing_reservations')->where('status', 'pending_payment')->update(['status' => 'active', 'payment_status' => 'unpaid']);
        DB::table('housing_reservations')->where('status', 'paid')->update(['status' => 'active', 'payment_status' => 'paid']);
        DB::table('housing_reservations')->where('status', 'converted')->update(['status' => 'spr_created', 'payment_status' => DB::raw("CASE WHEN paid_amount >= booking_fee THEN 'paid' ELSE 'unpaid' END")]);
        DB::table('housing_reservations')->where('status', 'cancelled')->update(['status' => 'cancelled', 'cancellation_type' => 'internal']);
        DB::table('housing_reservations')->where('status', 'expired')->update(['payment_status' => 'unpaid', 'cancellation_type' => 'automatic']);
    }

    public function down(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'process_stage', 'completed_at', 'cancellation_type']);
        });
    }
};
