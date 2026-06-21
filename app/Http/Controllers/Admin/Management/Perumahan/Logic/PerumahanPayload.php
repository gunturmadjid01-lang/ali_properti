<?php

namespace App\Http\Controllers\Admin\Management\Perumahan\Logic;

use Illuminate\Foundation\Http\FormRequest;

class PerumahanPayload
{
    public function fromRequest(FormRequest $request): array
    {
        $payload = $request->validated();

        if ($request->hasFile('logo')) {
            $payload['logo'] = $request->file('logo')->store('perumahan/logo', 'public');
        }

        if (($payload['logo'] ?? '') === '') {
            $payload['logo'] = null;
        }

        foreach (['total_blok', 'harga_mulai'] as $key) {
            if (($payload[$key] ?? '') === '') {
                $payload[$key] = 0;
            }
        }

        return $payload;
    }
}
