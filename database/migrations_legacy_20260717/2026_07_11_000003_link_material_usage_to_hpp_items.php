<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_usage_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('material_usage_details', 'detail_rumah_hpp_item_id')) {
                $table->foreignId('detail_rumah_hpp_item_id')->nullable()->after('barang_material_id')->constrained('detail_rumah_hpp_items')->nullOnDelete();
            }
        });

        Schema::table('hpp_realisasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('hpp_realisasis', 'detail_rumah_hpp_item_id')) {
                $table->foreignId('detail_rumah_hpp_item_id')->nullable()->after('kelompok_hpp_id')->constrained('detail_rumah_hpp_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hpp_realisasis', function (Blueprint $table): void {
            if (Schema::hasColumn('hpp_realisasis', 'detail_rumah_hpp_item_id')) {
                $table->dropConstrainedForeignId('detail_rumah_hpp_item_id');
            }
        });

        Schema::table('material_usage_details', function (Blueprint $table): void {
            if (Schema::hasColumn('material_usage_details', 'detail_rumah_hpp_item_id')) {
                $table->dropConstrainedForeignId('detail_rumah_hpp_item_id');
            }
        });
    }
};
