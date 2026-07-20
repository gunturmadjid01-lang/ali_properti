<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $table) {
            $table->foreignId('petty_cash_account_id')->nullable()->after('master_bank_id')->constrained('petty_cash_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_receipts', fn (Blueprint $table) => $table->dropConstrainedForeignId('petty_cash_account_id'));
    }
};
