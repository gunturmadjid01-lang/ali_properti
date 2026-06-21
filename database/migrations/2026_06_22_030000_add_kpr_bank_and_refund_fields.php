<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_kredits', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_kredits', 'bunga_tahunan')) {
                $table->decimal('bunga_tahunan', 5, 2)->default(7.50)->after('email_pic');
            }
            if (! Schema::hasColumn('bank_kredits', 'tenor_min_bulan')) {
                $table->unsignedSmallInteger('tenor_min_bulan')->default(60)->after('bunga_tahunan');
            }
            if (! Schema::hasColumn('bank_kredits', 'tenor_max_bulan')) {
                $table->unsignedSmallInteger('tenor_max_bulan')->default(240)->after('tenor_min_bulan');
            }
            if (! Schema::hasColumn('bank_kredits', 'minimal_dp_persen')) {
                $table->decimal('minimal_dp_persen', 5, 2)->default(10)->after('tenor_max_bulan');
            }
            if (! Schema::hasColumn('bank_kredits', 'biaya_provisi_persen')) {
                $table->decimal('biaya_provisi_persen', 5, 2)->default(1)->after('minimal_dp_persen');
            }
            if (! Schema::hasColumn('bank_kredits', 'biaya_admin')) {
                $table->decimal('biaya_admin', 18, 2)->default(0)->after('biaya_provisi_persen');
            }
        });

        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'bank_kredit_id')) {
                $table->foreignId('bank_kredit_id')->nullable()->after('metode_pembayaran')->constrained('bank_kredits')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'kpr_tenor_bulan')) {
                $table->unsignedSmallInteger('kpr_tenor_bulan')->nullable()->after('bank_kredit_id');
            }
            if (! Schema::hasColumn('sprs', 'kpr_bunga_tahunan')) {
                $table->decimal('kpr_bunga_tahunan', 5, 2)->nullable()->after('kpr_tenor_bulan');
            }
            if (! Schema::hasColumn('sprs', 'refund_master_bank_id')) {
                $table->foreignId('refund_master_bank_id')->nullable()->after('alasan_batal')->constrained('master_banks')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'refund_transaksi_keuangan_id')) {
                $table->foreignId('refund_transaksi_keuangan_id')->nullable()->after('refund_master_bank_id')->constrained('transaksi_keuangans')->nullOnDelete();
            }
            if (! Schema::hasColumn('sprs', 'refund_amount')) {
                $table->decimal('refund_amount', 18, 2)->default(0)->after('refund_transaksi_keuangan_id');
            }
            if (! Schema::hasColumn('sprs', 'refund_at')) {
                $table->date('refund_at')->nullable()->after('refund_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            foreach (['refund_at', 'refund_amount'] as $column) {
                if (Schema::hasColumn('sprs', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('sprs', 'refund_transaksi_keuangan_id')) {
                $table->dropConstrainedForeignId('refund_transaksi_keuangan_id');
            }
            if (Schema::hasColumn('sprs', 'refund_master_bank_id')) {
                $table->dropConstrainedForeignId('refund_master_bank_id');
            }
            if (Schema::hasColumn('sprs', 'kpr_bunga_tahunan')) {
                $table->dropColumn('kpr_bunga_tahunan');
            }
            if (Schema::hasColumn('sprs', 'kpr_tenor_bulan')) {
                $table->dropColumn('kpr_tenor_bulan');
            }
            if (Schema::hasColumn('sprs', 'bank_kredit_id')) {
                $table->dropConstrainedForeignId('bank_kredit_id');
            }
        });

        Schema::table('bank_kredits', function (Blueprint $table) {
            foreach (['biaya_admin', 'biaya_provisi_persen', 'minimal_dp_persen', 'tenor_max_bulan', 'tenor_min_bulan', 'bunga_tahunan'] as $column) {
                if (Schema::hasColumn('bank_kredits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
