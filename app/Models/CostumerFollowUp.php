<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostumerFollowUp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketing_lead_id',
        'costumer_id',
        'user_id',
        'tanggal_follow_up',
        'followed_up_at',
        'metode_follow_up',
        'status_serius',
        'progress_kemampuan',
        'result_code',
        'interest_level',
        'status',
        'catatan',
        'obstacle',
        'next_action',
        'attachment_path',
        'rencana_follow_up_at',
        'admin_review_status',
        'admin_review_note',
        'admin_reviewed_by',
        'admin_reviewed_at',
    ];

    protected $casts = [
        'tanggal_follow_up' => 'date',
        'followed_up_at' => 'datetime',
        'rencana_follow_up_at' => 'date',
        'status_serius' => 'boolean',
        'admin_reviewed_at' => 'datetime',
    ];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
