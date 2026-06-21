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
        Schema::create('costumers', function (Blueprint $table) {
            $table->id();
            $table->string('kode_costumer')->unique(); // auto generate code costumer after crud costumer
            $table->string('nama');
            $table->string('jenis_kelamin');
            $table->string('jenis_identitas');
            $table->string('no_identitas');
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('status_perkawinan');
            $table->string('alamat');
            $table->string('email')->nullable();
            $table->string('npwp')->nullable();
            $table->string('telepon')->nullable();
            $table->string('file_identitas')->nullable(); // image or pdf only
            $table->float('penghasilan')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('pekerjaan')->nullable(); // jenis pekerjaan
            $table->string('nama_perusahaan')->nullable();
            $table->string('alamat_perusahaan')->nullable();
            $table->string('telepon_perusahaan')->nullable();
            $table->text('keterangan_perusahaan')->nullable();
            $table->string('nama_lengkap_pasangan')->nullable();
            $table->string('jenis_kelamin_pasangan')->nullable();
            $table->string('jenis_identitas_pasangan')->nullable();
            $table->string('no_identitas_pasangan')->nullable();
            $table->date('tanggal_lahir_pasangan')->nullable();
            $table->string('tempat_lahir_pasangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costumers');
    }
};
