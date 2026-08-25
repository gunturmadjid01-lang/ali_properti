<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialConditionStock extends Model
{
    protected $fillable = ['barang_material_id', 'gudang_id', 'condition_bucket', 'qty', 'unit_cost', 'inventory_value'];
    protected $casts = ['qty' => 'float', 'unit_cost' => 'float', 'inventory_value' => 'float'];
}
