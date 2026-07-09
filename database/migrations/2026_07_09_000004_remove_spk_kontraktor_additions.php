<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('spk_kontraktor_additions');

        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            if (Schema::hasColumn('spk_kontraktors', 'total_penambahan')) {
                $table->dropColumn('total_penambahan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table): void {
            if (! Schema::hasColumn('spk_kontraktors', 'total_penambahan')) {
                $table->decimal('total_penambahan', 16, 2)->default(0)->after('nilai_kontrak');
            }
        });

        Schema::create('spk_kontraktor_additions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spk_kontraktor_id')->constrained('spk_kontraktors')->cascadeOnDelete();
            $table->string('kategori_penambahan')->default('lainnya');
            $table->string('judul_penambahan');
            $table->text('deskripsi')->nullable();
            $table->decimal('volume', 16, 2)->default(0);
            $table->string('satuan')->nullable();
            $table->decimal('harga_satuan', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
