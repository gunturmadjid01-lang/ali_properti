<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\BankBranch;
use App\Models\BankCreditProduct;
use App\Models\BankCreditProductVersion;
use App\Models\BankHousingPartnership;
use App\Models\BankHousingPartnershipVersion;
use App\Models\BankKredit;
use App\Models\DocumentRequirementSet;
use App\Models\DokumenCostumer;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Seeder;

class BankCreditMasterSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = User::query()->value('id');
        $housings = Perumahan::query()->where('status', 'aktif')->get(['id', 'kode_proyek', 'nama_perusahaan']);

        foreach (BankKredit::query()->where('status', 'aktif')->get() as $bank) {
            $bank->update([
                'jenis_bank' => str_contains(strtolower($bank->nama_bank), 'syariah') ? 'syariah' : 'konvensional',
                'alamat_pusat' => $bank->alamat_pusat ?: 'Jakarta, Indonesia',
                'nomor_telepon' => $bank->nomor_telepon ?: '1500'.str_pad((string) $bank->id, 3, '0', STR_PAD_LEFT),
                'email' => $bank->email ?: 'kpr.'.strtolower(str_replace(['-KPR', ' '], '', $bank->kode_bank)).'@example.test',
                'catatan' => 'Data contoh master KPR Bank. Sesuaikan suku bunga, biaya, plafon, dan PKS sebelum dipakai pada produksi.',
            ]);

            $branch = BankBranch::withTrashed()->firstOrNew([
                'bank_kredit_id' => $bank->id,
                'branch_code' => 'MKS-UTM',
            ]);
            $branch->fill([
                'branch_name' => 'Cabang Makassar Utama',
                'address' => 'Jl. Jenderal Sudirman, Makassar',
                'city' => 'Makassar',
                'pic_name' => 'PIC KPR '.$bank->nama_bank,
                'pic_position' => 'Mortgage Relationship Manager',
                'phone' => '0411-'.str_pad((string) (700000 + $bank->id), 6, '0', STR_PAD_LEFT),
                'email' => 'kpr.makassar.'.strtolower(str_replace(['-KPR', ' '], '', $bank->kode_bank)).'@example.test',
                'status' => 'aktif',
            ])->save();
            if ($branch->trashed()) {
                $branch->restore();
            }

            $products = [
                [
                    'suffix' => 'REG', 'name' => 'KPR Reguler', 'type' => 'kpr_reguler', 'subsidy' => 'non_subsidi',
                    'minimum_ceiling' => 100000000, 'maximum_ceiling' => 2000000000,
                    'minimum_down_payment' => 20000000, 'maximum_tenor_months' => min(240, (int) $bank->tenor_max_bulan),
                    'margin' => (float) $bank->bunga_tahunan, 'provision' => 1000000,
                ],
                [
                    'suffix' => 'FLX', 'name' => 'KPR Fleksibel', 'type' => 'kpr_fleksibel', 'subsidy' => 'non_subsidi',
                    'minimum_ceiling' => 75000000, 'maximum_ceiling' => 1500000000,
                    'minimum_down_payment' => 15000000, 'maximum_tenor_months' => min(180, (int) $bank->tenor_max_bulan),
                    'margin' => (float) $bank->bunga_tahunan + 0.5, 'provision' => 750000,
                ],
            ];

            foreach ($products as $definition) {
                $product = BankCreditProduct::withTrashed()->firstOrNew(['product_code' => $bank->kode_bank.'-'.$definition['suffix']]);
                $product->fill([
                    'bank_kredit_id' => $bank->id,
                    'bank_branch_id' => $branch->id,
                    'product_name' => $bank->nama_bank.' '.$definition['name'],
                    'product_type' => $definition['type'],
                    'subsidy_type' => $definition['subsidy'],
                    'scheme_type' => $bank->jenis_bank === 'syariah' ? 'syariah' : 'konvensional',
                    'minimum_ceiling' => $definition['minimum_ceiling'],
                    'maximum_ceiling' => $definition['maximum_ceiling'],
                    'minimum_down_payment' => $definition['minimum_down_payment'],
                    'maximum_tenor_months' => $definition['maximum_tenor_months'],
                    'indicative_interest_margin' => $definition['margin'],
                    'provision_fee' => $definition['provision'],
                    'administration_fee' => (float) $bank->biaya_admin,
                    'appraisal_fee' => 750000,
                    'insurance_fee' => 2500000,
                    'notary_fee' => 5000000,
                    'disbursement_method' => 'sesuai_perjanjian',
                    'estimated_sla_days' => 14,
                    'effective_from' => '2026-01-01',
                    'effective_until' => null,
                    'current_version' => 1,
                    'status' => 'aktif',
                    'record_status' => 'locked',
                    'locked_at' => now(),
                    'locked_by' => $creatorId,
                    'notes' => 'Produk contoh untuk pengujian alur SPR KPR Bank.',
                ])->save();
                if ($product->trashed()) {
                    $product->restore();
                }

                BankCreditProductVersion::query()->updateOrCreate(
                    ['bank_credit_product_id' => $product->id, 'version_number' => 1],
                    ['terms_snapshot' => $product->fresh()->toArray(), 'effective_from' => '2026-01-01', 'effective_until' => null, 'created_by' => $creatorId],
                );

                $this->seedDocuments($bank, $product);
            }

            foreach ($housings as $housing) {
                $agreementNumber = 'PKS/'.$bank->kode_bank.'/'.($housing->kode_proyek ?: $housing->id).'/2026';
                $partnership = BankHousingPartnership::withTrashed()->firstOrNew([
                    'bank_kredit_id' => $bank->id,
                    'perumahan_id' => $housing->id,
                    'agreement_number' => $agreementNumber,
                ]);
                $partnership->fill([
                    'bank_branch_id' => $branch->id,
                    'agreement_name' => 'Kerja Sama Pembiayaan '.$housing->nama_perusahaan,
                    'effective_from' => '2026-01-01',
                    'effective_until' => null,
                    'current_version' => 1,
                    'status' => 'aktif',
                    'notes' => 'PKS contoh untuk pengujian. Ganti dengan dokumen PKS sebenarnya.',
                ])->save();
                if ($partnership->trashed()) {
                    $partnership->restore();
                }

                BankHousingPartnershipVersion::query()->updateOrCreate(
                    ['bank_housing_partnership_id' => $partnership->id, 'version_number' => 1],
                    ['agreement_snapshot' => $partnership->fresh()->toArray(), 'effective_from' => '2026-01-01', 'effective_until' => null, 'created_by' => $creatorId],
                );
            }
        }
    }

    private function seedDocuments(BankKredit $bank, BankCreditProduct $product): void
    {
        $documents = [
            ['KTP', 'KTP Pemohon dan Pasangan', true, 1],
            ['KK', 'Kartu Keluarga', true, 2],
            ['NPWP', 'NPWP Pemohon', true, 3],
            ['PENGHASILAN', 'Bukti Penghasilan Tiga Bulan Terakhir', true, 4],
            ['REKENING', 'Rekening Koran Tiga Bulan Terakhir', true, 5],
            ['NIKAH', 'Buku Nikah atau Akta Perkawinan', false, 6],
        ];
        $set = DocumentRequirementSet::withTrashed()->firstOrNew(['code' => 'BANK-'.$product->product_code]);
        $set->fill(['name' => 'Persyaratan '.$product->product_name, 'description' => 'Paket contoh produk bank. Sesuaikan dengan PKS yang berlaku.', 'application_types' => ['kpr_bank'], 'status' => 'aktif', 'record_status' => 'locked', 'locked_at' => now()])->save();
        if ($set->trashed()) {
            $set->restore();
        }
        $set->banks()->syncWithoutDetaching([$bank->id]);
        $set->products()->syncWithoutDetaching([$product->id]);
        foreach ($documents as [$code, $name, $required, $sort]) {
            $document = DokumenCostumer::withTrashed()->firstOrNew(['kode_dokumen' => $code]);
            $document->fill(['nama_dokumen' => $name, 'kategori_pengajuan' => 'kpr_bank', 'wajib' => $required, 'status' => 'aktif', 'record_status' => 'locked', 'locked_at' => now()])->save();
            if ($document->trashed()) {
                $document->restore();
            }
            $set->items()->updateOrCreate(['dokumen_costumer_id' => $document->id, 'party_scope' => 'customer'], ['is_required' => $required, 'sort_order' => $sort]);
        }
        ApprovalRequest::query()->updateOrCreate(
            ['module_key' => 'document-requirement-set', 'model_type' => DocumentRequirementSet::class, 'model_id' => $set->id, 'action' => 'lock'],
            ['module_label' => 'Paket Persyaratan Dokumen Pelanggan', 'status' => 'approved', 'current_step' => 1, 'total_steps' => 1, 'reviewed_at' => now(), 'after_data' => ['seeded' => true]],
        );
    }
}
