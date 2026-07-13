<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'body', 'recalled_at',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    protected function casts(): array
    {
        return ['recalled_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(ChatMessageDeletion::class, 'message_id');
    }
}
