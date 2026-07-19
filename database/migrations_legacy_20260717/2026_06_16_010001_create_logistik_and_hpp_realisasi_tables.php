<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_materials', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang')->unique();
            $table->string('nama_barang');
            $table->string('satuan');
            $table->decimal('harga_hpp', 16, 2)->default(0);
            $table->string('status')->default('aktif');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stok_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
            $table->decimal('qty', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['barang_material_id', 'cabang_id']);
        });

        Schema::create('transaksi_logistiks', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi')->unique();
            $table->date('tanggal');
            $table->string('jenis');
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('kelompok_hpp_id')->nullable()->constrained('kelompok_hpps')->nullOnDelete();
            $table->decimal('total_nominal', 16, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transaksi_logistik_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_logistik_id')->constrained('transaksi_logistiks')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('qty', 16, 2);
            $table->string('satuan');
            $table->decimal('harga_satuan', 16, 2);
            $table->decimal('subtotal', 16, 2);
            $table->timestamps();
        });

        Schema::create('hpp_realisasis', function (Blueprint $table) {
            $table->id();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('kelompok_hpp_id')->nullable()->constrained('kelompok_hpps')->nullOnDelete();
            $table->string('sumber_type')->nullable();
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->date('tanggal');
            $table->decimal('nominal', 16, 2);
            $table->text('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_type', 'target_id']);
            $table->index(['sumber_type', 'sumber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_realisasis');
        Schema::dropIfExists('transaksi_logistik_details');
        Schema::dropIfExists('transaksi_logistiks');
        Schema::dropIfExists('stok_materials');
        Schema::dropIfExists('barang_materials');
    }
};
