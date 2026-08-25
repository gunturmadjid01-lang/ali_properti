<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingActivityContact extends Model
{
    protected $guarded = [];

    protected $casts = ['converted_at' => 'datetime'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(MarketingVisit::class, 'marketing_visit_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }
}
