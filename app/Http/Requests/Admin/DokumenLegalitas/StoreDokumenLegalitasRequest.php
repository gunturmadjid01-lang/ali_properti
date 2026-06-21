<?php

namespace App\Http\Requests\Admin\DokumenLegalitas;

use Illuminate\Foundation\Http\FormRequest;

class StoreDokumenLegalitasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'nama_dokument' => ['required', 'string', 'max:255'],
            'nomor_dokument' => ['required', 'string', 'max:255'],
            'tanggal_terbit' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date', 'after_or_equal:tanggal_terbit'],
            'file' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }
}
