<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialReturn extends Model
{
    use HasUserAudit, SoftDeletes;

    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DITERIMA = 'diterima_gudang';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'kode_pengembalian',
        'tanggal',
        'gudang_id',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'status',
        'keterangan',
        'receive_note',
        'received_by',
        'received_at',
        'transaksi_logistik_id',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['tanggal' => 'date', 'received_at' => 'datetime', 'locked_at' => 'datetime'];

    public function details(): HasMany
    {
        return $this->hasMany(MaterialReturnDetail::class);
    }

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

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
