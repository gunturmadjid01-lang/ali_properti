<?php

namespace Database\Seeders;

use App\Models\Gudang;
use Illuminate\Database\Seeder;

class GudangSeeder extends Seeder
{
    public function run(): void
    {
        Gudang::withTrashed()->updateOrCreate(
            ['kode_gudang' => 'GDG-UTAMA'],
            [
                'nama_gudang' => 'Gudang Utama',
                'penanggung_jawab' => 'Admin Gudang',
                'phone' => null,
                'alamat' => 'Gudang pusat material proyek',
                'catatan' => 'Gudang default untuk stok material bahan bangunan.',
                'status' => 'aktif',
                'deleted_at' => null,
            ],
        );
    }
}
