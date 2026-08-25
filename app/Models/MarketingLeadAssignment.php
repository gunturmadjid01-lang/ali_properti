<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLeadAssignment extends Model
{
    protected $fillable = ['marketing_lead_id', 'costumer_id', 'from_marketing_id', 'to_marketing_id', 'reason', 'status', 'assigned_by', 'assigned_at', 'response_due_at', 'responded_at', 'response_note'];

    protected $casts = ['assigned_at' => 'datetime', 'response_due_at' => 'datetime', 'responded_at' => 'datetime'];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }

    public function fromMarketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_marketing_id');
    }

    public function toMarketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_marketing_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
