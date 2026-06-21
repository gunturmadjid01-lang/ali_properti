<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCommission extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_komisi', 'spr_id', 'user_id', 'dasar_perhitungan', 'persentase',
        'nominal', 'status', 'tanggal_jatuh_tempo', 'tanggal_dibayar', 'catatan',
        'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'dasar_perhitungan' => 'float',
        'persentase' => 'float',
        'nominal' => 'float',
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_dibayar' => 'date',
        'locked_at' => 'datetime',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
