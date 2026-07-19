<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('spk_kontraktor_payments')
            ->where('status', 'menunggu_pencairan_owner')
            ->update(['status' => 'menunggu_pembayaran_keuangan']);
    }

    public function down(): void
    {
        DB::table('spk_kontraktor_payments')
            ->where('status', 'menunggu_pembayaran_keuangan')
            ->update(['status' => 'menunggu_pencairan_owner']);
    }
};
