<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLeadIntakeRow extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'reviewed_at' => 'datetime'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SalesLeadImportBatch::class, 'batch_id');
    }

    public function duplicateCustomer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class, 'duplicate_costumer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class, 'costumer_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }

    public function duplicateLead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'duplicate_marketing_lead_id');
    }
}
