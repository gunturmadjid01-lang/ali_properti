<?php

namespace App\Http\Controllers\Admin\Management\RolePermission\Logic;

use Illuminate\Foundation\Http\FormRequest;

class RolePermissionPayload
{
    public function fromRequest(FormRequest $request): array
    {
        return collect($request->validated())
            ->put('guard_name', 'web')
            ->toArray();
    }
}
