<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            $table->foreignId('kontraktor_id')->nullable()->change();
        });

        $internalContractorIds = DB::table('kontraktors')
            ->where('kode_kontraktor', 'INTERNAL-TAKANG')
            ->pluck('id');

        if ($internalContractorIds->isEmpty()) {
            return;
        }

        DB::table('spk_kontraktors')
            ->whereIn('kontraktor_id', $internalContractorIds)
            ->update(['kontraktor_id' => null]);

        DB::table('kontraktors')
            ->whereIn('id', $internalContractorIds)
            ->delete();
    }

    public function down(): void
    {
        $internalContractorId = DB::table('kontraktors')->insertGetId([
            'kode_kontraktor' => 'INTERNAL-TAKANG',
            'nama_kontraktor' => 'Tukang Sendiri',
            'jenis_badan' => 'internal',
            'bidang_pekerjaan' => 'Tukang sendiri',
            'penanggung_jawab' => 'Sistem',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('spk_kontraktors')
            ->whereNull('kontraktor_id')
            ->update(['kontraktor_id' => $internalContractorId]);

        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            $table->foreignId('kontraktor_id')->nullable(false)->change();
        });
    }
};
