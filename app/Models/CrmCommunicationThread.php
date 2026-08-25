<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmCommunicationThread extends Model
{
    protected $table = 'crm_communication_threads';

    protected $fillable = [
        'thread_no', 'channel', 'external_key', 'contact_name', 'contact_address',
        'status', 'last_message_at', 'assigned_to',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmCommunicationMessage::class, 'thread_id');
    }
}
