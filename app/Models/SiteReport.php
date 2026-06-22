<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteReport extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_laporan', 'jenis_laporan', 'tanggal', 'periode_mulai', 'periode_selesai',
        'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'cuaca',
        'site_schedule_id', 'progress_pembangunan_id', 'jumlah_pekerja', 'kontraktor', 'pekerjaan_selesai', 'pekerjaan_tertahan',
        'kendala', 'koordinasi', 'rencana_berikutnya', 'lampiran', 'approval_status',
        'approved_by', 'approved_at', 'record_status', 'locked_at', 'locked_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

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
