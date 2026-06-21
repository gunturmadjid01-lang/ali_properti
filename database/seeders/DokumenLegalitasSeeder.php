<?php

namespace Database\Seeders;

use App\Models\DokumenLegalitas;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;

class DokumenLegalitasSeeder extends Seeder
{
    public function run(): void
    {
        $perumahan = Perumahan::where('nama_perusahaan', 'Perumahan Sidratul Muntaha Mamuju')->firstOrFail();

        $dokumens = [
            ['nama_dokument' => 'Sertifikat Induk', 'nomor_dokument' => 'SHM-SM-001'],
            ['nama_dokument' => 'Izin Mendirikan Bangunan', 'nomor_dokument' => 'IMB-SM-001'],
            ['nama_dokument' => 'Persetujuan Bangunan Gedung', 'nomor_dokument' => 'PBG-SM-001'],
        ];

        foreach ($dokumens as $dokumen) {
            $data = [
                ...$dokumen,
                'tanggal_terbit' => '2026-01-01',
                'tanggal_berakhir' => '2036-01-01',
                'file' => null,
                'status' => 'aktif',
            ];
            $row = DokumenLegalitas::withTrashed()
                ->where('perumahan_id', $perumahan->id)
                ->where('nomor_dokument', $dokumen['nomor_dokument'])
                ->first();

            if ($row) {
                $row->fill($data)->save();

                if ($row->trashed()) {
                    $row->restore();
                }

                continue;
            }

            DokumenLegalitas::create([
                'perumahan_id' => $perumahan->id,
                ...$data,
            ]);
        }
    }
}
