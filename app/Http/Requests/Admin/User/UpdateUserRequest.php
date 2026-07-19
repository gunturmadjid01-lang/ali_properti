<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('profile_section')) {
            $this->merge(['profile_section' => $this->boolean('has_login_access') ? 'marketing' : 'pegawai']);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = \App\Models\User::query()->find($this->route('id'));
        $employee = $this->input('profile_section') === 'pegawai' || $this->boolean('create_employee_profile');

        return [
            'profile_section' => ['required', Rule::in(['marketing', 'manager', 'manajer_pimpro', 'pengawas', 'keuangan', 'gudang', 'admin', 'owner', 'petugas', 'super_admin', 'pegawai'])],
            'create_employee_profile' => ['nullable', 'boolean'],
            'kantor_cabang_id' => ['nullable', 'exists:cabang_perusahaans,id'],
            'gudang_id' => [Rule::requiredIf($this->input('profile_section') === 'gudang'), 'nullable', 'exists:gudangs,id'],
            'gudang_ids' => ['nullable', 'array'],
            'gudang_ids.*' => ['exists:gudangs,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'employee_number' => [Rule::requiredIf($employee), 'nullable', 'string', 'max:50', Rule::unique('users', 'employee_number')->ignore($this->route('id'))],
            'job_title' => [Rule::requiredIf($employee), 'nullable', 'string', 'max:100'],
            'join_date' => [Rule::requiredIf($employee), 'nullable', 'date'],
            'employment_type' => [Rule::requiredIf($employee), 'nullable', Rule::in(['tetap', 'kontrak', 'harian', 'magang'])],
            'employment_status' => [Rule::requiredIf($employee), 'nullable', Rule::in(['aktif', 'nonaktif', 'resign'])],
            'has_login_access' => ['nullable', 'boolean'],
            'attendance_pin' => ['nullable', Rule::requiredIf($employee && blank($user?->attendance_pin)), 'digits:8'],
            'email' => [Rule::requiredIf(! $employee), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'password' => ['nullable', Rule::requiredIf(! $employee && blank($user?->password)), 'string', 'min:8'],
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
