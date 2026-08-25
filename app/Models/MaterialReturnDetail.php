<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReturnDetail extends Model
{
    protected $fillable = [
        'material_return_id',
        'site_material_stock_id',
        'barang_material_id',
        'qty',
        'qty_normal',
        'qty_quarantine',
        'qty_scrap',
        'qty_lost',
        'satuan',
        'condition_status',
        'stock_disposition',
        'condition_note',
        'harga_satuan',
        'subtotal',
    ];

    protected $casts = ['qty' => 'float', 'qty_normal' => 'float', 'qty_quarantine' => 'float', 'qty_scrap' => 'float', 'qty_lost' => 'float', 'harga_satuan' => 'float', 'subtotal' => 'float'];

    public function materialReturn(): BelongsTo
    {
        return $this->belongsTo(MaterialReturn::class);
    }

    public function siteMaterialStock(): BelongsTo
    {
        return $this->belongsTo(SiteMaterialStock::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }
}
