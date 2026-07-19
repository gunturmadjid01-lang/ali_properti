<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['bank_branches', 'bank_credit_products', 'bank_housing_partnerships', 'cash_installment_schemes', 'developer_kpr_products'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('record_status')->default('draft')->index();
                $blueprint->timestamp('locked_at')->nullable();
                $blueprint->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            });
            DB::table($table)->where('status', '!=', 'draft')->update(['record_status' => 'locked', 'locked_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('locked_by');
                $blueprint->dropColumn(['record_status', 'locked_at']);
            });
        }
    }
};
