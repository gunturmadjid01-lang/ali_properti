<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequest extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_MENUNGGU_OWNER = 'menunggu_approval_owner';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_MENUNGGU_STOK = 'menunggu_stok';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'kode_request',
        'tanggal',
        'gudang_id',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'site_schedule_id',
        'progress_diakui',
        'progress_pembangunan_id',
        'status',
        'keterangan',
        'requested_by',
        'processed_by',
        'processed_at',
        'approved_by_gudang',
        'approved_at_gudang',
        'approval_note',
        'approved_by_owner',
        'approved_at_owner',
        'owner_approval_note',
        'issued_by',
        'issued_at',
        'transaksi_logistik_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'processed_at' => 'datetime',
        'approved_at_gudang' => 'datetime',
        'approved_at_owner' => 'datetime',
        'issued_at' => 'datetime',
        'progress_diakui' => 'float',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(MaterialRequestDetail::class);
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

    public function siteSchedule(): BelongsTo
    {
        return $this->belongsTo(SiteSchedule::class);
    }

    public function progressPembangunan(): BelongsTo
    {
        return $this->belongsTo(ProgressPembangunan::class);
    }

    public function approvedByGudang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_gudang');
    }

    public function approvedByOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_owner');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function transaksiLogistik(): BelongsTo
    {
        return $this->belongsTo(TransaksiLogistik::class);
    }
}
