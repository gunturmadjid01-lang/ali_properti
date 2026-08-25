<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'barang_material_id',
        'gudang_id',
        'cabang_id',
        'qty',
        'average_unit_cost',
        'inventory_value',
    ];

    protected $casts = [
        'qty' => 'float',
        'average_unit_cost' => 'float',
        'inventory_value' => 'float',
    ];

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_id');
    }
}
