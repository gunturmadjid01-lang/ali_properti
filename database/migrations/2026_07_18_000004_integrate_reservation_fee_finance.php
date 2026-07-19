<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE payment_schedules MODIFY sales_transaction_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE customer_receipts MODIFY sales_transaction_id BIGINT UNSIGNED NULL');
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->foreignId('housing_reservation_id')->nullable()->after('sales_transaction_id')->constrained('housing_reservations')->nullOnDelete();
        });
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->foreignId('housing_reservation_id')->nullable()->after('sales_transaction_id')->constrained('housing_reservations')->nullOnDelete();
        });
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->string('fund_custody_status')->default('unpaid')->after('payment_approval_status')->index();
            $table->string('fund_destination_type')->nullable()->after('fund_custody_status');
            $table->foreignId('fund_master_bank_id')->nullable()->after('fund_destination_type')->constrained('master_banks')->nullOnDelete();
            $table->dateTime('fund_received_at')->nullable()->after('fund_master_bank_id');
            $table->foreignId('fund_received_by')->nullable()->after('fund_received_at')->constrained('users')->nullOnDelete();
            $table->string('settlement_proof_path')->nullable()->after('fund_received_by');
            $table->string('settlement_proof_original_name')->nullable()->after('settlement_proof_path');
            $table->text('finance_verification_notes')->nullable()->after('settlement_proof_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fund_master_bank_id');
            $table->dropConstrainedForeignId('fund_received_by');
            $table->dropColumn(['fund_custody_status', 'fund_destination_type', 'fund_received_at', 'settlement_proof_path', 'settlement_proof_original_name', 'finance_verification_notes']);
        });
        Schema::table('customer_receipts', fn (Blueprint $table) => $table->dropConstrainedForeignId('housing_reservation_id'));
        Schema::table('payment_schedules', fn (Blueprint $table) => $table->dropConstrainedForeignId('housing_reservation_id'));
        DB::statement('ALTER TABLE customer_receipts MODIFY sales_transaction_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE payment_schedules MODIFY sales_transaction_id BIGINT UNSIGNED NOT NULL');
    }
};
