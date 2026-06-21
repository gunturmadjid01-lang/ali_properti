<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KprFollowUp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kpr_submission_id',
        'user_id',
        'tanggal_follow_up',
        'metode_follow_up',
        'status_kpr',
        'hasil_follow_up',
        'kendala',
        'tindak_lanjut',
        'catatan',
        'rencana_follow_up_at',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'tanggal_follow_up' => 'date',
        'rencana_follow_up_at' => 'date',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KprSubmission::class, 'kpr_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
