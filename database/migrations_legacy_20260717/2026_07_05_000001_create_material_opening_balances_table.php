<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->date('tanggal_saldo');
            $table->decimal('qty', 16, 2)->default(0);
            $table->decimal('harga_satuan', 16, 2)->default(0);
            $table->decimal('total_nilai', 18, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gudang_id', 'barang_material_id'], 'material_opening_balance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_opening_balances');
    }
};
