<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'tanggal_pembayaran_booking_fee')) {
                $table->date('tanggal_pembayaran_booking_fee')->nullable()->after('booking_fee_includes_dp');
            }

            if (! Schema::hasColumn('sprs', 'tanggal_jatuh_tempo_dp')) {
                $table->date('tanggal_jatuh_tempo_dp')->nullable()->after('uang_muka_jumlah_pembayaran');
            }

            if (! Schema::hasColumn('sprs', 'tanggal_jatuh_tempo_angsuran')) {
                $table->date('tanggal_jatuh_tempo_angsuran')->nullable()->after('tanggal_jatuh_tempo_dp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            foreach ([
                'tanggal_jatuh_tempo_angsuran',
                'tanggal_jatuh_tempo_dp',
                'tanggal_pembayaran_booking_fee',
            ] as $column) {
                if (Schema::hasColumn('sprs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
