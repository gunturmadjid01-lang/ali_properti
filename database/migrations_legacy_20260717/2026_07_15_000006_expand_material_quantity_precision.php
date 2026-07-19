<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return; // SQLite NUMERIC tidak membatasi skala aktual.
        }
        Schema::table('stok_materials', fn (Blueprint $table) => $table->decimal('qty', 18, 6)->default(0)->change());
        Schema::table('transaksi_logistik_details', fn (Blueprint $table) => $table->decimal('qty', 18, 6)->change());
        Schema::table('material_purchase_details', function (Blueprint $table) {
            $table->decimal('qty', 18, 6)->change();
            $table->decimal('qty_diterima', 18, 6)->default(0)->change();
        });
        Schema::table('material_stock_opname_details', function (Blueprint $table) {
            foreach (['stok_sistem', 'fisik', 'masuk', 'keluar', 'selisih'] as $column) {
                $table->decimal($column, 18, 6)->default(0)->change();
            }
        });
    }

    public function down(): void {}
};
