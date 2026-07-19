<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table) {
            $table->decimal('pengeluaran_bulanan', 18, 2)->nullable()->after('penghasilan');
            $table->string('pekerjaan_pasangan')->nullable()->after('tempat_lahir_pasangan');
            $table->decimal('penghasilan_pasangan', 18, 2)->nullable()->after('pekerjaan_pasangan');
            $table->decimal('pengeluaran_bulanan_pasangan', 18, 2)->nullable()->after('penghasilan_pasangan');
            $table->json('daftar_cicilan')->nullable()->after('pengeluaran_bulanan_pasangan');
        });
    }

    public function down(): void
    {
        Schema::table('costumers', function (Blueprint $table) {
            $table->dropColumn([
                'pengeluaran_bulanan',
                'pekerjaan_pasangan',
                'penghasilan_pasangan',
                'pengeluaran_bulanan_pasangan',
                'daftar_cicilan',
            ]);
        });
    }
};
