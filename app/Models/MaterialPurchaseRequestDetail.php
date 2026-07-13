<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseRequestDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_purchase_request_id',
        'barang_material_id',
        'qty',
        'satuan',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty' => 'float',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchaseRequest::class, 'material_purchase_request_id');
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }
}
