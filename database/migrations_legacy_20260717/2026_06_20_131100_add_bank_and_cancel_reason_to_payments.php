<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_keuangans', 'master_bank_id')) {
                $table->foreignId('master_bank_id')->nullable()->after('cabang_id')->constrained('master_banks')->nullOnDelete();
            }
        });

        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'alasan_batal')) {
                $table->string('alasan_batal')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (Schema::hasColumn('sprs', 'alasan_batal')) {
                $table->dropColumn('alasan_batal');
            }
        });

        Schema::table('transaksi_keuangans', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_keuangans', 'master_bank_id')) {
                $table->dropConstrainedForeignId('master_bank_id');
            }
        });
    }
};
