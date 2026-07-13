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
        'qty',
        'qty_diterima',
        'inspection_status',
        'inspection_note',
        'checked_by',
        'checked_at',
        'satuan',
        'harga_satuan',
        'diskon',
        'subtotal',
    ];

    protected $casts = [
        'qty' => 'float',
        'qty_diterima' => 'float',
        'harga_satuan' => 'float',
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

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
