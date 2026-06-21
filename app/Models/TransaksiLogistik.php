<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransaksiLogistik extends Model
{
    use HasFactory, SoftDeletes;

    public const JENIS_MASUK = 'masuk';

    public const JENIS_KELUAR = 'keluar';

    protected $fillable = [
        'kode_transaksi',
        'gudang_id',
        'tanggal',
        'jenis',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'kelompok_hpp_id',
        'total_nominal',
        'keterangan',
        'source_type',
        'source_id',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_nominal' => 'float',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(TransaksiLogistikDetail::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
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
}
