<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stok_materials')->where('qty', '>', 0)->where('average_unit_cost', '<=', 0)
            ->orderBy('id')->chunkById(200, function ($stocks): void {
                $prices = DB::table('barang_materials')->whereIn('id', $stocks->pluck('barang_material_id'))->pluck('harga_hpp', 'id');
                foreach ($stocks as $stock) {
                    $cost = (float) ($prices[$stock->barang_material_id] ?? 0);
                    DB::table('stok_materials')->where('id', $stock->id)->update([
                        'average_unit_cost' => $cost,
                        'inventory_value' => (float) $stock->qty * $cost,
                    ]);
                }
            });

        DB::table('site_material_stocks')->where('qty_available', '>', 0)->where('average_unit_cost', '<=', 0)
            ->orderBy('id')->chunkById(200, function ($stocks): void {
                $warehouseCosts = DB::table('stok_materials')->get()->keyBy(fn ($row) => $row->gudang_id.'-'.$row->barang_material_id);
                $prices = DB::table('barang_materials')->whereIn('id', $stocks->pluck('barang_material_id'))->pluck('harga_hpp', 'id');
                foreach ($stocks as $stock) {
                    $warehouse = $warehouseCosts->get($stock->gudang_id.'-'.$stock->barang_material_id);
                    $cost = (float) ($warehouse?->average_unit_cost ?: ($prices[$stock->barang_material_id] ?? 0));
                    DB::table('site_material_stocks')->where('id', $stock->id)->update(['average_unit_cost' => $cost]);
                }
            });
    }

    public function down(): void {}
};
