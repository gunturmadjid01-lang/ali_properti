<?php

namespace App\Services;

use App\Models\User;
use App\Jobs\StoreAppNotificationJob;
use Illuminate\Support\Facades\Cache;

class AppNotificationService
{
    public function toRole(string $role, string $title, ?string $message = null, ?string $url = null): void
    {
        StoreAppNotificationJob::dispatch(null, $role, $title, $message, $url)->afterCommit();
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

        StoreAppNotificationJob::dispatch((int) $user->id, null, $title, $message, $url)->afterCommit();
        $this->flushSidebarCache($user->id);
    }

    public function flushSidebarCache(?int $userId = null): void
    {
        if ($userId !== null) {
            Cache::forget($this->cacheKey($userId));
        }
    }

    private function cacheKey(int $userId): string
    {
        return 'sidebar-notifications:'.$userId;
    }
}
