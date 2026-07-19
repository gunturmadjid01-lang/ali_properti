<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_purchases', 'subtotal_nominal')) {
                $table->decimal('subtotal_nominal', 16, 2)->default(0)->after('total_nominal');
            }

            if (! Schema::hasColumn('material_purchases', 'diskon_transaksi')) {
                $table->decimal('diskon_transaksi', 16, 2)->default(0)->after('subtotal_nominal');
            }
        });

        Schema::table('material_purchase_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_purchase_details', 'diskon')) {
                $table->decimal('diskon', 16, 2)->default(0)->after('harga_satuan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('material_purchase_details', function (Blueprint $table): void {
            if (Schema::hasColumn('material_purchase_details', 'diskon')) {
                $table->dropColumn('diskon');
            }
        });

        Schema::table('material_purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('material_purchases', 'diskon_transaksi')) {
                $table->dropColumn('diskon_transaksi');
            }

            if (Schema::hasColumn('material_purchases', 'subtotal_nominal')) {
                $table->dropColumn('subtotal_nominal');
            }
        });
    }
};
