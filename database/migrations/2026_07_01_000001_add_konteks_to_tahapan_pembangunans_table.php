<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tahapan_pembangunans', 'konteks')) {
            Schema::table('tahapan_pembangunans', function (Blueprint $table) {
                $table->string('konteks')->default('unit')->after('status');
            });
        }

        DB::table('tahapan_pembangunans')->update(['konteks' => 'unit']);

        $kawasanTahapan = [
            ['nama_tahapan' => 'Pematangan Lahan', 'bobot_persen' => 15, 'urutan' => 1],
            ['nama_tahapan' => 'Cut and Fill', 'bobot_persen' => 10, 'urutan' => 2],
            ['nama_tahapan' => 'Jalan Kawasan', 'bobot_persen' => 15, 'urutan' => 3],
            ['nama_tahapan' => 'Drainase', 'bobot_persen' => 10, 'urutan' => 4],
            ['nama_tahapan' => 'Pagar Kawasan', 'bobot_persen' => 10, 'urutan' => 5],
            ['nama_tahapan' => 'Gerbang Perumahan', 'bobot_persen' => 10, 'urutan' => 6],
            ['nama_tahapan' => 'Instalasi Air Bersih Kawasan', 'bobot_persen' => 10, 'urutan' => 7],
            ['nama_tahapan' => 'Instalasi Listrik Kawasan', 'bobot_persen' => 10, 'urutan' => 8],
            ['nama_tahapan' => 'Taman dan Fasum', 'bobot_persen' => 5, 'urutan' => 9],
            ['nama_tahapan' => 'Septictank Komunal', 'bobot_persen' => 5, 'urutan' => 10],
        ];

        foreach ($kawasanTahapan as $item) {
            DB::table('tahapan_pembangunans')->updateOrInsert(
                ['nama_tahapan' => $item['nama_tahapan']],
                [
                    ...$item,
                    'konteks' => 'kawasan',
                    'status' => 'aktif',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tahapan_pembangunans', 'konteks')) {
            Schema::table('tahapan_pembangunans', function (Blueprint $table) {
                $table->dropColumn('konteks');
            });
        }
    }
};
