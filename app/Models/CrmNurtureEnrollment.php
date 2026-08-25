<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNurtureEnrollment extends Model
{
    protected $guarded = [];
    protected $casts = ['next_run_at' => 'datetime', 'enrolled_at' => 'datetime', 'completed_at' => 'datetime'];
    public function sequence(): BelongsTo { return $this->belongsTo(CrmNurtureSequence::class, 'sequence_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(MarketingLead::class, 'marketing_lead_id'); }
}
