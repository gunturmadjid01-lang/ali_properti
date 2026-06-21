<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gudangs', function (Blueprint $table) {
            $table->id();
            $table->string('kode_gudang')->unique();
            $table->string('nama_gudang');
            $table->foreignId('cabang_id')->nullable()->constrained('cabang_perusahaans')->nullOnDelete();
            $table->foreignId('perumahan_id')->nullable()->constrained('perumahans')->nullOnDelete();
            $table->string('penanggung_jawab')->nullable();
            $table->string('phone')->nullable();
            $table->text('alamat')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status')->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_material_id')->constrained('barang_materials')->cascadeOnDelete();
            $table->date('tanggal_berlaku');
            $table->decimal('harga_satuan', 16, 2);
            $table->string('supplier')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('barang_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('barang_materials', 'kategori_material')) {
                $table->string('kategori_material')->nullable()->after('nama_barang');
            }
            if (! Schema::hasColumn('barang_materials', 'stok_minimum')) {
                $table->decimal('stok_minimum', 16, 2)->default(0)->after('harga_hpp');
            }
            if (! Schema::hasColumn('barang_materials', 'catatan')) {
                $table->text('catatan')->nullable()->after('stok_minimum');
            }
        });

        foreach (['stok_materials', 'transaksi_logistiks', 'material_requests', 'material_purchases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'gudang_id')) {
                    $table->foreignId('gudang_id')->nullable()->after('id')->constrained('gudangs')->nullOnDelete();
                }
            });
        }

        foreach (['detail_perumahan_hpps', 'detail_rumah_hpp_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'barang_material_id')) {
                    $table->foreignId('barang_material_id')->nullable()->after('kelompok_hpp_id')->constrained('barang_materials')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['material_purchases', 'material_requests', 'transaksi_logistiks', 'stok_materials'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'gudang_id')) {
                    $table->dropConstrainedForeignId('gudang_id');
                }
            });
        }

        foreach (['detail_rumah_hpp_items', 'detail_perumahan_hpps'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'barang_material_id')) {
                    $table->dropConstrainedForeignId('barang_material_id');
                }
            });
        }

        Schema::table('barang_materials', function (Blueprint $table) {
            foreach (['kategori_material', 'stok_minimum', 'catatan'] as $column) {
                if (Schema::hasColumn('barang_materials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('material_price_histories');
        Schema::dropIfExists('gudangs');
    }
};
