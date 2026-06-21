<?php

namespace Database\Seeders;

use App\Models\BankKredit;
use Illuminate\Database\Seeder;

class BankKreditSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['kode_bank' => 'BTN-KPR', 'nama_bank' => 'Bank BTN KPR'],
            ['kode_bank' => 'BRI-KPR', 'nama_bank' => 'Bank BRI KPR'],
            ['kode_bank' => 'BNI-KPR', 'nama_bank' => 'Bank BNI KPR'],
            ['kode_bank' => 'MANDIRI-KPR', 'nama_bank' => 'Bank Mandiri KPR'],
            ['kode_bank' => 'BCA-KPR', 'nama_bank' => 'Bank BCA KPR'],
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
                'status' => 'aktif',
                'record_status' => 'draft',
            ];

            if ($row) {
                $row->fill($data)->save();

                if ($row->trashed()) {
                    $row->restore();
                }

                continue;
            }

            BankKredit::create($data);
        }
    }
}
