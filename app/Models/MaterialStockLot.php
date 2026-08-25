<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialStockLot extends Model
{
    protected $fillable = [
        'material_purchase_id', 'material_purchase_detail_id', 'barang_material_id', 'gudang_id',
        'source_type', 'source_id',
        'kode_lot', 'tanggal_terima', 'qty_diterima', 'qty_tersedia', 'unit_cost', 'total_cost', 'kondisi', 'status',
    ];

    protected $casts = [
        'tanggal_terima' => 'date', 'qty_diterima' => 'float', 'qty_tersedia' => 'float',
        'unit_cost' => 'float', 'total_cost' => 'float',
    ];
}
