<?php

namespace App\Http\Requests\Admin\Approval;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApprovalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*.module_key' => ['required', 'string'],
            'settings.*.module_label' => ['required', 'string'],
            'settings.*.action' => ['required', 'in:lock'],
            'settings.*.requires_approval' => ['nullable', 'boolean'],
            'settings.*.approval_stages' => ['required', 'integer', 'between:0,3'],
            'settings.*.approver_role_ids' => ['nullable', 'array'],
            'settings.*.approver_role_ids.*' => ['exists:roles,id'],
            'settings.*.approval_steps' => ['nullable', 'array', 'max:3'],
            'settings.*.approval_steps.*.step' => ['required', 'integer', 'between:1,3'],
            'settings.*.approval_steps.*.role_ids' => ['required', 'array', 'min:1', 'max:1'],
            'settings.*.approval_steps.*.role_ids.*' => ['exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'Data setting approval tidak ditemukan.',
            'settings.*.approval_stages.required' => 'Jumlah tahap approval wajib dipilih.',
            'settings.*.approval_stages.between' => 'Jumlah tahap approval harus antara 0 sampai 3 tahap.',
            'settings.*.approval_steps.*.role_ids.required' => 'Role penanggung jawab tahap ini wajib dipilih.',
            'settings.*.approval_steps.*.role_ids.min' => 'Role penanggung jawab tahap ini wajib dipilih.',
            'settings.*.approval_steps.*.role_ids.max' => 'Setiap tahap hanya boleh memiliki satu role penanggung jawab.',
            'settings.*.approval_steps.*.role_ids.*.exists' => 'Role penanggung jawab yang dipilih tidak valid atau sudah dihapus.',
        ];
    }
}
