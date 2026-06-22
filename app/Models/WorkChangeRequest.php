<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkChangeRequest extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_perubahan', 'tanggal', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id',
        'spk_kontraktor_id', 'jenis_perubahan', 'uraian_perubahan', 'alasan', 'estimasi_biaya',
        'estimasi_hari', 'status', 'approval_status', 'approved_by', 'approved_at',
        'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'estimasi_biaya' => 'float',
        'estimasi_hari' => 'integer',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function spkKontraktor(): BelongsTo { return $this->belongsTo(SpkKontraktor::class); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function tahapanPembangunan(): BelongsTo { return $this->belongsTo(TahapanPembangunan::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
