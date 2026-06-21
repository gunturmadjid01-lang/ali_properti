<?php

namespace App\Http\Requests\Admin\Perumahan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerumahanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cabang_id' => ['required', 'exists:cabang_perusahaans,id'],
            'kode_proyek' => ['nullable', 'string', 'max:255', Rule::unique('perumahans', 'kode_proyek')->ignore($this->route('id'))],
            'nama_perusahaan' => ['required', 'string', 'max:255'],
            'developer_name' => ['nullable', 'string', 'max:255'],
            'alamat' => ['required', 'string'],
            'latitude' => ['nullable', 'string', 'max:255'],
            'longtitude' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', Rule::when($this->hasFile('logo'), ['image', 'max:2048'], ['string', 'max:255'])],
            'luas_lahan' => ['required', 'string', 'max:255'],
            'luas_komersial' => ['nullable', 'string', 'max:255'],
            'luas_fasos_fasum' => ['nullable', 'string', 'max:255'],
            'jumlah_unit' => ['required', 'integer', 'min:0'],
            'total_blok' => ['nullable', 'integer', 'min:0'],
            'harga_mulai' => ['nullable', 'numeric', 'min:0'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_target_selesai' => ['nullable', 'date'],
            'jenis_sertifikat' => ['nullable', 'string', 'max:255'],
            'nomor_sertifikat_induk' => ['nullable', 'string', 'max:255'],
            'nama_marketing' => ['nullable', 'string', 'max:255'],
            'phone_marketing' => ['nullable', 'string', 'max:255'],
            'email_marketing' => ['nullable', 'email', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }
}
