<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_opname_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_stock_opname_details', 'masuk')) {
                $table->decimal('masuk', 16, 3)->default(0)->after('fisik');
            }

            if (! Schema::hasColumn('material_stock_opname_details', 'keluar')) {
                $table->decimal('keluar', 16, 3)->default(0)->after('masuk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_opname_details', function (Blueprint $table): void {
            if (Schema::hasColumn('material_stock_opname_details', 'keluar')) {
                $table->dropColumn('keluar');
            }

            if (Schema::hasColumn('material_stock_opname_details', 'masuk')) {
                $table->dropColumn('masuk');
            }
        });
    }
};
