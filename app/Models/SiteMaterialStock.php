<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteMaterialStock extends Model
{
    protected $fillable = [
        'gudang_id',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'kelompok_hpp_id',
        'barang_material_id',
        'qty_received',
        'qty_used',
        'qty_returned',
        'qty_reserved_return',
        'qty_available',
    ];

    protected $casts = [
        'qty_received' => 'float',
        'qty_used' => 'float',
        'qty_returned' => 'float',
        'qty_reserved_return' => 'float',
        'qty_available' => 'float',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function tahapanPembangunan(): BelongsTo
    {
        return $this->belongsTo(TahapanPembangunan::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function kelompokHpp(): BelongsTo
    {
        return $this->belongsTo(KelompokHpp::class);
    }
}
