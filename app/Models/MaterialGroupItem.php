<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialGroupItem extends Model
{
    protected $fillable = ['barang_material_id', 'material_unit_id', 'quantity', 'conversion_to_base', 'quantity_base', 'sort_order'];

    protected $casts = ['quantity' => 'float', 'conversion_to_base' => 'float', 'quantity_base' => 'float'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class, 'material_group_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class, 'barang_material_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'material_unit_id');
    }
}
