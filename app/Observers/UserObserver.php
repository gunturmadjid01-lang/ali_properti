<?php

namespace App\Observers;

use App\Models\User;
use App\Services\UserPettyCashService;

class UserObserver
{
    public function created(User $user): void
    {
        app(UserPettyCashService::class)->ensureFor($user);
    }
}
