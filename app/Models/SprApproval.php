<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprApproval extends Model
{
    protected $fillable = [
        'spr_id',
        'user_id',
        'level',
        'status',
        'catatan',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
