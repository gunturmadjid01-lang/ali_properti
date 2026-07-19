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
            ['kode_bank' => 'BRI', 'nama_bank' => 'Bank Rakyat Indonesia', 'nomor_rekening' => '001001000001'],
            ['kode_bank' => 'BNI', 'nama_bank' => 'Bank Negara Indonesia', 'nomor_rekening' => '002002000002'],
            ['kode_bank' => 'MANDIRI', 'nama_bank' => 'Bank Mandiri', 'nomor_rekening' => '003003000003'],
            ['kode_bank' => 'BCA', 'nama_bank' => 'Bank Central Asia', 'nomor_rekening' => '004004000004'],
            ['kode_bank' => 'BTN', 'nama_bank' => 'Bank Tabungan Negara', 'nomor_rekening' => '005005000005'],
        ];

        foreach ($banks as $bank) {
            $row = MasterBank::withTrashed()
                ->where('kode_bank', $bank['kode_bank'])
                ->first();

            $data = [
                ...$bank,
                'cabang_id' => $perumahan->cabang_id,
                'perumahan_id' => $perumahan->id,
                'nama_rekening' => 'PT Ali Properti Indonesia',
                'status' => 'aktif',
                'record_status' => 'locked',
                'locked_at' => now(),
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

        // Setiap proyek wajib memiliki minimal satu rekening penerimaan final
        // agar form Reservasi Transfer selalu dapat digunakan.
        Perumahan::query()->whereKeyNot($perumahan->id)->orderBy('id')->each(function (Perumahan $project): void {
            MasterBank::query()->updateOrCreate(
                ['kode_bank' => 'PROYEK-'.$project->id],
                [
                    'cabang_id' => $project->cabang_id,
                    'perumahan_id' => $project->id,
                    'nama_bank' => 'Bank Mandiri',
                    'nomor_rekening' => '103'.str_pad((string) $project->id, 9, '0', STR_PAD_LEFT),
                    'nama_rekening' => 'PT Ali Properti Indonesia - '.$project->nama_perusahaan,
                    'status' => 'aktif',
                    'record_status' => 'locked',
                    'locked_at' => now(),
                ],
            );
        });
    }
}
