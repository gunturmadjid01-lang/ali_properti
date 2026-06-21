<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('kode_request')->unique();
            $table->date('tanggal');
            $table->foreignId('gudang_id')->constrained('gudangs')->restrictOnDelete();
            $table->string('status')->default('diajukan');
            $table->text('keterangan')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('record_status')->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_purchase_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_purchase_request_id')->constrained('material_purchase_requests')->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->restrictOnDelete();
            $table->decimal('qty', 16, 2);
            $table->string('satuan');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->foreignId('material_purchase_request_id')
                ->nullable()
                ->after('material_request_id')
                ->constrained('material_purchase_requests')
                ->nullOnDelete();
        });

        Schema::table('material_purchase_details', function (Blueprint $table) {
            $table->string('inspection_status')->default('pending')->after('qty_diterima');
            $table->text('inspection_note')->nullable()->after('inspection_status');
            $table->foreignId('checked_by')->nullable()->after('inspection_note')->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('material_purchase_details', function (Blueprint $table) {
            $table->dropColumn('checked_at');
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn(['inspection_note', 'inspection_status']);
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_purchase_request_id');
        });

        Schema::dropIfExists('material_purchase_request_details');
        Schema::dropIfExists('material_purchase_requests');
    }
};
