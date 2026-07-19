<?php

namespace Database\Seeders;

use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DetailRumahSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = User::query()->where('email', 'admin@ptali.com')->value('id')
            ?? User::query()->value('id');
        $created = 0;

        foreach (Perumahan::query()->where('status', 'aktif')->orderBy('id')->get() as $housingIndex => $housing) {
            for ($index = 1; $index <= 12; $index++) {
                $block = chr(65 + intdiv($index - 1, 4));
                $number = str_pad((string) ((($housingIndex + 1) * 100) + $index), 3, '0', STR_PAD_LEFT);
                $type = match ($index % 3) {
                    1 => ['36/72', 36, 72, 2, 1, 185000000],
                    2 => ['45/84', 45, 84, 2, 2, 225000000],
                    default => ['54/96', 54, 96, 3, 2, 285000000],
                };

                $unit = DetailRumah::withTrashed()->firstOrNew([
                    'perumahan_id' => $housing->id,
                    'kode_nlok' => $block,
                    'nomor_rumah' => $number,
                ]);
                $isNew = ! $unit->exists;

                // Jangan mengubah status unit yang sudah dipakai dalam proses penjualan.
                $unit->fill([
                    'tipe_rumah' => $type[0],
                    'model_unit' => $index % 2 === 0 ? 'Minimalis Modern' : 'Tropis Modern',
                    'luas_bangunan' => (string) $type[1],
                    'luas_tanah' => (string) $type[2],
                    'jumlah_lantai' => $index % 4 === 0 ? 2 : 1,
                    'kamar_tidur' => $type[3],
                    'kamar_mandi' => $type[4],
                    'daya_listrik' => $index % 3 === 0 ? '2200 VA' : '1300 VA',
                    'sumber_air' => 'PDAM dan sumur bor kawasan',
                    'carport' => '1 mobil',
                    'arah_hadap' => ['utara', 'timur', 'selatan', 'barat'][($index - 1) % 4],
                    'posisi_unit' => $index % 4 === 0 ? 'hook' : 'standar',
                    'harga_jual' => $type[5] + ($housingIndex * 15000000) + ($index * 1000000),
                    'status_pembangunan' => $index <= 4 ? 'selesai' : ($index <= 8 ? 'pembangunan' : 'kapling'),
                    'progress_terakhir' => $index <= 4 ? 100 : ($index <= 8 ? 45 : 0),
                    'spesifikasi' => 'Pondasi batu kali, dinding bata ringan, rangka atap baja ringan, lantai keramik.',
                    'catatan' => 'Unit contoh untuk pengujian alur SPR dan approval.',
                    'status' => 'aktif',
                    'created_by' => $unit->created_by ?: $creatorId,
                    'updated_by' => $creatorId,
                    ...($isNew ? ['status_penjualan' => 'tersedia', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $creatorId] : []),
                ]);
                // Seeder unit testing tidak perlu membentuk seluruh template HPP per unit.
                // Menonaktifkan event juga mencegah benturan write-lock SQLite saat aplikasi aktif.
                $unit->saveQuietly();

                if ($unit->trashed()) {
                    $unit->restoreQuietly();
                }
                $created++;
            }
        }

        $this->command?->info("{$created} unit contoh berhasil disiapkan.");
    }
}
