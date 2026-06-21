<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialPriceHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'barang_material_id',
        'tanggal_berlaku',
        'harga_satuan',
        'supplier',
        'keterangan',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tanggal_berlaku' => 'date',
        'harga_satuan' => 'float',
    ];

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }
}
