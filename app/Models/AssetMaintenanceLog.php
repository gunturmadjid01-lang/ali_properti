<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetMaintenanceLog extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_servis', 'office_asset_id', 'tanggal_servis', 'jenis_servis',
        'hour_meter', 'odometer', 'pekerjaan_servis', 'sparepart', 'biaya',
        'teknisi', 'jadwal_berikutnya', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_servis' => 'date',
        'jadwal_berikutnya' => 'date',
        'hour_meter' => 'float',
        'odometer' => 'float',
        'biaya' => 'float',
    ];

    public function asset(): BelongsTo { return $this->belongsTo(OfficeAsset::class, 'office_asset_id'); }
}
