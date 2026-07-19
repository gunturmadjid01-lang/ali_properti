<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('material_purchases', 'tanggal_barang_masuk')) {
            Schema::table('material_purchases', function (Blueprint $table) {
                $table->date('tanggal_barang_masuk')->nullable()->after('tanggal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_purchases', 'tanggal_barang_masuk')) {
            Schema::table('material_purchases', function (Blueprint $table) {
                $table->dropColumn('tanggal_barang_masuk');
            });
        }
    }
};
