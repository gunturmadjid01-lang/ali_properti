<?php

namespace Database\Seeders;

use App\Models\DokumenLegalitasRumah;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;

class DokumenLegalitasRumahSeeder extends Seeder
{
    public function run(): void
    {
        $perumahan = Perumahan::where('nama_perusahaan', 'Perumahan Sidratul Muntaha Mamuju')->firstOrFail();

        foreach (['Dokumen AJB', 'Dokumen SHM Pecahan', 'Dokumen PBB'] as $namaDokumen) {
            $data = [
                'tanggal_terbit' => '2026-01-01',
                'tanggal_berakhir' => '2036-01-01',
                'file' => 'dokumen/default.pdf',
                'status' => 'aktif',
            ];
            $row = DokumenLegalitasRumah::withTrashed()
                ->where('perumahan_id', $perumahan->id)
                ->where('nama_dokumen', $namaDokumen)
                ->first();

            if ($row) {
                $row->fill($data)->save();

                if ($row->trashed()) {
                    $row->restore();
                }

                continue;
            }

            DokumenLegalitasRumah::create([
                'perumahan_id' => $perumahan->id,
                'nama_dokumen' => $namaDokumen,
                ...$data,
            ]);
        }
    }
}
