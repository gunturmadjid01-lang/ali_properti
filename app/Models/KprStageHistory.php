<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KprStageHistory extends Model
{
    protected $fillable = [
        'kpr_submission_id', 'tahapan', 'status', 'tanggal_status', 'catatan', 'user_id',
    ];

    protected $casts = [
        'tanggal_status' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KprSubmission::class, 'kpr_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
