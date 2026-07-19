<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_purchase_id',
        'barang_material_id',
        'material_unit_id',
        'qty',
        'qty_base',
        'qty_diterima',
        'qty_diterima_base',
        'inspection_status',
        'inspection_note',
        'checked_by',
        'checked_at',
        'satuan',
        'conversion_to_base',
        'harga_satuan',
        'harga_satuan_base',
        'diskon',
        'subtotal',
    ];

    protected $casts = [
        'qty' => 'float',
        'qty_base' => 'float',
        'qty_diterima' => 'float',
        'qty_diterima_base' => 'float',
        'conversion_to_base' => 'float',
        'harga_satuan' => 'float',
        'harga_satuan_base' => 'float',
        'diskon' => 'float',
        'subtotal' => 'float',
        'checked_at' => 'datetime',
    ];

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function materialPurchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class);
    }

    public function materialUnit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
