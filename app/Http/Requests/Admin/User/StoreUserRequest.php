<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kantor_cabang_id' => ['nullable', 'exists:cabang_perusahaans,id'],
            'gudang_id' => ['nullable', 'exists:gudangs,id'],
            'gudang_ids' => ['nullable', 'array'],
            'gudang_ids.*' => ['exists:gudangs,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:users,employee_number'],
            'job_title' => ['required', 'string', 'max:100'],
            'join_date' => ['required', 'date'],
            'employment_type' => ['required', Rule::in(['tetap', 'kontrak', 'harian', 'magang'])],
            'employment_status' => ['required', Rule::in(['aktif', 'nonaktif', 'resign'])],
            'has_login_access' => ['required', 'boolean'],
            'email' => ['nullable', Rule::requiredIf($this->boolean('has_login_access')), 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', Rule::requiredIf($this->boolean('has_login_access')), 'string', 'min:8'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bpjs_health_number' => ['nullable', 'string', 'max:50'],
            'bpjs_employment_number' => ['nullable', 'string', 'max:50'],
            'payroll_bank_name' => ['nullable', 'string', 'max:100'],
            'payroll_bank_account' => ['nullable', 'string', 'max:100'],
            'payroll_bank_holder' => ['nullable', 'string', 'max:100'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'perumahan_ids' => ['nullable', 'array'],
            'perumahan_ids.*' => ['exists:perumahans,id'],
        ];
    }
}
