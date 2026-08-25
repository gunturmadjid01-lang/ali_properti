<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCommunicationMessage extends Model
{
    protected $table = 'crm_communication_messages';

    protected $fillable = [
        'thread_id', 'message_key', 'direction', 'sender_address', 'recipient_address',
        'body', 'template_code', 'provider', 'provider_message_id', 'status',
        'failure_reason', 'metadata', 'sent_at', 'read_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'sent_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CrmCommunicationThread::class, 'thread_id');
    }
}
