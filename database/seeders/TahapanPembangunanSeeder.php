<?php

namespace Database\Seeders;

use App\Models\TahapanPembangunan;
use Illuminate\Database\Seeder;

class TahapanPembangunanSeeder extends Seeder
{
    public function run(): void
    {
        $unitItems = [
            ['nama_tahapan' => 'PEK. PERSIAPAN & PONDASI', 'bobot_persen' => 7.48, 'urutan' => 1],
            ['nama_tahapan' => 'PEK. DINDING', 'bobot_persen' => 26.30, 'urutan' => 2],
            ['nama_tahapan' => 'PEK. FINISHING AWAL', 'bobot_persen' => 14.44, 'urutan' => 3],
            ['nama_tahapan' => 'PEK. PIPA AIR BERSIH & KOTOR', 'bobot_persen' => 1.66, 'urutan' => 4],
            ['nama_tahapan' => 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI', 'bobot_persen' => 10.81, 'urutan' => 5],
            ['nama_tahapan' => 'PEK. PAGAR & CAR PORT', 'bobot_persen' => 14.96, 'urutan' => 6],
            ['nama_tahapan' => 'PEK. TAMAN, PROFIL DAN PENGECATAN', 'bobot_persen' => 6.38, 'urutan' => 7],
            ['nama_tahapan' => 'PEK. PEMASANGAN ATAP', 'bobot_persen' => 7.42, 'urutan' => 8],
            ['nama_tahapan' => 'PEK. PEMASANGAN PLAFON', 'bobot_persen' => 7.42, 'urutan' => 9],
            ['nama_tahapan' => 'PEK. INSTALASI LISTRIK', 'bobot_persen' => 3.13, 'urutan' => 10],
        ];

        $kawasanItems = [
            ['nama_tahapan' => 'I RAB TANAH', 'bobot_persen' => 28.77, 'urutan' => 1],
            ['nama_tahapan' => 'II RAB SARANA', 'bobot_persen' => 10.15, 'urutan' => 2],
            ['nama_tahapan' => 'III RAB PRASARANA', 'bobot_persen' => 12.54, 'urutan' => 3],
            ['nama_tahapan' => 'IV RAB BANGUNAN', 'bobot_persen' => 48.54, 'urutan' => 4],
        ];

        foreach (array_merge(
            array_map(fn (array $item) => [...$item, 'konteks' => 'unit'], $unitItems),
            array_map(fn (array $item) => [...$item, 'konteks' => 'kawasan'], $kawasanItems),
        ) as $item) {
            TahapanPembangunan::query()->updateOrCreate(
                ['nama_tahapan' => $item['nama_tahapan']],
                [...$item, 'status' => 'aktif'],
            );
        }
    }
}
