<?php

namespace App\Http\Requests\Admin\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCostumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marketing_lead_source_id' => ['nullable', 'exists:marketing_lead_sources,id'],
            'marketing_campaign_id' => ['nullable', 'exists:marketing_campaigns,id'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'string', 'max:255'],
            'jenis_identitas' => ['required', 'string', 'max:255'],
            'no_identitas' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'status_perkawinan' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:255'],
            'file_identitas' => ['nullable', 'string', 'max:255'],
            'penghasilan' => ['nullable', 'numeric', 'min:0'],
            'pengeluaran_bulanan' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
            'employment_category' => ['required', 'in:pns,tni_polri,bumn,pegawai_swasta,wiraswasta,profesional,pensiunan,lainnya'],
            'nama_perusahaan' => ['nullable', 'string', 'max:255'],
            'alamat_perusahaan' => ['nullable', 'string'],
            'telepon_perusahaan' => ['nullable', 'string', 'max:255'],
            'keterangan_perusahaan' => ['nullable', 'string'],
            'nama_lengkap_pasangan' => ['nullable', 'string', 'max:255'],
            'jenis_kelamin_pasangan' => ['nullable', 'string', 'max:255'],
            'jenis_identitas_pasangan' => ['nullable', 'string', 'max:255'],
            'no_identitas_pasangan' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir_pasangan' => ['nullable', 'date'],
            'tempat_lahir_pasangan' => ['nullable', 'string', 'max:255'],
            'pekerjaan_pasangan' => ['nullable', 'string', 'max:255'],
            'penghasilan_pasangan' => ['nullable', 'numeric', 'min:0'],
            'pengeluaran_bulanan_pasangan' => ['nullable', 'numeric', 'min:0'],
            'daftar_cicilan' => ['nullable', 'array', 'max:25'],
            'daftar_cicilan.*.pemilik' => ['nullable', 'in:konsumen,pasangan'],
            'daftar_cicilan.*.jenis' => ['nullable', 'string', 'max:100'],
            'daftar_cicilan.*.kreditur' => ['nullable', 'string', 'max:150'],
            'daftar_cicilan.*.angsuran_bulanan' => ['nullable', 'numeric', 'min:0'],
            'daftar_cicilan.*.sisa_pokok' => ['nullable', 'numeric', 'min:0'],
            'daftar_cicilan.*.tanggal_selesai' => ['nullable', 'date'],
        ];
    }
}
