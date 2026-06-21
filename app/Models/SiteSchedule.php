<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteSchedule extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_jadwal', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id',
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
}
