<?php

namespace App\Http\Requests\Admin\MasterBank;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'nama_bank' => ['required', 'string', 'max:255'],
            'nomor_rekening' => ['nullable', 'string', 'max:255'],
            'nama_rekening' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }
}
