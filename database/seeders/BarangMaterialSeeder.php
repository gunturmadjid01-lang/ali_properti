<?php

namespace Database\Seeders;

use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\StokMaterial;
use Illuminate\Database\Seeder;

class BarangMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $gudang = Gudang::query()->where('kode_gudang', 'GDG-UTAMA')->first();

        $materials = [
            ['MAT-BOUWPLANK', 'Material Bouwplank', 'ls', 100000, 'Pondasi', null],
            ['MAT-TANAH-TIMBUN', 'Tanah Timbunan', 'm3', 15000, 'Pondasi', null],
            ['MAT-BATU-BELAH', 'Batu Belah Pondasi', 'm3', 55000, 'Pondasi', null],
            ['MAT-BEKISTING', 'Material Bekisting', 'm2', 180000, 'Pondasi', null],
            ['MAT-BATA-RINGAN', 'Bata Ringan', 'm3', 850000, 'Dinding', null],
            ['MAT-BESI-8', 'Besi Beton 8 mm', 'kg', 70000, 'Besi & Baja', null],
            ['MAT-BESI-BEGEL', 'Besi Begel', 'kg', 50000, 'Besi & Baja', null],
            ['MAT-BESI-10', 'Besi Beton 10 mm', 'kg', 100000, 'Besi & Baja', null],
            ['MAT-SEMEN-40KG', 'Semen 40 Kg', 'sak', 60000, 'Pasir, Batu & Semen', null],
            ['MAT-SEMEN-PEREKAT', 'Semen Perekat Bata Ringan', 'sak', 110000, 'Dinding', null],
            ['MAT-SEMEN-ACIAN', 'Semen Acian Putih', 'sak', 140000, 'Cat & Finishing', null],
            ['MAT-PASIR', 'Pasir', 'm3', 600000, 'Pasir, Batu & Semen', null],
            ['MAT-CIPPING', 'Cipping', 'm3', 850000, 'Pasir, Batu & Semen', null],
            ['MAT-LOSTER-1530', 'Loster Angin-Angin 15x30', 'bh', 25000, 'Dinding', null],
            ['MAT-SPANDEK-SILVER', 'Spandek 0.25 - 0.30 Silver', 'bh', 103000, 'Atap', null],
            ['MAT-RENG', 'Reng Baja Ringan', 'bh', 70000, 'Atap', null],
            ['MAT-KANAL-C', 'Kanal C Baja Ringan', 'bh', 97000, 'Atap', null],
            ['MAT-LIST-PLAN', 'List Plan 3 M', 'bh', 55000, 'Atap', null],
            ['MAT-BAUT-ATAP', 'Baut Rangka, Baut Spandek, Paku Beton, Kawat Penggantung', 'set', 342036, 'Atap', null],
            ['MAT-HOLLOW-2X4', 'Hollow 2x4', 'bh', 20000, 'Plafon', null],
            ['MAT-KAWAT-BESI', 'Kawat Pengikat Besi', 'roll', 20000, 'Besi & Baja', null],
            ['MAT-PAKU', 'Paku 5, 7, 10', 'kg', 25000, 'Alat Kerja', null],
            ['MAT-GYPSUM-CALSIBOARD', 'Gypsum / Calsiboard 120x240', 'bh', 60000, 'Plafon', null],
            ['MAT-KUSEN-PINTU-JENDELA', 'Kusen, Pintu dan Jendela per Unit', 'ls', 6500000, 'Pintu, Jendela & Kusen', null],
            ['MAT-KERAMIK-4040', 'Keramik Lantai Rumah 40x40', 'dus', 80000, 'Lantai & Keramik', null],
            ['MAT-KERAMIK-KM-2525', 'Keramik Lantai Kamar Mandi 25x25', 'dus', 81000, 'Lantai & Keramik', null],
            ['MAT-KERAMIK-DINDING-2540', 'Keramik Dinding Kamar Mandi 25x40', 'dus', 84000, 'Lantai & Keramik', null],
            ['MAT-SEPTIC-TANK', 'Septictank 2 Cincin + 1 Penutup', 'set', 300000, 'Sanitair', null],
            ['MAT-CLOSET-JONGKOK', 'Closet Jongkok', 'bh', 205221, 'Sanitair', null],
            ['MAT-PIPA-AIR-12', 'Pipa Air Bersih 1/2', 'bh', 20000, 'Pipa & Plumbing', 'Pimas / Lucky'],
            ['MAT-AKSESORIS-PIPA', 'Aksesoris Sambungan Pipa', 'set', 47885, 'Pipa & Plumbing', null],
            ['MAT-PIPA-SEPTIC-3', 'Pipa Pembuangan Septictank 3 Inch', 'bh', 60000, 'Pipa & Plumbing', 'Lucky / Pimas'],
            ['MAT-PIPA-AIR-KOTOR-3', 'Pipa Air Kotor 3 Inch', 'bh', 60000, 'Pipa & Plumbing', null],
            ['MAT-PINTU-KM', 'Pintu Kamar Mandi', 'bh', 250000, 'Pintu, Jendela & Kusen', null],
            ['MAT-KRAN-AIR', 'Kran Air Biasa', 'bh', 17000, 'Sanitair', null],
            ['MAT-FLOOR-DRAIN', 'Floor Drain / Saringan Pembuangan', 'bh', 17000, 'Sanitair', null],
            ['MAT-BALOK-PAPAN-BAMBU', 'Balok, Papan, Bambu, Paku, Triplex', 'ls', 684072, 'Kayu & Papan', null],
            ['MAT-LISTRIK-PAKET', 'Kabel, Pipa, Saklar, Lampu dan Aksesoris Listrik', 'ls', 1736286, 'Listrik', null],
            ['MAT-CAT-PLAFON-ARIES', 'Cat Plafon Standar', 'ls', 370000, 'Cat & Finishing', 'Aries'],
            ['MAT-CAT-BADAN-RUMAH', 'Cat Badan Rumah', 'ls', 670000, 'Cat & Finishing', null],
            ['MAT-NODROP-MOCHA', 'No Drop Mocha 4 Kg', 'kaleng', 54100, 'Cat & Finishing', 'No Drop'],
            ['MAT-NODROP-PUTIH', 'No Drop Putih 6 Kg', 'kaleng', 54100, 'Cat & Finishing', 'No Drop'],
            ['MAT-NODROP-ABU', 'No Drop Abu-Abu Muda 4 Kg', 'kaleng', 54100, 'Cat & Finishing', 'No Drop'],
            ['MAT-NODROP-LIKESTONE', 'No Drop Likestone 4 Kg', 'kaleng', 54100, 'Cat & Finishing', 'No Drop'],
        ];

        foreach ($materials as [$code, $name, $unit, $price, $type, $brand]) {
            $data = [
                'kode_barang' => $code,
                'nama_barang' => $name,
                'kategori_material' => $type,
                'jenis_material' => $type,
                'merk_material' => $brand,
                'satuan' => $unit,
                'harga_hpp' => $price,
                'stok_minimum' => 0,
                'status' => 'aktif',
            ];

            $barang = BarangMaterial::withTrashed()
                ->where('kode_barang', $code)
                ->first();

            if ($barang) {
                $barang->fill($data)->save();

                if ($barang->trashed()) {
                    $barang->restore();
                }
            } else {
                $barang = BarangMaterial::create($data);
            }

            if ($gudang) {
                StokMaterial::query()->firstOrCreate(
                    ['gudang_id' => $gudang->id, 'barang_material_id' => $barang->id],
                    ['cabang_id' => null, 'qty' => 0],
                );
            }
        }
    }
}
