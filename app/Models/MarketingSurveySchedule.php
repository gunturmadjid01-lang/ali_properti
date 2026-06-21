<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingSurveySchedule extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_survey',
        'costumer_id',
        'perumahan_id',
        'detail_rumah_id',
        'marketing_id',
        'tanggal_survey',
        'metode_survey',
        'status',
        'hasil_survey',
        'catatan',
        'rencana_follow_up_at',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_survey' => 'datetime',
        'rencana_follow_up_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_id');
    }
}
