<?php

namespace Database\Seeders;

use App\Models\MasterBank;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;

class MasterBankSeeder extends Seeder
{
    public function run(): void
    {
        $perumahan = Perumahan::query()->first();

        if (! $perumahan) {
            return;
        }

        $banks = [
            ['kode_bank' => 'BRI', 'nama_bank' => 'Bank Rakyat Indonesia'],
            ['kode_bank' => 'BNI', 'nama_bank' => 'Bank Negara Indonesia'],
            ['kode_bank' => 'MANDIRI', 'nama_bank' => 'Bank Mandiri'],
            ['kode_bank' => 'BCA', 'nama_bank' => 'Bank Central Asia'],
            ['kode_bank' => 'BTN', 'nama_bank' => 'Bank Tabungan Negara'],
        ];

        foreach ($banks as $bank) {
            $row = MasterBank::withTrashed()
                ->where('kode_bank', $bank['kode_bank'])
                ->first();

            $data = [
                ...$bank,
                'perumahan_id' => $perumahan->id,
                'nomor_rekening' => null,
                'nama_rekening' => 'PT Ali Properti Indonesia',
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

            MasterBank::create($data);
        }
    }
}
