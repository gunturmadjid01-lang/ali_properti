<?php

namespace App\Http\Requests\Admin\PerumahanHpp;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerumahanHppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.kelompok_hpp_id' => ['nullable', 'exists:kelompok_hpps,id'],
            'items.*.tahapan_pembangunan_id' => ['required', 'exists:tahapan_pembangunans,id'],
            'items.*.nama_pekerjaan' => ['required', 'string', 'max:255'],
            'items.*.barang_material_id' => ['nullable', 'exists:barang_materials,id'],
            'items.*.volume' => ['required', 'numeric', 'min:0'],
            'items.*.satuan' => ['nullable', 'string', 'max:255'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'items.*.urutan' => ['nullable', 'integer', 'min:0'],
            'target_detail_rumah_ids' => ['nullable', 'array'],
            'target_detail_rumah_ids.*' => ['required', 'exists:detail_rumahs,id'],
        ];
    }
}
