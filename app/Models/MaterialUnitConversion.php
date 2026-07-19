<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialUnitConversion extends Model
{
    protected $fillable = ['barang_material_id', 'level', 'parent_unit_id', 'child_unit_id', 'factor', 'cumulative_factor', 'parent_price', 'child_price'];

    protected $casts = ['factor' => 'float', 'cumulative_factor' => 'float', 'parent_price' => 'float', 'child_price' => 'float'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class, 'barang_material_id');
    }

    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'parent_unit_id');
    }

    public function childUnit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'child_unit_id');
    }
}
