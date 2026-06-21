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
            'settings.*.action' => ['required', 'in:create,update,delete'],
            'settings.*.requires_approval' => ['nullable', 'boolean'],
            'settings.*.approver_role_ids' => ['nullable', 'array'],
            'settings.*.approver_role_ids.*' => ['exists:roles,id'],
        ];
    }
}
