<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HppRealisasi extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'target_type',
        'target_id',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'kelompok_hpp_id',
        'detail_rumah_hpp_item_id',
        'source_type',
        'source_id',
        'sumber_type',
        'sumber_id',
        'tanggal',
        'nominal',
        'keterangan',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'float',
    ];

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function sumber(): MorphTo
    {
        return $this->morphTo();
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function kelompokHpp(): BelongsTo
    {
        return $this->belongsTo(KelompokHpp::class);
    }

    public function tahapanPembangunan(): BelongsTo
    {
        return $this->belongsTo(TahapanPembangunan::class);
    }

    public function detailRumahHppItem(): BelongsTo
    {
        return $this->belongsTo(DetailRumahHppItem::class);
    }
}
