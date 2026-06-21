<?php

namespace Database\Seeders;

use App\Models\KelompokHpp;
use Illuminate\Database\Seeder;

class KelompokHppSeeder extends Seeder
{
    public function run(): void
    {
        $kelompoks = [
            ['nama_hpp' => 'Beli Tanah', 'kategori' => 'tanah'],
            ['nama_hpp' => 'Biaya Balik Nama Tanah', 'kategori' => 'tanah'],
            ['nama_hpp' => 'Biaya Ukur Tanah', 'kategori' => 'tanah'],
            ['nama_hpp' => 'Biaya Pematangan Lahan', 'kategori' => 'tanah'],
            ['nama_hpp' => 'Biaya Cut and Fill', 'kategori' => 'tanah'],
            ['nama_hpp' => 'Biaya Urugan Tanah', 'kategori' => 'tanah'],

            ['nama_hpp' => 'AJB BBN', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'BPHTB', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'Pajak PPN', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'PBB', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'Perijinan', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'IMB PBG', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'SLF', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'Notaris', 'kategori' => 'legalitas'],
            ['nama_hpp' => 'Sertifikat Pecah Kavling', 'kategori' => 'legalitas'],

            ['nama_hpp' => 'Jalan Kawasan', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Drainase', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Pagar Kawasan', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Gerbang Perumahan', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Taman dan Fasum', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Penerangan Jalan', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Instalasi Air Bersih', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Instalasi Listrik Kawasan', 'kategori' => 'infrastruktur'],
            ['nama_hpp' => 'Septictank Komunal', 'kategori' => 'infrastruktur'],

            ['nama_hpp' => 'Pondasi', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Struktur Beton', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Dinding', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Atap', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Plafon', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Lantai', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Kusen Pintu dan Jendela', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Pintu dan Jendela', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Sanitair', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Instalasi Plumbing Rumah', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Instalasi Listrik Rumah', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Cat dan Finishing', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Kanopi dan Carport', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Biaya Bangun Rumah', 'kategori' => 'bangunan'],
            ['nama_hpp' => 'Biaya Tak Terduga Bangunan', 'kategori' => 'bangunan'],

            ['nama_hpp' => 'Pasir', 'kategori' => 'material'],
            ['nama_hpp' => 'Batu Kali', 'kategori' => 'material'],
            ['nama_hpp' => 'Batu Split', 'kategori' => 'material'],
            ['nama_hpp' => 'Semen', 'kategori' => 'material'],
            ['nama_hpp' => 'Besi', 'kategori' => 'material'],
            ['nama_hpp' => 'Bata Merah Batako Hebel', 'kategori' => 'material'],
            ['nama_hpp' => 'Kayu', 'kategori' => 'material'],
            ['nama_hpp' => 'Keramik', 'kategori' => 'material'],
            ['nama_hpp' => 'Material Bangunan', 'kategori' => 'material'],

            ['nama_hpp' => 'Upah Tukang', 'kategori' => 'tenaga_kerja'],
            ['nama_hpp' => 'Upah Mandor', 'kategori' => 'tenaga_kerja'],
            ['nama_hpp' => 'Upah Harian Proyek', 'kategori' => 'tenaga_kerja'],
            ['nama_hpp' => 'Biaya Subkontraktor', 'kategori' => 'tenaga_kerja'],

            ['nama_hpp' => 'Gaji Karyawan Proyek', 'kategori' => 'operasional'],
            ['nama_hpp' => 'Gaji Karyawan Kantor', 'kategori' => 'operasional'],
            ['nama_hpp' => 'Transportasi Proyek', 'kategori' => 'operasional'],
            ['nama_hpp' => 'Konsumsi Proyek', 'kategori' => 'operasional'],
            ['nama_hpp' => 'Sewa Alat', 'kategori' => 'operasional'],
            ['nama_hpp' => 'Keamanan Proyek', 'kategori' => 'operasional'],
            ['nama_hpp' => 'Administrasi Kantor', 'kategori' => 'operasional'],

            ['nama_hpp' => 'Fee Marketing Tahap 1', 'kategori' => 'marketing'],
            ['nama_hpp' => 'Fee Marketing Tahap 2', 'kategori' => 'marketing'],
            ['nama_hpp' => 'Fee Marketing Tahap 3', 'kategori' => 'marketing'],
            ['nama_hpp' => 'Iklan dan Promosi', 'kategori' => 'marketing'],
            ['nama_hpp' => 'Brosur Banner dan Spanduk', 'kategori' => 'marketing'],
            ['nama_hpp' => 'Pameran dan Event', 'kategori' => 'marketing'],

            ['nama_hpp' => 'Bayar Hutang', 'kategori' => 'keuangan'],
            ['nama_hpp' => 'Bunga Pinjaman', 'kategori' => 'keuangan'],
            ['nama_hpp' => 'Biaya Administrasi Bank', 'kategori' => 'keuangan'],
            ['nama_hpp' => 'Bagi Hasil Investor', 'kategori' => 'keuangan'],
            ['nama_hpp' => 'Bagi Hasil Profit Pemilik Tanah', 'kategori' => 'keuangan'],

            ['nama_hpp' => 'Cadangan Maintenance', 'kategori' => 'cadangan'],
            ['nama_hpp' => 'Biaya Komplain Konsumen', 'kategori' => 'cadangan'],
            ['nama_hpp' => 'Biaya Tak Terduga', 'kategori' => 'cadangan'],
        ];

        foreach ($kelompoks as $kelompok) {
            $row = KelompokHpp::withTrashed()->updateOrCreate(
                ['nama_hpp' => $kelompok['nama_hpp']],
                [
                    ...$kelompok,
                    'status' => 'aktif',
                ],
            );

            if ($row->trashed()) {
                $row->restore();
            }
        }
    }
}
