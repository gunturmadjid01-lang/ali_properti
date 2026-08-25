<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingActionPlan extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = ['action_no', 'costumer_id', 'marketing_id', 'perumahan_id', 'title', 'objective', 'expected_result', 'actual_result', 'priority', 'status', 'start_at', 'due_at', 'completed_at', 'blocker', 'supervisor_note', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by'];

    protected $casts = ['start_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime', 'locked_at' => 'datetime'];

    public function costumer(): BelongsTo { return $this->belongsTo(Costumer::class); }
    public function marketing(): BelongsTo { return $this->belongsTo(User::class, 'marketing_id'); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
}
