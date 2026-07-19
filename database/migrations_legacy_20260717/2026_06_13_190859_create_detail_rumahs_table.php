<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detail_rumahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perumahan_id')->constrained('perumahans')->onDelete('cascade');
            $table->string('kode_nlok');
            $table->string('nomor_rumah');
            $table->string('tipe_rumah')->nullable();
            $table->string('model_unit')->nullable();
            $table->string('luas_bangunan')->nullable();
            $table->string('luas_tanah');
            $table->unsignedTinyInteger('jumlah_lantai')->nullable();
            $table->unsignedTinyInteger('kamar_tidur')->nullable();
            $table->unsignedTinyInteger('kamar_mandi')->nullable();
            $table->string('daya_listrik')->nullable();
            $table->string('sumber_air')->nullable();
            $table->string('carport')->nullable();
            $table->string('arah_hadap')->nullable();
            $table->string('posisi_unit')->nullable();
            $table->decimal('harga_jual', 16, 2)->default(0);
            $table->string('status_penjualan')->default('tersedia');
            $table->string('status_pembangunan')->default('kapling');
            $table->decimal('progress_terakhir', 5, 2)->default(0);
            $table->date('tanggal_mulai_bangun')->nullable();
            $table->date('tanggal_selesai_bangun')->nullable();
            $table->text('spesifikasi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_rumahs');
    }
};
