<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeAsset extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_aset', 'nama_aset', 'kategori', 'tipe_aset', 'nomor_seri', 'plat_nomor',
        'lokasi_sekarang', 'kondisi', 'status', 'nilai_aset', 'hour_meter_terakhir',
        'odometer_terakhir', 'penanggung_jawab_id', 'catatan', 'record_status',
        'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'nilai_aset' => 'float',
        'hour_meter_terakhir' => 'float',
        'odometer_terakhir' => 'float',
        'locked_at' => 'datetime',
    ];

    public function penanggungJawab(): BelongsTo { return $this->belongsTo(User::class, 'penanggung_jawab_id'); }
    public function usageRequests(): HasMany { return $this->hasMany(AssetUsageRequest::class); }
    public function usageLogs(): HasMany { return $this->hasMany(AssetUsageLog::class); }
    public function maintenanceLogs(): HasMany { return $this->hasMany(AssetMaintenanceLog::class); }
}
