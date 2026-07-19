<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('material_requests', 'kelompok_hpp_id')) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // SQLite 3.39 gagal menghapus kolom yang masih disebut di definisi FK.
                // Kolom legacy dibiarkan nullable agar struktur tabel tidak rusak.
                return;
            }
            Schema::table('material_requests', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('kelompok_hpp_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('material_requests', 'kelompok_hpp_id')) {
            Schema::table('material_requests', function (Blueprint $table): void {
                $table->foreignId('kelompok_hpp_id')->nullable()->after('tahapan_pembangunan_id')->constrained('kelompok_hpps')->nullOnDelete();
            });
        }
    }
};
