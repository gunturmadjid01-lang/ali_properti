<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchase_details', function (Blueprint $table) {
            $unit = $table->foreignId('material_unit_id')->nullable()->after('barang_material_id');
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $unit->constrained('material_units')->nullOnDelete();
            }
            $table->decimal('conversion_to_base', 18, 6)->default(1)->after('satuan');
            $table->decimal('qty_base', 18, 6)->default(0)->after('qty');
            $table->decimal('qty_diterima_base', 18, 6)->default(0)->after('qty_diterima');
            $table->decimal('harga_satuan_base', 18, 2)->default(0)->after('harga_satuan');
        });
        Schema::table('transaksi_logistik_details', function (Blueprint $table) {
            $table->decimal('input_qty', 18, 6)->nullable()->after('qty');
            $unit = $table->foreignId('input_unit_id')->nullable()->after('input_qty');
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $unit->constrained('material_units')->nullOnDelete();
            }
            $table->string('input_satuan')->nullable()->after('input_unit_id');
            $table->decimal('conversion_to_base', 18, 6)->default(1)->after('input_satuan');
        });
        Schema::table('material_stock_opname_details', function (Blueprint $table) {
            $table->json('physical_unit_counts')->nullable()->after('fisik');
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_opname_details', fn (Blueprint $table) => $table->dropColumn('physical_unit_counts'));
        Schema::table('transaksi_logistik_details', function (Blueprint $table) {
            Schema::getConnection()->getDriverName() === 'sqlite' ? $table->dropColumn('input_unit_id') : $table->dropConstrainedForeignId('input_unit_id');
            $table->dropColumn(['input_qty', 'input_satuan', 'conversion_to_base']);
        });
        Schema::table('material_purchase_details', function (Blueprint $table) {
            Schema::getConnection()->getDriverName() === 'sqlite' ? $table->dropColumn('material_unit_id') : $table->dropConstrainedForeignId('material_unit_id');
            $table->dropColumn(['conversion_to_base', 'qty_base', 'qty_diterima_base', 'harga_satuan_base']);
        });
    }
};
