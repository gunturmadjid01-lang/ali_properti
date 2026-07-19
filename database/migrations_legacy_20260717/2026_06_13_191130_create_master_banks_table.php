<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // migration ini diperuntukkan pemilihan keluar masuknya cash perumahan
    public function up(): void
    {
        Schema::create('master_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perumahan_id')->constrained('perumahans')->onDelete('cascade');
            $table->string('kode_bank')->unique();
            $table->string('nama_bank');
            $table->string('nomor_rekening')->nullable();
            $table->string('nama_rekening')->nullable();
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
        Schema::dropIfExists('master_banks');
    }
};
