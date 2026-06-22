<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteManpowerLog extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_log', 'tanggal', 'perumahan_id', 'detail_rumah_id', 'spk_kontraktor_id',
        'sumber_tenaga_kerja', 'kontraktor', 'nama_mandor', 'mandor', 'tukang',
        'kenek', 'tipe_upah', 'nilai_upah', 'jam_kerja', 'jam_lembur',
        'alat_digunakan', 'pekerjaan', 'catatan', 'record_status', 'locked_at',
        'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'mandor' => 'integer',
        'tukang' => 'integer',
        'kenek' => 'integer',
        'nilai_upah' => 'float',
        'jam_kerja' => 'float',
        'jam_lembur' => 'float',
        'locked_at' => 'datetime',
    ];

    public function spkKontraktor(): BelongsTo { return $this->belongsTo(SpkKontraktor::class); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
}
