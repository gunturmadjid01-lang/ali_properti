<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityInspection extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_inspeksi', 'tanggal', 'perumahan_id', 'detail_rumah_id',
        'tahapan_pembangunan_id', 'site_schedule_id', 'progress_pembangunan_id', 'hasil', 'item_pemeriksaan', 'temuan',
        'tindakan_perbaikan', 'target_selesai', 'foto', 'status', 'approval_status',
        'approved_by', 'approved_at', 'record_status', 'locked_at', 'locked_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'target_selesai' => 'date',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $sync = fn (QualityInspection $row) => $row->detail_rumah_id ? app(\App\Services\SalesProcessService::class)->syncLinkedUnitData((int) $row->detail_rumah_id) : null;
        static::saved($sync);
        static::deleted($sync);
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
