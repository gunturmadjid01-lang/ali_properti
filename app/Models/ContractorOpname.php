<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractorOpname extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_opname', 'tanggal', 'spk_kontraktor_id', 'perumahan_id', 'detail_rumah_id',
        'tahapan_pembangunan_id', 'progress_pembangunan_id', 'pekerjaan', 'progress_diakui',
        'nilai_diajukan', 'nilai_disetujui', 'catatan', 'status', 'foto', 'approval_status',
        'approved_by', 'approved_at', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'progress_diakui' => 'float',
        'nilai_diajukan' => 'float',
        'nilai_disetujui' => 'float',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function spkKontraktor(): BelongsTo { return $this->belongsTo(SpkKontraktor::class); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function tahapanPembangunan(): BelongsTo { return $this->belongsTo(TahapanPembangunan::class); }
    public function progressPembangunan(): BelongsTo { return $this->belongsTo(ProgressPembangunan::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function spkPayments(): HasMany { return $this->hasMany(SpkKontraktorPayment::class); }
}
