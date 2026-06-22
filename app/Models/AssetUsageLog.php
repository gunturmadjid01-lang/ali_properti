<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetUsageLog extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_log', 'office_asset_id', 'asset_usage_request_id', 'used_by',
        'mulai_pakai', 'selesai_pakai', 'durasi_jam', 'hour_meter_awal',
        'hour_meter_akhir', 'odometer_awal', 'odometer_akhir', 'bbm_liter',
        'biaya_bbm', 'operator', 'kondisi_sebelum', 'kondisi_sesudah',
        'lokasi', 'pekerjaan', 'catatan', 'record_status', 'locked_at',
        'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'mulai_pakai' => 'datetime',
        'selesai_pakai' => 'datetime',
        'durasi_jam' => 'float',
        'hour_meter_awal' => 'float',
        'hour_meter_akhir' => 'float',
        'odometer_awal' => 'float',
        'odometer_akhir' => 'float',
        'bbm_liter' => 'float',
        'biaya_bbm' => 'float',
        'locked_at' => 'datetime',
    ];

    public function asset(): BelongsTo { return $this->belongsTo(OfficeAsset::class, 'office_asset_id'); }
    public function request(): BelongsTo { return $this->belongsTo(AssetUsageRequest::class, 'asset_usage_request_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'used_by'); }
}
