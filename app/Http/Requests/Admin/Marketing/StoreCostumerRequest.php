<?php

namespace App\Http\Requests\Admin\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreCostumerRequest extends FormRequest
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
            'lead_priority' => ['nullable', 'in:low,normal,high,urgent'],
            'interest_level' => ['nullable', 'string', 'max:30'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'gte:budget_min'],
            'preferred_payment_method' => ['nullable', 'in:cash,cash_installment,kpr'],
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
            'unit_interests' => ['nullable', 'array', 'max:10'],
            'unit_interests.*.detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'unit_interests.*.perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'unit_interests.*.interest_level' => ['nullable', 'string', 'max:30'],
            'unit_interests.*.payment_plan' => ['nullable', 'string', 'max:50'],
            'unit_interests.*.budget_min' => ['nullable', 'numeric', 'min:0'],
            'unit_interests.*.budget_max' => ['nullable', 'numeric', 'min:0'],
            'unit_interests.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
