<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockOpnameDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_stock_opname_id',
        'barang_material_id',
        'stok_sistem',
        'fisik',
        'physical_unit_counts',
        'masuk',
        'keluar',
        'selisih',
        'catatan',
    ];

    protected $casts = [
        'stok_sistem' => 'float',
        'fisik' => 'float',
        'physical_unit_counts' => 'array',
        'masuk' => 'float',
        'keluar' => 'float',
        'selisih' => 'float',
    ];

    public function opname(): BelongsTo
    {
        return $this->belongsTo(MaterialStockOpname::class, 'material_stock_opname_id');
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }
}
