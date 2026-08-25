<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLeadActivity extends Model
{
    protected $fillable = [
        'costumer_id',
        'user_id',
        'activity_type',
        'title',
        'status_from',
        'status_to',
        'source_type',
        'source_id',
        'activity_at',
        'note',
        'metadata',
        'source_url',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'activity_at' => 'datetime',
        'metadata' => 'array',
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
