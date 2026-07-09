<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use App\Models\SpkKontraktor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteSchedule extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_jadwal', 'batch_code', 'perumahan_id', 'detail_rumah_id', 'spk_kontraktor_id', 'spk_plan_json', 'tahapan_pembangunan_id',
        'nama_pekerjaan', 'tanggal_mulai', 'tanggal_target', 'target_progress',
        'realisasi_progress', 'status', 'kendala', 'catatan', 'record_status',
        'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_target' => 'date',
        'target_progress' => 'float',
        'realisasi_progress' => 'float',
        'locked_at' => 'datetime',
        'spk_plan_json' => 'array',
    ];

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function spkKontraktor(): BelongsTo
    {
        return $this->belongsTo(SpkKontraktor::class);
    }

    public function tahapanPembangunan(): BelongsTo
    {
        return $this->belongsTo(TahapanPembangunan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SiteScheduleAllocation::class);
    }
}
