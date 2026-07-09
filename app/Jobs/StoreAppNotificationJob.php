<?php

namespace App\Jobs;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $role,
        public readonly string $title,
        public readonly ?string $message = null,
        public readonly ?string $url = null,
    ) {
    }

    public function handle(): void
    {
        AppNotification::query()->create([
            'user_id' => $this->userId,
            'role' => $this->role,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ]);
    }
}
