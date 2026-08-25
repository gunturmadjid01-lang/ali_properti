<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialStockLotAllocation extends Model
{
    protected $fillable = ['transaksi_logistik_detail_id', 'material_stock_lot_id', 'qty', 'unit_cost', 'amount'];
    protected $casts = ['qty' => 'float', 'unit_cost' => 'float', 'amount' => 'float'];
}
