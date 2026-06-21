<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailPerumahanHpp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'perumahan_hpp_id',
        'kelompok_hpp_id',
        'barang_material_id',
        'volume',
        'satuan',
        'harga_satuan',
        'jumlah_rab',
    ];

    protected $casts = [
        'volume' => 'float',
        'harga_satuan' => 'float',
        'jumlah_rab' => 'float',
    ];

    protected static function booted(): void
    {
        static::deleting(function (DetailPerumahanHpp $detailPerumahanHpp) {
            $detailPerumahanHpp->realisasiHpps()->get()->each->delete();
        });
    }

    public function perumahanHpp(): BelongsTo
    {
        return $this->belongsTo(PerumahanHpp::class);
    }

    public function kelompokHpp(): BelongsTo
    {
        return $this->belongsTo(KelompokHpp::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function realisasiHpps(): HasMany
    {
        return $this->hasMany(RealisasiHpp::class);
    }
}
