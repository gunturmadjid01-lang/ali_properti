<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchases', function (Blueprint $table): void {
            $table->string('nomor_faktur')->nullable()->after('tanggal_barang_masuk');
            $table->date('tanggal_faktur')->nullable()->after('nomor_faktur');
            $table->string('nomor_surat_jalan')->nullable()->after('tanggal_faktur');
            $table->string('nama_ekspedisi')->nullable()->after('nomor_surat_jalan');
            $table->string('nomor_kendaraan')->nullable()->after('nama_ekspedisi');
            $table->decimal('biaya_ekspedisi', 18, 2)->default(0)->after('diskon_transaksi');
            $table->decimal('upah_buruh_logistik', 18, 2)->default(0)->after('biaya_ekspedisi');
            $table->decimal('biaya_lain_perolehan', 18, 2)->default(0)->after('upah_buruh_logistik');
            $table->string('metode_alokasi_biaya', 20)->default('nilai')->after('biaya_lain_perolehan');
            $table->decimal('total_landed_cost', 18, 2)->default(0)->after('total_nominal');
        });

        Schema::table('material_purchase_details', function (Blueprint $table): void {
            $table->decimal('qty_faktur', 18, 4)->nullable()->after('qty_base');
            $table->decimal('qty_fisik_tiba', 18, 4)->nullable()->after('qty_faktur');
            $table->decimal('qty_diterima_baik', 18, 4)->default(0)->after('qty_diterima_base');
            $table->decimal('qty_cacat', 18, 4)->default(0)->after('qty_diterima_baik');
            $table->decimal('qty_ditolak', 18, 4)->default(0)->after('qty_cacat');
            $table->decimal('qty_kurang', 18, 4)->default(0)->after('qty_ditolak');
            $table->decimal('qty_lebih', 18, 4)->default(0)->after('qty_kurang');
            $table->string('kondisi_fisik', 30)->nullable()->after('inspection_status');
            $table->string('status_selisih', 30)->default('belum_diperiksa')->after('kondisi_fisik');
            $table->text('alasan_selisih')->nullable()->after('inspection_note');
            $table->decimal('biaya_ekspedisi_alokasi', 18, 2)->default(0)->after('subtotal');
            $table->decimal('upah_buruh_alokasi', 18, 2)->default(0)->after('biaya_ekspedisi_alokasi');
            $table->decimal('biaya_lain_alokasi', 18, 2)->default(0)->after('upah_buruh_alokasi');
            $table->decimal('landed_cost_total', 18, 2)->default(0)->after('biaya_lain_alokasi');
            $table->decimal('landed_unit_cost', 18, 4)->default(0)->after('landed_cost_total');
        });

        Schema::table('stok_materials', function (Blueprint $table): void {
            $table->decimal('average_unit_cost', 18, 4)->default(0)->after('qty');
            $table->decimal('inventory_value', 18, 2)->default(0)->after('average_unit_cost');
        });

        Schema::table('site_material_stocks', function (Blueprint $table): void {
            $table->decimal('average_unit_cost', 18, 4)->default(0)->after('qty_available');
        });

        Schema::table('material_usage_details', function (Blueprint $table): void {
            $table->decimal('unit_cost_snapshot', 18, 4)->default(0)->after('satuan');
            $table->decimal('subtotal_snapshot', 18, 2)->default(0)->after('unit_cost_snapshot');
        });

        Schema::create('material_stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_purchase_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barang_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained()->cascadeOnDelete();
            $table->string('kode_lot')->unique();
            $table->date('tanggal_terima');
            $table->decimal('qty_diterima', 18, 4);
            $table->decimal('qty_tersedia', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 2);
            $table->string('kondisi', 30)->default('baik');
            $table->string('status', 20)->default('tersedia');
            $table->timestamps();
            $table->unique('material_purchase_detail_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_lots');
        Schema::table('material_usage_details', fn (Blueprint $table) => $table->dropColumn(['unit_cost_snapshot', 'subtotal_snapshot']));
        Schema::table('site_material_stocks', fn (Blueprint $table) => $table->dropColumn('average_unit_cost'));
        Schema::table('stok_materials', fn (Blueprint $table) => $table->dropColumn(['average_unit_cost', 'inventory_value']));
        Schema::table('material_purchase_details', fn (Blueprint $table) => $table->dropColumn([
            'qty_faktur', 'qty_fisik_tiba', 'qty_diterima_baik', 'qty_cacat', 'qty_ditolak', 'qty_kurang', 'qty_lebih',
            'kondisi_fisik', 'status_selisih', 'alasan_selisih', 'biaya_ekspedisi_alokasi', 'upah_buruh_alokasi',
            'biaya_lain_alokasi', 'landed_cost_total', 'landed_unit_cost',
        ]));
        Schema::table('material_purchases', fn (Blueprint $table) => $table->dropColumn([
            'nomor_faktur', 'tanggal_faktur', 'nomor_surat_jalan', 'nama_ekspedisi', 'nomor_kendaraan',
            'biaya_ekspedisi', 'upah_buruh_logistik', 'biaya_lain_perolehan', 'metode_alokasi_biaya', 'total_landed_cost',
        ]));
    }
};
