<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailRumahHppItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'detail_rumah_hpp_id',
        'kelompok_hpp_id',
        'tahapan_pembangunan_id',
        'nama_pekerjaan',
        'barang_material_id',
        'volume',
        'satuan',
        'harga_satuan',
        'jumlah_rab',
        'urutan',
    ];

    protected $casts = [
        'volume' => 'float',
        'harga_satuan' => 'float',
        'jumlah_rab' => 'float',
        'urutan' => 'integer',
    ];

    public function detailRumahHpp(): BelongsTo
    {
        return $this->belongsTo(DetailRumahHpp::class);
    }

    public function kelompokHpp(): BelongsTo
    {
        return $this->belongsTo(KelompokHpp::class);
    }

    public function tahapanPembangunan(): BelongsTo
    {
        return $this->belongsTo(TahapanPembangunan::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }
}
