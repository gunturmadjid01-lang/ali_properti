<?php

namespace App\Http\Requests\Admin\Approval;

use Illuminate\Foundation\Http\FormRequest;

class RejectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
