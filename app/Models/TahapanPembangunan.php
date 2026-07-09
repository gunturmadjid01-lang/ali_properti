<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahapanPembangunan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_tahapan',
        'bobot_persen',
        'urutan',
        'status',
        'konteks',
        'perumahan_id',
        'detail_rumah_id',
    ];

    protected $casts = [
        'bobot_persen' => 'float',
        'urutan' => 'integer',
    ];

    public function progressPembangunans(): HasMany
    {
        return $this->hasMany(ProgressPembangunan::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function detailPerumahanHpps(): HasMany
    {
        return $this->hasMany(DetailPerumahanHpp::class);
    }

    public function detailRumahHppItems(): HasMany
    {
        return $this->hasMany(DetailRumahHppItem::class);
    }
}
