<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialUsage extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_pemakaian',
        'tanggal',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'progress_pembangunan_id',
        'material_request_id',
        'quality_upgrade_contract_id',
        'quality_upgrade_contract_item_id',
        'stock_posted_at',
        'keterangan',
        'foto',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['tanggal' => 'date', 'locked_at' => 'datetime', 'stock_posted_at' => 'datetime'];

    public function details(): HasMany
    {
        return $this->hasMany(MaterialUsageDetail::class);
    }

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

    public function progressPembangunan(): BelongsTo
    {
        return $this->belongsTo(ProgressPembangunan::class);
    }

    public function qualityUpgradeContract(): BelongsTo
    {
        return $this->belongsTo(QualityUpgradeContract::class);
    }
}
