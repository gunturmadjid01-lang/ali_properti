<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'penambahan_tanah')) {
                $table->text('penambahan_tanah')->nullable()->after('nilai_pengajuan_kpr');
            }

            if (! Schema::hasColumn('sprs', 'harga_penambahan_tanah')) {
                $table->decimal('harga_penambahan_tanah', 18, 2)->default(0)->after('penambahan_tanah');
            }

            if (! Schema::hasColumn('sprs', 'penambahan_lain_lain')) {
                $table->text('penambahan_lain_lain')->nullable()->after('harga_penambahan_tanah');
            }

            if (! Schema::hasColumn('sprs', 'harga_penambahan_lain_lain')) {
                $table->decimal('harga_penambahan_lain_lain', 18, 2)->default(0)->after('penambahan_lain_lain');
            }

            if (! Schema::hasColumn('sprs', 'total_penambahan_tanah')) {
                $table->decimal('total_penambahan_tanah', 18, 2)->default(0)->after('harga_penambahan_lain_lain');
            }

            if (! Schema::hasColumn('sprs', 'total_penambahan_lain_lain')) {
                $table->decimal('total_penambahan_lain_lain', 18, 2)->default(0)->after('total_penambahan_tanah');
            }

            if (! Schema::hasColumn('sprs', 'total_penambahan')) {
                $table->decimal('total_penambahan', 18, 2)->default(0)->after('total_penambahan_lain_lain');
            }

            if (! Schema::hasColumn('sprs', 'nilai_pengajuan_akhir')) {
                $table->decimal('nilai_pengajuan_akhir', 18, 2)->default(0)->after('total_penambahan');
            }

            if (! Schema::hasColumn('sprs', 'tanggal_jatuh_tempo_termin')) {
                $table->date('tanggal_jatuh_tempo_termin')->nullable()->after('nominal_termin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (Schema::hasColumn('sprs', 'tanggal_jatuh_tempo_termin')) {
                $table->dropColumn('tanggal_jatuh_tempo_termin');
            }

            foreach ([
                'nilai_pengajuan_akhir',
                'total_penambahan',
                'total_penambahan_lain_lain',
                'total_penambahan_tanah',
                'harga_penambahan_lain_lain',
                'penambahan_lain_lain',
                'harga_penambahan_tanah',
                'penambahan_tanah',
            ] as $column) {
                if (Schema::hasColumn('sprs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
