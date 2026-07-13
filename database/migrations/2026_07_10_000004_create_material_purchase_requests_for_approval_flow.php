<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_purchase_requests')) {
            Schema::create('material_purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->string('kode_request')->unique();
                $table->date('tanggal');
                $table->foreignId('gudang_id')->constrained('gudangs')->restrictOnDelete();
                $table->string('status')->default('diajukan');
                $table->text('keterangan')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
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
        }

        if (! Schema::hasTable('material_purchase_request_details')) {
            Schema::create('material_purchase_request_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('material_purchase_request_id')->constrained('material_purchase_requests')->cascadeOnDelete();
                $table->foreignId('barang_material_id')->constrained('barang_materials')->restrictOnDelete();
                $table->decimal('qty', 16, 2);
                $table->string('satuan')->nullable();
                $table->text('catatan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('material_purchases', 'material_purchase_request_id')) {
            Schema::table('material_purchases', function (Blueprint $table) {
                $table->foreignId('material_purchase_request_id')
                    ->nullable()
                    ->after('material_request_id')
                    ->constrained('material_purchase_requests')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_purchases', 'material_purchase_request_id')) {
            Schema::table('material_purchases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('material_purchase_request_id');
            });
        }

        Schema::dropIfExists('material_purchase_request_details');
        Schema::dropIfExists('material_purchase_requests');
    }
};
