<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingReminder extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'costumer_id', 'marketing_lead_id', 'user_id', 'jenis', 'judul', 'remind_at', 'status', 'catatan',
        'source_type', 'source_id', 'completed_at', 'created_by', 'updated_by',
    ];

    public function lead()
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }

    protected $casts = [
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
