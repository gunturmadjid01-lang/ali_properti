<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class AppNotificationService
{
    public function toRole(string $role, string $title, ?string $message = null, ?string $url = null): void
    {
        AppNotification::query()->create([
            'role' => $role,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    public function toRoles(array $roles, string $title, ?string $message = null, ?string $url = null): void
    {
        foreach ($roles as $role) {
            $this->toRole($role, $title, $message, $url);
        }
    }

    public function toUser(?User $user, string $title, ?string $message = null, ?string $url = null): void
    {
        if (! $user) {
            return;
        }

        AppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }
}
