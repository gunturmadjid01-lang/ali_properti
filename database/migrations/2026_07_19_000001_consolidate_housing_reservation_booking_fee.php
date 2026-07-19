<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->foreignId('petty_cash_account_id')->nullable()->after('fund_master_bank_id')->constrained('petty_cash_accounts')->nullOnDelete();
            $table->text('payment_notes')->nullable()->after('payment_sender_name');
        });

        $financeRoleId = DB::table('roles')->whereRaw('LOWER(name) = ?', ['keuangan'])->value('id');
        $roleIds = $financeRoleId ? [(int) $financeRoleId] : [];

        DB::table('approval_settings')->updateOrInsert(
            ['module_key' => 'housing-reservation', 'action' => 'lock'],
            [
                'module_label' => 'Reservasi & Penerimaan Booking Fee',
                'requires_approval' => true,
                'approval_stages' => 1,
                'approver_role_ids' => json_encode($roleIds),
                'approval_steps' => json_encode([['step' => 1, 'role_ids' => $roleIds]]),
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('approval_settings')->where('module_key', 'housing-reservation-payment')->delete();
    }

    public function down(): void
    {
        Schema::table('housing_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('petty_cash_account_id');
            $table->dropColumn('payment_notes');
        });
    }
};
