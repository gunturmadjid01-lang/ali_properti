<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressPembangunan extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    protected $fillable = [
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'site_schedule_id',
        'nama_progress',
        'tanggal',
        'tahapan',
        'persentase',
        'persentase_total',
        'keterangan',
        'foto',
        'approval_status',
        'approved_by',
        'approved_at',
        'approved_note',
        'source_type',
        'source_id',
        'source_label',
        'record_status',
        'locked_at',
        'locked_by',
        'users_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tahapan' => 'float',
        'persentase' => 'float',
        'persentase_total' => 'float',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
