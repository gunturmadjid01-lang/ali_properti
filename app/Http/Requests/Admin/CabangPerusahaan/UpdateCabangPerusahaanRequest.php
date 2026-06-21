<?php

namespace App\Http\Requests\Admin\CabangPerusahaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCabangPerusahaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_cabang' => ['required', 'string', 'max:255', Rule::unique('cabang_perusahaans', 'nama_cabang')->ignore($this->route('id'))],
            'address' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'string', 'max:255'],
            'longtitude' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', Rule::when($this->hasFile('logo'), ['image', 'max:2048'], ['string', 'max:255'])],
            'image' => ['nullable', Rule::when($this->hasFile('image'), ['image', 'max:2048'], ['string', 'max:255'])],
            'deskripsi' => ['nullable', 'string'],
            'emaiil' => ['required', 'email', 'max:255'],
            'manager_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ];
    }
}
