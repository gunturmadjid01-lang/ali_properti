<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('kode_opname')->unique();
            $table->foreignId('gudang_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->string('record_status')->default('locked');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_stock_opname_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_stock_opname_id')->constrained('material_stock_opnames')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('stok_sistem', 16, 3)->default(0);
            $table->decimal('fisik', 16, 3)->default(0);
            $table->decimal('selisih', 16, 3)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['material_stock_opname_id', 'barang_material_id'], 'msod_opname_material_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_opname_details');
        Schema::dropIfExists('material_stock_opnames');
    }
};
