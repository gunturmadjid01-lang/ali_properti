<?php

namespace Database\Seeders;

use App\Models\CabangPerusahaan;
use App\Models\CashInstallmentScheme;
use App\Models\DeveloperKprProduct;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesPaymentMasterSeeder extends Seeder
{
    public function run(): void
    {
        $branch = CabangPerusahaan::query()->where('kode_cabang', 'PST-001')->firstOrFail();
        $creatorId = User::query()->where('email', 'admin@ptali.com')->value('id');

        $secondHousing = Perumahan::withTrashed()->firstOrNew(['kode_proyek' => 'PRJ-GRM-002']);
        $secondHousing->fill([
            'cabang_id' => $branch->id,
            'nama_perusahaan' => 'Green Residence Mamuju',
            'developer_name' => 'PT Ali Properti Indonesia',
            'alamat' => 'Mamuju, Sulawesi Barat',
            'luas_lahan' => '2 Ha',
            'luas_komersial' => '1.4 Ha',
            'luas_fasos_fasum' => '0.6 Ha',
            'jumlah_unit' => 80,
            'total_blok' => 2,
            'harga_mulai' => 225000000,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_target_selesai' => '2028-07-01',
            'jenis_sertifikat' => 'shm',
            'status' => 'aktif',
        ])->save();
        if ($secondHousing->trashed()) {
            $secondHousing->restore();
        }

        $housings = Perumahan::query()->where('cabang_id', $branch->id)->orderBy('id')->get();
        $housingIds = $housings->pluck('id')->all();

        $monthly = $this->scheme('CB-SAMA-12', [
            'name' => 'Cash Bertahap Bulanan 12 Kali',
            'minimum_booking_fee' => 5000000,
            'booking_fee_deducts' => 'down_payment',
            'dp_type' => 'percentage',
            'minimum_dp' => 20,
            'payment_model' => 'equal_monthly',
            'installment_count' => 12,
            'maximum_tenor_months' => 12,
            'interval_type' => 'monthly',
            'schedule_config' => ['tenor_value' => 12, 'tenor_unit' => 'month', 'holiday_rule' => 'next_business_day'],
            'grace_period_days' => 7,
            'penalty_method' => 'invoice_percentage',
            'penalty_value' => 0.1,
            'handover_config' => ['dp_paid' => true, 'no_arrears' => true, 'minimum_paid_percentage' => 30, 'minimum_progress' => 100],
            'requirements' => [['category' => 'Administratif', 'name' => 'Menandatangani surat kesanggupan pembayaran', 'required' => true, 'condition' => 'all']],
            'effective_from' => '2026-01-01',
            'status' => 'aktif',
        ], $branch->id, $housingIds, $creatorId);
        $monthly->steps()->withTrashed()->forceDelete();

        $staged = $this->scheme('CB-TAHAP-12', [
            'name' => 'Cash Bertahap Berdasarkan Tahapan',
            'minimum_booking_fee' => 5000000,
            'booking_fee_deducts' => 'down_payment',
            'dp_type' => 'percentage',
            'minimum_dp' => 20,
            'payment_model' => 'percentage_steps',
            'installment_count' => 4,
            'maximum_tenor_months' => 12,
            'interval_type' => 'staged',
            'schedule_config' => ['holiday_rule' => 'next_business_day'],
            'grace_period_days' => 7,
            'penalty_method' => 'invoice_percentage',
            'penalty_value' => 0.1,
            'handover_config' => ['dp_paid' => true, 'no_arrears' => true, 'minimum_paid_percentage' => 30, 'minimum_progress' => 100],
            'requirements' => [],
            'effective_from' => '2026-01-01',
            'status' => 'aktif',
        ], $branch->id, [$housingIds[0]], $creatorId);
        $staged->steps()->withTrashed()->forceDelete();
        foreach ([
            ['name' => 'Uang Muka', 'calculation_type' => 'percentage_sale', 'value' => 20, 'due_offset_months' => 0],
            ['name' => 'Tahap Pondasi', 'calculation_type' => 'percentage_sale', 'value' => 30, 'due_offset_months' => 3],
            ['name' => 'Tahap Atap', 'calculation_type' => 'percentage_sale', 'value' => 30, 'due_offset_months' => 7],
            ['name' => 'Pelunasan', 'calculation_type' => 'remaining', 'value' => 0, 'due_offset_months' => 12],
        ] as $index => $step) {
            $staged->steps()->create([...$step, 'sequence' => $index + 1, 'created_by' => $creatorId]);
        }

        $this->product('KPRD-FLAT-60', [
            'name' => 'KPR Developer Flat sampai 60 Bulan',
            'dp_type' => 'percentage',
            'minimum_dp' => 20,
            'financing_type' => 'percentage',
            'maximum_financing' => 80,
            'financing_basis' => 'final_price',
            'minimum_tenor_months' => 12,
            'maximum_tenor_months' => 60,
            'tenor_mode' => 'range',
            'tenor_increment' => 12,
            'allowed_tenors' => [12, 24, 36, 48, 60],
            'annual_margin' => 8,
            'margin_method' => 'flat',
            'margin_scope' => 'all',
            'administration_fee' => 1500000,
            'contract_fee' => 2500000,
            'fees' => [
                ['type' => 'Administrasi', 'method' => 'fixed', 'value' => 1500000, 'payment_time' => 'contract', 'financed' => false, 'required' => true],
                ['type' => 'Akad', 'method' => 'fixed', 'value' => 2500000, 'payment_time' => 'contract', 'financed' => false, 'required' => true],
            ],
            'grace_period_days' => 7,
            'penalty_method' => 'installment_percentage',
            'penalty_value' => 0.1,
            'minimum_income' => 5000000,
            'maximum_age' => 55,
            'eligibility_config' => ['jobs' => ['Karyawan Tetap', 'ASN', 'Profesional', 'Wiraswasta'], 'maximum_installment_ratio' => 30, 'minimum_age' => 21, 'maximum_age_at_end' => 65, 'spouse_required' => false],
            'handover_config' => ['dp_paid' => true, 'no_arrears' => true, 'minimum_progress' => 100],
            'advanced_config' => ['early_settlement' => true, 'restructuring' => true, 'cancellation' => true],
            'effective_from' => '2026-01-01',
            'status' => 'aktif',
        ], $branch->id, $housingIds, $creatorId);
    }

    private function scheme(string $code, array $data, int $branchId, array $housingIds, ?int $creatorId): CashInstallmentScheme
    {
        $row = CashInstallmentScheme::withTrashed()->firstOrNew(['code' => $code]);
        $row->fill([...$data, 'cabang_perusahaan_id' => $branchId, 'perumahan_id' => $housingIds[0], 'created_by' => $creatorId, 'version' => 1, 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $creatorId])->save();
        if ($row->trashed()) {
            $row->restore();
        }
        $row->housings()->sync($housingIds);

        return $row;
    }

    private function product(string $code, array $data, int $branchId, array $housingIds, ?int $creatorId): DeveloperKprProduct
    {
        $row = DeveloperKprProduct::withTrashed()->firstOrNew(['code' => $code]);
        $row->fill([...$data, 'cabang_perusahaan_id' => $branchId, 'perumahan_id' => $housingIds[0], 'created_by' => $creatorId, 'version' => 1, 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $creatorId])->save();
        if ($row->trashed()) {
            $row->restore();
        }
        $row->housings()->sync($housingIds);

        return $row;
    }
}
