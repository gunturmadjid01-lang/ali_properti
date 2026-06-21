<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingTarget extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'user_id', 'tahun', 'bulan', 'target_lead', 'target_survey', 'target_spr',
        'target_closing', 'target_nilai_penjualan', 'catatan', 'record_status',
        'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'target_nilai_penjualan' => 'float',
        'locked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
