<?php

namespace App\Http\Requests\Admin\KelompokHpp;

use App\Models\KelompokHpp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKelompokHppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_hpp' => ['required', 'string', 'max:255', Rule::unique('kelompok_hpps', 'nama_hpp')->ignore($this->route('id'))],
            'kategori' => ['required', Rule::in(array_keys(KelompokHpp::CATEGORY_LABELS))],
            'status' => ['required', 'in:aktif,nonaktif'],
        ];
    }
}
