<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public string $connection = 'database';

    public string $broadcastQueue = 'chat';

    public function __construct(
        public ChatConversation $conversation,
        public string $action,
        public ?array $message = null,
    ) {}

    public function broadcastOn(): array
    {
        if ($this->conversation->type === 'global') {
            return [new PrivateChannel('chat.global')];
        }

        return $this->conversation->participants()
            ->pluck('users.id')
            ->map(fn ($id) => new PrivateChannel('chat.user.'.$id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'chat.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'conversation_type' => $this->conversation->type,
            'action' => $this->action,
            'message' => $this->message,
        ];
    }
}
