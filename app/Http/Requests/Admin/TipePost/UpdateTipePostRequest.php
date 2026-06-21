<?php

namespace App\Http\Requests\Admin\TipePost;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTipePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_post' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'debit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }
}
