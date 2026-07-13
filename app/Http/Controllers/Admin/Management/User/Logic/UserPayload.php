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
        $payload['gudang_ids'] = $payload['gudang_ids'] ?? [];
        $payload['has_login_access'] = $request->boolean('has_login_access');

        foreach (['employee_number', 'email', 'tax_number', 'bpjs_health_number', 'bpjs_employment_number', 'payroll_bank_name', 'payroll_bank_account', 'payroll_bank_holder'] as $field) {
            if (array_key_exists($field, $payload) && blank($payload[$field])) {
                $payload[$field] = null;
            }
        }

        if (($payload['password'] ?? '') === '') {
            unset($payload['password']);
        }

        return $payload;
    }
}
