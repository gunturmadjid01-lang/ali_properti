<?php

namespace App\Http\Requests\Admin\DokumenLegalitasRumah;

use Illuminate\Foundation\Http\FormRequest;

class StoreDokumenLegalitasRumahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'tanggal_terbit' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date', 'after_or_equal:tanggal_terbit'],
            'file' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
        ];
    }
}
