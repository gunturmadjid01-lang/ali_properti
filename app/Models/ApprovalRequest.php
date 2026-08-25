<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'module_key',
        'module_label',
        'action',
        'model_type',
        'model_id',
        'before_data',
        'after_data',
        'status',
        'current_step',
        'total_steps',
        'step_history',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'note',
        'rejection_note',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'reviewed_at' => 'datetime',
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'step_history' => 'array',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
