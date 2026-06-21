<?php

namespace App\Http\Requests\Admin\Logistik;

use App\Models\KelompokHpp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransaksiLogistikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'jenis' => ['required', Rule::in(['masuk', 'keluar'])],
            'gudang_id' => ['nullable', 'exists:gudangs,id'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'kelompok_hpp_id' => [
                'required_if:jenis,keluar',
                'nullable',
                Rule::exists('kelompok_hpps', 'id')->where(fn ($query) => $query->whereIn('kategori', KelompokHpp::LOGISTIC_CATEGORIES)),
            ],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string', 'max:255'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
