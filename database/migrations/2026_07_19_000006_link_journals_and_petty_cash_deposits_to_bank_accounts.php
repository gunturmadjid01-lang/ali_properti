<?php

use App\Models\CustomerReceipt;
use App\Models\CustomerRefund;
use App\Models\MaterialPurchase;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table): void {
            $table->foreignId('master_bank_id')->nullable()->after('detail_rumah_id')
                ->constrained('master_banks')->nullOnDelete();
            $table->index(['master_bank_id', 'tanggal']);
        });

        Schema::table('petty_cash_deposits', function (Blueprint $table): void {
            $table->foreignId('master_bank_id')->nullable()->after('petty_cash_account_id')
                ->constrained('master_banks')->nullOnDelete();
        });

        $sources = [
            CustomerReceipt::class => ['customer_receipts', 'master_bank_id'],
            CustomerRefund::class => ['customer_refunds', 'master_bank_id'],
            MaterialPurchase::class => ['material_purchases', 'payment_master_bank_id'],
            TransaksiKeuangan::class => ['transaksi_keuangans', 'master_bank_id'],
        ];

        foreach ($sources as $sourceType => [$table, $column]) {
            DB::table('journals')
                ->where('source_type', $sourceType)
                ->whereNull('master_bank_id')
                ->orderBy('id')
                ->chunkById(200, function ($journals) use ($table, $column): void {
                    $bankIds = DB::table($table)
                        ->whereIn('id', $journals->pluck('source_id')->filter())
                        ->pluck($column, 'id');

                    foreach ($journals as $journal) {
                        $bankId = $bankIds[$journal->source_id] ?? null;
                        if ($bankId) {
                            DB::table('journals')->where('id', $journal->id)->update(['master_bank_id' => $bankId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('petty_cash_deposits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('master_bank_id');
        });
        Schema::table('journals', function (Blueprint $table): void {
            $table->dropIndex(['master_bank_id', 'tanggal']);
            $table->dropConstrainedForeignId('master_bank_id');
        });
    }
};
