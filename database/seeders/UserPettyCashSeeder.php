<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserPettyCashService;
use Illuminate\Database\Seeder;

class UserPettyCashSeeder extends Seeder
{
    public function run(UserPettyCashService $service): void
    {
        User::query()->orderBy('id')->each(fn (User $user) => $service->ensureFor($user));
    }
}
