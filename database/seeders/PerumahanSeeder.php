<?php

namespace Database\Seeders;

use App\Models\CabangPerusahaan;
use App\Models\Perumahan;
use Illuminate\Database\Seeder;

class PerumahanSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = CabangPerusahaan::where('kode_cabang', 'PST-001')->firstOrFail();

        $data = [
            'cabang_id' => $cabang->id,
            'kode_proyek' => 'PRJ-SMM-001',
            'developer_name' => 'PT Ali Properti Indonesia',
            'alamat' => 'Mamuju, Sulawesi Barat',
            'latitude' => null,
            'longtitude' => null,
            'logo' => 'logo.png',
            'luas_lahan' => '3 Ha',
            'luas_komersial' => '2.1 Ha',
            'luas_fasos_fasum' => '0.9 Ha',
            'jumlah_unit' => 120,
            'total_blok' => 3,
            'harga_mulai' => 185000000,
            'tanggal_mulai' => '2026-06-13',
            'tanggal_target_selesai' => '2028-06-13',
            'jenis_sertifikat' => 'shm',
            'nomor_sertifikat_induk' => 'SHM-INDUK-SMM-001',
            'nama_marketing' => 'Tim Marketing PT ALI',
            'phone_marketing' => '081100000004',
            'email_marketing' => 'marketing@ptali.com',
            'deskripsi' => 'Kawasan hunian tertata dengan pengelolaan unit, legalitas, HPP, dan progres pembangunan terintegrasi.',
            'status' => 'aktif',
        ];

        $perumahan = Perumahan::withTrashed()
            ->where('nama_perusahaan', 'Perumahan Sidratul Muntaha Mamuju')
            ->first();

        if ($perumahan) {
            $perumahan->fill($data)->save();

            if ($perumahan->trashed()) {
                $perumahan->restore();
            }

            return;
        }

        Perumahan::create([
            'nama_perusahaan' => 'Perumahan Sidratul Muntaha Mamuju',
            ...$data,
        ]);
    }
}
