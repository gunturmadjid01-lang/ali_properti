<?php

namespace App\Http\Requests\Admin\DokumenCostumer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDokumenCostumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'kategori_pengajuan' => ['required', Rule::in(['umum', 'spr', 'kpr', 'cash', 'bertahap'])],
            'wajib' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }
}
