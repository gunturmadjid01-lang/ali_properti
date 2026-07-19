<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('kode_request')->unique();
            $table->date('tanggal');
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('kelompok_hpp_id')->nullable()->constrained('kelompok_hpps')->nullOnDelete();
            $table->string('status')->default('diajukan');
            $table->text('keterangan')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('material_requests')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('qty', 16, 2);
            $table->string('satuan');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('material_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pembelian')->unique();
            $table->date('tanggal');
            $table->foreignId('material_request_id')->nullable()->constrained('material_requests')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->foreignId('detail_rumah_id')->nullable()->constrained('detail_rumahs')->nullOnDelete();
            $table->foreignId('tahapan_pembangunan_id')->nullable()->constrained('tahapan_pembangunans')->nullOnDelete();
            $table->foreignId('kelompok_hpp_id')->nullable()->constrained('kelompok_hpps')->nullOnDelete();
            $table->string('supplier')->nullable();
            $table->decimal('total_nominal', 16, 2)->default(0);
            $table->string('status')->default('menunggu_approval_manager');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('fund_released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fund_released_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('receive_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_purchase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained('material_purchases')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->decimal('qty', 16, 2);
            $table->decimal('qty_diterima', 16, 2)->default(0);
            $table->string('satuan');
            $table->decimal('harga_satuan', 16, 2);
            $table->decimal('subtotal', 16, 2);
            $table->timestamps();
        });

        Schema::create('operational_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['role', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('operational_settings');
        Schema::dropIfExists('material_purchase_details');
        Schema::dropIfExists('material_purchases');
        Schema::dropIfExists('material_request_details');
        Schema::dropIfExists('material_requests');
    }
};
