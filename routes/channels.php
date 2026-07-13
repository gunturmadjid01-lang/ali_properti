<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.global', fn ($user) => (bool) $user);
Broadcast::channel('chat.user.{id}', fn ($user, int $id) => (int) $user->id === $id);
Broadcast::channel('chat.online', fn ($user) => [
    'id' => $user->id,
    'name' => $user->name,
]);
