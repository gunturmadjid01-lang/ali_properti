<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldDefect extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_defect', 'tanggal', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id',
        'progress_pembangunan_id', 'quality_inspection_id', 'kategori', 'prioritas', 'temuan', 'instruksi_perbaikan',
        'target_selesai', 'tanggal_selesai', 'status', 'foto', 'approval_status',
        'approved_by', 'approved_at', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'target_selesai' => 'date',
        'tanggal_selesai' => 'date',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $sync = fn (FieldDefect $row) => $row->detail_rumah_id ? app(\App\Services\SalesProcessService::class)->syncLinkedUnitData((int) $row->detail_rumah_id) : null;
        static::saved($sync);
        static::deleted($sync);
    }

    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function tahapanPembangunan(): BelongsTo { return $this->belongsTo(TahapanPembangunan::class); }
    public function qualityInspection(): BelongsTo { return $this->belongsTo(QualityInspection::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
