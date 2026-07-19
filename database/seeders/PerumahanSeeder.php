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

        $projects = [
            [
                'kode_proyek' => 'PRJ-SMM-001',
                'nama_perusahaan' => 'Perumahan Sidratul Muntaha Mamuju',
                'alamat' => 'Mamuju, Sulawesi Barat',
                'luas_lahan' => '3 Ha',
                'luas_komersial' => '2.1 Ha',
                'luas_fasos_fasum' => '0.9 Ha',
                'jumlah_unit' => 120,
                'total_blok' => 3,
                'harga_mulai' => 185000000,
                'tanggal_mulai' => '2026-06-13',
                'tanggal_target_selesai' => '2028-06-13',
                'nomor_sertifikat_induk' => 'SHM-INDUK-SMM-001',
            ],
            [
                'kode_proyek' => 'PRJ-GRM-002',
                'nama_perusahaan' => 'Green Residence Mamuju',
                'alamat' => 'Simboro, Mamuju, Sulawesi Barat',
                'luas_lahan' => '2 Ha',
                'luas_komersial' => '1.4 Ha',
                'luas_fasos_fasum' => '0.6 Ha',
                'jumlah_unit' => 80,
                'total_blok' => 2,
                'harga_mulai' => 225000000,
                'tanggal_mulai' => '2026-07-01',
                'tanggal_target_selesai' => '2028-07-01',
                'nomor_sertifikat_induk' => 'SHM-INDUK-GRM-002',
            ],
            [
                'kode_proyek' => 'PRJ-BGV-003',
                'nama_perusahaan' => 'Bukit Graha Vista',
                'alamat' => 'Kalukku, Mamuju, Sulawesi Barat',
                'luas_lahan' => '2.5 Ha',
                'luas_komersial' => '1.8 Ha',
                'luas_fasos_fasum' => '0.7 Ha',
                'jumlah_unit' => 96,
                'total_blok' => 3,
                'harga_mulai' => 205000000,
                'tanggal_mulai' => '2026-08-01',
                'tanggal_target_selesai' => '2028-12-01',
                'nomor_sertifikat_induk' => 'SHM-INDUK-BGV-003',
            ],
        ];

        foreach ($projects as $project) {
            $perumahan = Perumahan::withTrashed()->firstOrNew(['kode_proyek' => $project['kode_proyek']]);
            $perumahan->fill([
                ...$project,
                'cabang_id' => $cabang->id,
                'developer_name' => 'PT Ali Properti Indonesia',
                'latitude' => null,
                'longtitude' => null,
                'logo' => null,
                'jenis_sertifikat' => 'shm',
                'nama_marketing' => 'Tim Marketing PT ALI',
                'phone_marketing' => '081100000004',
                'email_marketing' => 'marketing@ptali.com',
                'deskripsi' => 'Data contoh perumahan untuk pengujian unit, customer, SPR, dan approval bertahap.',
                'status' => 'aktif',
            ])->save();
            if ($perumahan->trashed()) {
                $perumahan->restore();
            }
        }
    }
}
