<?php

namespace App\Http\Controllers\Admin\Management\User\Logic;

use Illuminate\Foundation\Http\FormRequest;

class UserPayload
{
    public function fromRequest(FormRequest $request): array
    {
        $payload = $request->validated();

        $payload['role_ids'] = $payload['role_ids'] ?? [];
        $payload['perumahan_ids'] = $payload['perumahan_ids'] ?? [];

        if (($payload['password'] ?? '') === '') {
            unset($payload['password']);
        }

        return $payload;
    }
}
