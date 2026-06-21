<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            $table->foreignId('planned_master_bank_id')
                ->nullable()
                ->after('metode_pembayaran')
                ->constrained('master_banks')
                ->nullOnDelete();
            $table->foreignId('payment_master_bank_id')
                ->nullable()
                ->after('planned_master_bank_id')
                ->constrained('master_banks')
                ->nullOnDelete();
        });

        $defaultBankId = DB::table('master_banks')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($defaultBankId) {
            DB::table('material_purchases')
                ->whereNull('planned_master_bank_id')
                ->update(['planned_master_bank_id' => $defaultBankId]);

            DB::table('material_purchases')
                ->whereNotNull('fund_released_at')
                ->whereNull('payment_master_bank_id')
                ->update(['payment_master_bank_id' => $defaultBankId]);
        }
    }

    public function down(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_master_bank_id');
            $table->dropConstrainedForeignId('planned_master_bank_id');
        });
    }
};
