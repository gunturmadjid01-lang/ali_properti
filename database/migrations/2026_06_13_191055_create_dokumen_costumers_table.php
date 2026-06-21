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
        Schema::create('dokumen_costumers', function (Blueprint $table) {
            $table->id();
            $table->string('kode_dokumen')->unique();
            $table->string('nama_dokumen');
            $table->string('kategori_pengajuan')->default('umum');
            $table->boolean('wajib')->default(true);
            $table->text('keterangan')->nullable();
            $table->string('status')->default('aktif');
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
        Schema::dropIfExists('dokumen_costumers');
    }
};
