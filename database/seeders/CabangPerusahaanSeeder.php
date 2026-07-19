<?php

namespace Database\Seeders;

use App\Models\CabangPerusahaan;
use Illuminate\Database\Seeder;

class CabangPerusahaanSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'kode_cabang' => 'PST-001',
            'nama_cabang' => 'PT Ali Properti Indonesia Pusat',
            'address' => 'Mamuju, Sulawesi Barat',
            'phone' => '082196903414',
            'latitude' => null,
            'longtitude' => null,
            'logo' => null,
            'image' => null,
            'deskripsi' => 'Kantor pusat pengelolaan pemasaran dan administrasi Perumahan Sidratul Muntaha.',
            'emaiil' => 'admin@aliproperti.test',
            'manager_name' => 'Manager Pusat',
            'status' => 'aktif',
            'type' => 'pusat',
        ];

        $cabang = CabangPerusahaan::withTrashed()
            ->where('kode_cabang', $data['kode_cabang'])
            ->orWhere('nama_cabang', $data['nama_cabang'])
            ->first();

        if ($cabang) {
            $cabang->fill($data)->save();

            if ($cabang->trashed()) {
                $cabang->restore();
            }

            return;
        }

        CabangPerusahaan::create($data);
    }
}
