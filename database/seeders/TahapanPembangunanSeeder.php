<?php

namespace Database\Seeders;

use App\Models\TahapanPembangunan;
use Illuminate\Database\Seeder;

class TahapanPembangunanSeeder extends Seeder
{
    public function run(): void
    {
        $unitItems = [
            ['nama_tahapan' => 'Pondasi', 'bobot_persen' => 15, 'urutan' => 1],
            ['nama_tahapan' => 'Dinding', 'bobot_persen' => 25, 'urutan' => 2],
            ['nama_tahapan' => 'Lantai', 'bobot_persen' => 15, 'urutan' => 3],
            ['nama_tahapan' => 'Atap', 'bobot_persen' => 15, 'urutan' => 4],
            ['nama_tahapan' => 'Plafon', 'bobot_persen' => 10, 'urutan' => 5],
            ['nama_tahapan' => 'Instalasi', 'bobot_persen' => 10, 'urutan' => 6],
            ['nama_tahapan' => 'Finishing', 'bobot_persen' => 10, 'urutan' => 7],
        ];

        $kawasanItems = [
            ['nama_tahapan' => 'Pematangan Lahan', 'bobot_persen' => 15, 'urutan' => 1],
            ['nama_tahapan' => 'Cut and Fill', 'bobot_persen' => 10, 'urutan' => 2],
            ['nama_tahapan' => 'Jalan Kawasan', 'bobot_persen' => 15, 'urutan' => 3],
            ['nama_tahapan' => 'Drainase', 'bobot_persen' => 10, 'urutan' => 4],
            ['nama_tahapan' => 'Pagar Kawasan', 'bobot_persen' => 10, 'urutan' => 5],
            ['nama_tahapan' => 'Gerbang Perumahan', 'bobot_persen' => 10, 'urutan' => 6],
            ['nama_tahapan' => 'Instalasi Air Bersih Kawasan', 'bobot_persen' => 10, 'urutan' => 7],
            ['nama_tahapan' => 'Instalasi Listrik Kawasan', 'bobot_persen' => 10, 'urutan' => 8],
            ['nama_tahapan' => 'Taman dan Fasum', 'bobot_persen' => 5, 'urutan' => 9],
            ['nama_tahapan' => 'Septictank Komunal', 'bobot_persen' => 5, 'urutan' => 10],
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
