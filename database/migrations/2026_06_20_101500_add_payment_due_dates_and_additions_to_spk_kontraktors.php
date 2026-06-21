<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_kontraktors', function (Blueprint $table) {
            $table->decimal('nilai_kontrak_dasar', 16, 2)->default(0)->after('tanggal_selesai');
            $table->decimal('total_penambahan', 16, 2)->default(0)->after('nilai_kontrak');
        });

        Schema::table('spk_kontraktor_payments', function (Blueprint $table) {
            $table->date('tanggal_jatuh_tempo')->nullable()->after('termin_ke');
        });

        Schema::create('spk_kontraktor_additions', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('spk_kontraktor_additions');

        Schema::table('spk_kontraktor_payments', function (Blueprint $table) {
            $table->dropColumn('tanggal_jatuh_tempo');
        });

        Schema::table('spk_kontraktors', function (Blueprint $table) {
            $table->dropColumn(['nilai_kontrak_dasar', 'total_penambahan']);
        });
    }
};
