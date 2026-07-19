<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('barang_materials', 'jenis_material')) {
                $table->string('jenis_material')->nullable()->after('kategori_material');
            }

            if (! Schema::hasColumn('barang_materials', 'merk_material')) {
                $table->string('merk_material')->nullable()->after('jenis_material');
            }
        });

        if (Schema::hasColumn('barang_materials', 'kategori_material')) {
            DB::table('barang_materials')
                ->whereNull('jenis_material')
                ->update(['jenis_material' => DB::raw('kategori_material')]);
        }
    }

    public function down(): void
    {
        Schema::table('barang_materials', function (Blueprint $table) {
            foreach (['merk_material', 'jenis_material'] as $column) {
                if (Schema::hasColumn('barang_materials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
