<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_sale_id')->constrained('cash_sales')->cascadeOnDelete();
            $table->foreignId('transaksi_keuangan_id')->nullable()->constrained('transaksi_keuangans')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_pembayaran');
            $table->decimal('nominal', 18, 2)->default(0);
            $table->string('metode_pembayaran')->default('transfer');
            $table->text('keterangan')->nullable();
            $table->string('bukti_pembayaran')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sale_payments');
    }
};
