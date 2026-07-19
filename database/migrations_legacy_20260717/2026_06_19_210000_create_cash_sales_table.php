<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('kode_cash')->unique();
            $table->foreignId('spr_id')->constrained('sprs')->cascadeOnDelete();
            $table->foreignId('costumer_id')->constrained('costumers')->cascadeOnDelete();
            $table->foreignId('detail_rumah_id')->constrained('detail_rumahs')->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_transaksi');
            $table->decimal('harga_rumah', 18, 2)->default(0);
            $table->decimal('total_tagihan', 18, 2)->default(0);
            $table->decimal('total_dibayar', 18, 2)->default(0);
            $table->decimal('sisa_tagihan', 18, 2)->default(0);
            $table->string('status_pembayaran')->default('menunggu_pembayaran');
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sales');
    }
};
