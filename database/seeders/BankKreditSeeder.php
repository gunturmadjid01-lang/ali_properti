<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\BankKredit;
use App\Models\User;
use Illuminate\Database\Seeder;

class BankKreditSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = User::query()->value('id');
        $banks = [
            ['kode_bank' => 'BTN-KPR', 'nama_bank' => 'Bank BTN KPR', 'bunga_tahunan' => 7.25, 'tenor_min_bulan' => 60, 'tenor_max_bulan' => 240, 'minimal_dp_persen' => 10, 'biaya_provisi_persen' => 1, 'biaya_admin' => 750000],
            ['kode_bank' => 'BRI-KPR', 'nama_bank' => 'Bank BRI KPR', 'bunga_tahunan' => 7.50, 'tenor_min_bulan' => 60, 'tenor_max_bulan' => 240, 'minimal_dp_persen' => 10, 'biaya_provisi_persen' => 1, 'biaya_admin' => 750000],
            ['kode_bank' => 'BNI-KPR', 'nama_bank' => 'Bank BNI KPR', 'bunga_tahunan' => 7.75, 'tenor_min_bulan' => 60, 'tenor_max_bulan' => 240, 'minimal_dp_persen' => 10, 'biaya_provisi_persen' => 1, 'biaya_admin' => 750000],
            ['kode_bank' => 'MANDIRI-KPR', 'nama_bank' => 'Bank Mandiri KPR', 'bunga_tahunan' => 8.00, 'tenor_min_bulan' => 60, 'tenor_max_bulan' => 240, 'minimal_dp_persen' => 10, 'biaya_provisi_persen' => 1, 'biaya_admin' => 750000],
            ['kode_bank' => 'BCA-KPR', 'nama_bank' => 'Bank BCA KPR', 'bunga_tahunan' => 8.25, 'tenor_min_bulan' => 60, 'tenor_max_bulan' => 240, 'minimal_dp_persen' => 15, 'biaya_provisi_persen' => 1, 'biaya_admin' => 1000000],
        ];

        foreach ($banks as $bank) {
            $row = BankKredit::withTrashed()
                ->where('kode_bank', $bank['kode_bank'])
                ->first();

            $data = [
                ...$bank,
                'nama_pic' => null,
                'telepon_pic' => null,
                'email_pic' => null,
                'bunga_tahunan' => $bank['bunga_tahunan'],
                'tenor_min_bulan' => $bank['tenor_min_bulan'],
                'tenor_max_bulan' => $bank['tenor_max_bulan'],
                'minimal_dp_persen' => $bank['minimal_dp_persen'],
                'biaya_provisi_persen' => $bank['biaya_provisi_persen'],
                'biaya_admin' => $bank['biaya_admin'],
                'status' => 'aktif',
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $creatorId,
            ];

            if ($row) {
                $row->fill($data)->save();

                if ($row->trashed()) {
                    $row->restore();
                }

            } else {
                $row = BankKredit::create($data);
            }

            ApprovalRequest::query()->updateOrCreate(
                [
                    'module_key' => 'bank-credit-master',
                    'action' => 'lock',
                    'model_type' => BankKredit::class,
                    'model_id' => $row->id,
                ],
                [
                    'module_label' => 'Master Bank Kredit',
                    'status' => ApprovalRequest::STATUS_APPROVED,
                    'current_step' => 1,
                    'total_steps' => 1,
                    'requested_by' => $creatorId,
                    'reviewed_by' => $creatorId,
                    'reviewed_at' => now(),
                    'after_data' => ['record_status' => 'locked', 'seeded' => true],
                ],
            );
        }
    }
}
