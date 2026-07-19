<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_accounts', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            $table->index(['assigned_user_id', 'status']);
        });

        DB::table('petty_cash_accounts')
            ->whereNull('assigned_user_id')
            ->whereNotNull('created_by')
            ->update(['assigned_user_id' => DB::raw('created_by')]);
    }

    public function down(): void
    {
        Schema::table('petty_cash_accounts', function (Blueprint $table) {
            $table->dropIndex(['assigned_user_id', 'status']);
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};
