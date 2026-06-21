<?php

namespace Database\Seeders;

use App\Models\BarangMaterial;
use App\Models\StokMaterial;
use Illuminate\Database\Seeder;

class BarangMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            ['kode_barang' => 'BESI', 'nama_barang' => 'Besi Beton', 'satuan' => 'batang', 'harga_hpp' => 75000],
            ['kode_barang' => 'SEMEN', 'nama_barang' => 'Semen', 'satuan' => 'sak', 'harga_hpp' => 65000],
            ['kode_barang' => 'PASIR', 'nama_barang' => 'Pasir', 'satuan' => 'm3', 'harga_hpp' => 250000],
            ['kode_barang' => 'BATA', 'nama_barang' => 'Bata Merah', 'satuan' => 'pcs', 'harga_hpp' => 900],
            ['kode_barang' => 'KERAMIK', 'nama_barang' => 'Keramik', 'satuan' => 'dus', 'harga_hpp' => 125000],
        ];

        foreach ($materials as $material) {
            $data = $material + ['status' => 'aktif'];
            $barang = BarangMaterial::withTrashed()
                ->where('kode_barang', $material['kode_barang'])
                ->first();

            if ($barang) {
                $barang->fill($data)->save();

                if ($barang->trashed()) {
                    $barang->restore();
                }
            } else {
                $barang = BarangMaterial::create($data);
            }

            StokMaterial::query()->firstOrCreate(
                ['barang_material_id' => $barang->id, 'cabang_id' => null],
                ['qty' => 0],
            );
        }
    }
}
