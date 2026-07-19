<?php

namespace App\Http\Requests\Admin\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSprRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = (string) $this->input('metode_pembayaran');
        $isBankKpr = $method === 'kpr_bank';
        $isDeveloperKpr = $method === 'kpr_developer';
        $isInstallmentCash = $method === 'cash_bertahap';
        $isKpr = $isBankKpr || $isDeveloperKpr;

        return [
            'housing_reservation_id' => ['nullable', 'integer', 'exists:housing_reservations,id'],
            'costumer_id' => ['required', 'integer', 'exists:costumers,id'],
            'detail_rumah_id' => ['required', 'integer', 'exists:detail_rumahs,id'],
            'tanggal_spr' => ['required', 'date'],
            'metode_pembayaran' => ['required', Rule::in(['cash', 'cash_bertahap', 'kpr_bank', 'kpr_developer'])],

            'bank_kredit_id' => [Rule::requiredIf($isBankKpr), 'nullable', 'integer', 'exists:bank_kredits,id'],
            'bank_branch_id' => [Rule::requiredIf($isBankKpr), 'nullable', 'integer', 'exists:bank_branches,id'],
            'bank_credit_product_id' => [
                Rule::requiredIf($isBankKpr),
                'nullable',
                'integer',
                Rule::exists('bank_credit_products', 'id')->where(
                    fn ($query) => $query
                        ->where('bank_kredit_id', $this->input('bank_kredit_id'))
                        ->where('status', 'aktif'),
                ),
            ],
            'cash_installment_scheme_id' => [Rule::requiredIf($isInstallmentCash), 'nullable', 'integer', 'exists:cash_installment_schemes,id'],
            'developer_kpr_product_id' => [Rule::requiredIf($isDeveloperKpr), 'nullable', 'integer', 'exists:developer_kpr_products,id'],
            'kpr_tenor_bulan' => [Rule::requiredIf($isKpr), 'nullable', 'integer', 'min:1'],
            'kpr_bunga_tahunan' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'harga_jual' => ['required', 'numeric', 'min:0'],
            'booking_fee' => [Rule::requiredIf($isInstallmentCash), 'nullable', 'numeric', 'min:0'],
            'booking_fee_includes_dp' => ['nullable', 'boolean'],
            'tanggal_pembayaran_booking_fee' => ['nullable', 'date'],
            'uang_muka' => [Rule::requiredIf($isInstallmentCash || $isKpr), 'nullable', 'numeric', 'min:0'],
            'uang_muka_jumlah_pembayaran' => ['nullable', 'integer', 'min:1'],
            'tanggal_jatuh_tempo_dp' => ['nullable', 'date'],
            'nilai_pengajuan_kpr' => [Rule::requiredIf($isKpr), 'nullable', 'numeric', 'min:0'],

            'penambahan_tanah' => ['nullable', 'numeric', 'min:0'],
            'harga_penambahan_tanah' => ['nullable', 'numeric', 'min:0'],
            'penambahan_lain_lain' => ['nullable', 'string'],
            'harga_penambahan_lain_lain' => ['nullable', 'numeric', 'min:0'],
            'jumlah_termin' => ['nullable', 'integer', 'min:1'],
            'tanggal_jatuh_tempo_angsuran' => [Rule::requiredIf($isInstallmentCash), 'nullable', 'date'],
            'catatan' => ['nullable', 'string'],

            // Daftar dokumen boleh kosong. Dokumen yang benar-benar wajib diperiksa
            // terhadap master kategori SPR/metode pembayaran di SprController.
            'berkas' => ['nullable', 'array'],
            'berkas.*.dokumen_costumer_id' => ['required', 'integer', 'exists:dokumen_costumers,id'],
            'berkas.*.customer_document_id' => ['nullable', 'integer', 'exists:customer_documents,id'],
            'berkas.*.selected' => ['nullable', 'boolean'],
            'berkas.*.file_upload' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
            'berkas.*.keterangan' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'costumer_id' => 'customer',
            'housing_reservation_id' => 'reservasi perumahan yang sudah dibayar',
            'detail_rumah_id' => 'unit rumah',
            'tanggal_spr' => 'tanggal SPR',
            'metode_pembayaran' => 'metode pembayaran',
            'bank_kredit_id' => 'bank kredit',
            'bank_branch_id' => 'cabang bank',
            'bank_credit_product_id' => 'produk kredit bank',
            'cash_installment_scheme_id' => 'skema Cash Bertahap',
            'developer_kpr_product_id' => 'produk KPR Developer',
            'kpr_tenor_bulan' => 'tenor KPR',
            'harga_jual' => 'harga jual unit',
            'booking_fee' => 'booking fee',
            'uang_muka' => 'uang muka',
            'nilai_pengajuan_kpr' => 'nilai pengajuan KPR',
            'tanggal_jatuh_tempo_angsuran' => 'tanggal jatuh tempo angsuran pertama',
            'berkas.*.dokumen_costumer_id' => 'jenis dokumen customer',
            'berkas.*.file_upload' => 'file dokumen customer',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'required_if' => ':Attribute wajib diisi untuk metode pembayaran yang dipilih.',
            'exists' => ':Attribute yang dipilih tidak valid atau sudah tidak tersedia.',
            'date' => ':Attribute harus berupa tanggal yang valid.',
            'numeric' => ':Attribute harus berupa nominal atau angka yang valid.',
            'integer' => ':Attribute harus berupa angka bulat.',
            'min' => ':Attribute belum memenuhi nilai minimum :min.',
            'mimes' => ':Attribute harus berformat PDF, JPG, PNG, WEBP, DOC, atau DOCX.',
            'berkas.*.file_upload.max' => 'Ukuran file dokumen customer maksimal 10 MB.',
        ];
    }
}
