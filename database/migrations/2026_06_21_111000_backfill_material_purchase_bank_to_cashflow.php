<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $purchases = DB::table('material_purchases')
            ->whereNotNull('payment_master_bank_id')
            ->get(['kode_pembelian', 'payment_master_bank_id']);

        foreach ($purchases as $purchase) {
            DB::table('transaksi_keuangans')
                ->whereNull('master_bank_id')
                ->where('keterangan', 'like', "%{$purchase->kode_pembelian}%")
                ->update(['master_bank_id' => $purchase->payment_master_bank_id]);
        }
    }

    public function down(): void
    {
        // Historical cashflow account references are intentionally retained.
    }
};
