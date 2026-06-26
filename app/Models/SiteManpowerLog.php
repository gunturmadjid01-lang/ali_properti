<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteManpowerLog extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_log', 'tanggal', 'perumahan_id', 'detail_rumah_id', 'spk_kontraktor_id',
        'sumber_tenaga_kerja', 'kontraktor', 'nama_mandor', 'mandor', 'tukang',
        'kenek', 'tipe_upah', 'jumlah_periode', 'tarif_mandor', 'tarif_tukang',
        'tarif_kenek', 'nilai_borongan', 'nilai_upah', 'jam_kerja', 'jam_lembur',
        'tarif_lembur', 'sumber_alat', 'alat_digunakan', 'penyedia_alat',
        'biaya_sewa_alat', 'pekerjaan', 'catatan', 'record_status', 'locked_at',
        'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'mandor' => 'integer',
        'tukang' => 'integer',
        'kenek' => 'integer',
        'jumlah_periode' => 'float',
        'tarif_mandor' => 'float',
        'tarif_tukang' => 'float',
        'tarif_kenek' => 'float',
        'nilai_borongan' => 'float',
        'nilai_upah' => 'float',
        'jam_kerja' => 'float',
        'jam_lembur' => 'float',
        'tarif_lembur' => 'float',
        'biaya_sewa_alat' => 'float',
        'locked_at' => 'datetime',
    ];

    public function spkKontraktor(): BelongsTo { return $this->belongsTo(SpkKontraktor::class); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function officeAssets(): BelongsToMany { return $this->belongsToMany(OfficeAsset::class)->withTimestamps(); }
}
