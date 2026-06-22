<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternalHandover extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_serah_terima', 'tanggal', 'perumahan_id', 'detail_rumah_id',
        'progress_unit', 'kondisi_bangunan', 'checklist', 'catatan', 'status',
        'approval_status', 'approved_by', 'approved_at', 'record_status', 'locked_at',
        'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'progress_unit' => 'float',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
