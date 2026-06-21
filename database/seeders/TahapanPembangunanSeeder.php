<?php

namespace Database\Seeders;

use App\Models\TahapanPembangunan;
use Illuminate\Database\Seeder;

class TahapanPembangunanSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['nama_tahapan' => 'Pondasi', 'bobot_persen' => 15, 'urutan' => 1],
            ['nama_tahapan' => 'Dinding', 'bobot_persen' => 25, 'urutan' => 2],
            ['nama_tahapan' => 'Lantai', 'bobot_persen' => 15, 'urutan' => 3],
            ['nama_tahapan' => 'Atap', 'bobot_persen' => 15, 'urutan' => 4],
            ['nama_tahapan' => 'Plafon', 'bobot_persen' => 10, 'urutan' => 5],
            ['nama_tahapan' => 'Instalasi', 'bobot_persen' => 10, 'urutan' => 6],
            ['nama_tahapan' => 'Finishing', 'bobot_persen' => 10, 'urutan' => 7],
        ];

        foreach ($items as $item) {
            TahapanPembangunan::query()->updateOrCreate(
                ['nama_tahapan' => $item['nama_tahapan']],
                [...$item, 'status' => 'aktif'],
            );
        }
    }
}
