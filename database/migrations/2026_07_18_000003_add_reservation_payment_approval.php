<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->date('payment_submitted_at')->nullable()->after('paid_at');
            $table->string('payment_channel')->nullable()->after('payment_submitted_at');
            $table->string('payment_proof_path')->nullable()->after('payment_submitted_at');
            $table->string('payment_proof_original_name')->nullable()->after('payment_proof_path');
            $table->string('payment_bank_reference')->nullable()->after('payment_proof_original_name');
            $table->string('payment_sender_name')->nullable()->after('payment_bank_reference');
            $table->string('payment_approval_status')->nullable()->after('payment_status')->index();
            $table->string('record_status')->default('draft')->after('payment_approval_status');
            $table->dateTime('locked_at')->nullable()->after('record_status');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
        });

        $roleId = DB::table('roles')->where('name', 'keuangan')->value('id');
        DB::table('approval_settings')->updateOrInsert(
            ['module_key' => 'housing-reservation-payment', 'action' => 'lock'],
            ['module_label' => 'Pembayaran Booking Fee Reservasi', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => json_encode($roleId ? [$roleId] : []), 'approval_steps' => json_encode([['step' => 1, 'role_ids' => $roleId ? [$roleId] : []]]), 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('approval_settings')->where('module_key', 'housing-reservation-payment')->delete();
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn(['payment_submitted_at', 'payment_channel', 'payment_proof_path', 'payment_proof_original_name', 'payment_bank_reference', 'payment_sender_name', 'payment_approval_status', 'record_status', 'locked_at']);
        });
    }
};
