<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprs', function (Blueprint $table) {
            $table->id();
            $table->string('kode_spr')->unique();
            $table->foreignId('costumer_id')->constrained('costumers')->onDelete('cascade');
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_spr');
            $table->string('metode_pembayaran');
            $table->decimal('harga_jual', 18, 2)->default(0);
            $table->decimal('booking_fee', 18, 2)->default(0);
            $table->decimal('uang_muka', 18, 2)->default(0);
            $table->decimal('nilai_pengajuan_kpr', 18, 2)->default(0);
            $table->string('status')->default('menunggu_manager');
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprs');
    }
};
