<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dokumen_costumers')->whereIn('kategori_pengajuan', ['umum', 'cash'])->update(['kategori_pengajuan' => 'spr']);
        DB::table('dokumen_costumers')->where('kategori_pengajuan', 'bertahap')->update(['kategori_pengajuan' => 'cash_bertahap']);
        DB::table('dokumen_costumers')->where('kategori_pengajuan', 'kpr')->update(['kategori_pengajuan' => 'kpr_bank']);
    }

    public function down(): void
    {
        DB::table('dokumen_costumers')->where('kategori_pengajuan', 'cash_bertahap')->update(['kategori_pengajuan' => 'bertahap']);
        DB::table('dokumen_costumers')->whereIn('kategori_pengajuan', ['kpr_bank', 'kpr_developer'])->update(['kategori_pengajuan' => 'kpr']);
    }
};
