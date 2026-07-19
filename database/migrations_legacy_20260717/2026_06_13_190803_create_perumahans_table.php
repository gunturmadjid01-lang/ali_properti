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
        Schema::create('perumahans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang_perusahaans')->onDelete('cascade');
            $table->string('kode_proyek')->nullable()->unique();
            $table->string('nama_perusahaan');
            $table->string('developer_name')->nullable();
            $table->string('alamat');
            $table->string('latitude')->nullable();
            $table->string('longtitude')->nullable();
            $table->string('logo')->nullable();
            $table->string('luas_lahan');
            $table->string('luas_komersial')->nullable();
            $table->string('luas_fasos_fasum')->nullable();
            $table->integer('jumlah_unit');
            $table->integer('total_blok')->default(0);
            $table->decimal('harga_mulai', 16, 2)->default(0);
            $table->date('tanggal_mulai');
            $table->date('tanggal_target_selesai')->nullable();
            $table->string('jenis_sertifikat')->nullable();
            $table->string('nomor_sertifikat_induk')->nullable();
            $table->string('nama_marketing')->nullable();
            $table->string('phone_marketing')->nullable();
            $table->string('email_marketing')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('status');
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perumahans');
    }
};
