<?php

namespace App\Http\Controllers\Admin\Management\CabangPerusahaan\Logic;

use Illuminate\Foundation\Http\FormRequest;

class CabangPerusahaanPayload
{
    public function fromRequest(FormRequest $request): array
    {
        $payload = $request->validated();

        foreach (['logo', 'image'] as $field) {
            if ($request->hasFile($field)) {
                $payload[$field] = $request->file($field)->store("cabang-perusahaan/{$field}", 'public');
                continue;
            }

            if (($payload[$field] ?? '') === '') {
                $payload[$field] = null;
            }
        }

        return $payload;
    }
}
