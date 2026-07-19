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
        Schema::create('transaksi_keuangans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang_perusahaans')->onDelete('cascade');
            $table->foreignId('tipe_post_id')->constrained('tipe_posts')->onDelete('cascade');
            $table->date('tanggal');
            $table->float('nominal',16,2);
            $table->text('keterangan');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi_keuangans');
    }
};
