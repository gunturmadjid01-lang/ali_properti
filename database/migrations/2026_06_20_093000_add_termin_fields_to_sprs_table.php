<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (! Schema::hasColumn('sprs', 'jumlah_termin')) {
                $table->unsignedSmallInteger('jumlah_termin')->nullable()->after('nilai_pengajuan_kpr');
            }

            if (! Schema::hasColumn('sprs', 'nominal_termin')) {
                $table->decimal('nominal_termin', 16, 2)->nullable()->after('jumlah_termin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sprs', function (Blueprint $table) {
            if (Schema::hasColumn('sprs', 'nominal_termin')) {
                $table->dropColumn('nominal_termin');
            }

            if (Schema::hasColumn('sprs', 'jumlah_termin')) {
                $table->dropColumn('jumlah_termin');
            }
        });
    }
};
