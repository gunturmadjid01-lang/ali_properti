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
        'costumer_id',
        'user_id',
        'tanggal_follow_up',
        'metode_follow_up',
        'status_serius',
        'progress_kemampuan',
        'status',
        'catatan',
        'rencana_follow_up_at',
    ];

    protected $casts = [
        'tanggal_follow_up' => 'date',
        'rencana_follow_up_at' => 'date',
        'status_serius' => 'boolean',
    ];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
