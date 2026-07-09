<?php

namespace Database\Seeders;

use App\Models\DetailRumah;
use App\Models\DetailRumahHpp;
use App\Models\KelompokHpp;
use App\Models\Perumahan;
use App\Models\PerumahanHpp;
use App\Models\TahapanPembangunan;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Services\HppTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HppReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::query()->value('id') ?? 1;
        $kelompokTanah = $this->kelompok('Tanah & Legalitas Kawasan', 'tanah');
        $kelompokSarana = $this->kelompok('Sarana Kawasan', 'infrastruktur');
        $kelompokPrasarana = $this->kelompok('Prasarana Kawasan', 'infrastruktur');
        $kelompokBangunan = $this->kelompok('Bangunan Rumah', 'bangunan');

        $kawasanStages = [
            ['I RAB TANAH', 1, 28.77],
            ['II RAB SARANA', 2, 10.15],
            ['III RAB PRASARANA', 3, 12.54],
            ['IV RAB BANGUNAN', 4, 48.54],
        ];

        $unitStages = [
            ['PEK. PERSIAPAN & PONDASI', 1, 7.48],
            ['PEK. DINDING', 2, 26.30],
            ['PEK. FINISHING AWAL', 3, 14.44],
            ['PEK. PIPA AIR BERSIH & KOTOR', 4, 1.66],
            ['PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI', 5, 10.81],
            ['PEK. PAGAR & CAR PORT', 6, 14.96],
            ['PEK. TAMAN, PROFIL DAN PENGECATAN', 7, 6.38],
            ['PEK. PEMASANGAN ATAP', 8, 7.42],
            ['PEK. PEMASANGAN PLAFON', 9, 7.42],
            ['PEK. INSTALASI LISTRIK', 10, 3.13],
        ];

        $kawasanReference = [
            ['I RAB TANAH', $kelompokTanah->id, 'Harga Dasar Pembelian Tanah', 20053, 'M2', 150000],
            ['I RAB TANAH', $kelompokTanah->id, 'Biaya Pematangan Lahan (Cut And Fill)', 20053, 'M2', 70000],
            ['I RAB TANAH', $kelompokTanah->id, 'Biaya Penebangan Dan Pembersihan Pohon', 1, 'Ls', 25000000],
            ['I RAB TANAH', $kelompokTanah->id, 'BPHTB (Pajak Pembelian)', 5, '%', 2364992889],
            ['I RAB TANAH', $kelompokTanah->id, 'Pengukuran Tanah BPN', 1, 'Ls', 5000000],
            ['I RAB TANAH', $kelompokTanah->id, 'Biaya Pologoro/Kecamatan/Adat', 1, '%', 2364992889],
            ['I RAB TANAH', $kelompokTanah->id, 'Pengesahan Site Plan', 1, 'Ls', 5000000],
            ['I RAB TANAH', $kelompokTanah->id, 'Ijin Lokasi', 1, 'Ls', 10000000],
            ['I RAB TANAH', $kelompokTanah->id, 'Sertifikat HGB Induk a/n PT', 1, 'Ls', 90005000],
            ['II RAB SARANA', $kelompokSarana->id, 'Plat KPR', 150, 'Unit', 30000],
            ['II RAB SARANA', $kelompokSarana->id, 'Sertifikat Pemecahan', 150, 'Unit', 3000000],
            ['II RAB SARANA', $kelompokSarana->id, 'Instalasi Listrik', 150, 'Unit', 3500000],
            ['II RAB SARANA', $kelompokSarana->id, 'IMB', 150, 'Unit', 500000],
            ['II RAB SARANA', $kelompokSarana->id, 'Instalasi Air Bersih Dalam Rumah', 150, 'Unit', 3000000],
            ['II RAB SARANA', $kelompokSarana->id, 'SLF (Sertifikat Laik Fungsi)', 150, 'Unit', 1000000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Jalan Perumahan Rabat Beton ± 20%', 7334, 'M2', 170000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Saluran Drainase Perumahan', 2053, 'M', 80000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Taman & Penghijauan Perumahan', 1, 'Ls', 10000000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Gapura Perumahan', 1, 'Ls', 15000000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Perbaikan/Penyediaan Lahan Tempat Ibadah', 1, 'Ls', 10000000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Jaringan Pipa Distribusi PDAM', 150, 'Unit', 1500000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Jaringan & Tiang Listrik PLN', 150, 'Unit', 1800000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Trafo Listrik 50 KVA', 3, 'Unit', 30000000],
            ['III RAB PRASARANA', $kelompokPrasarana->id, 'Keamanan', 24, 'Bln', 500000],
        ];

        $unitReference = [
            ['I PEKERJAAN PONDASI', 'Pek. Pembuatan Bouwplank', 1, 'ls', 100000],
            ['I PEKERJAAN PONDASI', 'Pek. Tanah Timbunan', 11.57, 'm3', 15000],
            ['I PEKERJAAN PONDASI', 'Pas. Pondasi Batu Belah', 8.57, 'm3', 55000],
            ['I PEKERJAAN PONDASI', 'Pekerjaan bekisting', 10, 'm2', 180000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian BATA Ringan', 10, 'M3', 850000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian besi 8', 40, 'Kg', 70000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian BESI BEGEL', 35, 'Kg', 50000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian BESI 10', 20, 'Kg', 100000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian SEMEN 40 KG', 70, 'sak', 60000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian SEMEN Perekat', 17, 'sak', 110000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian Semen Acian Putih', 3, 'sak', 140000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian PASIR', 4, 'm3', 600000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'pemakaian CIPPING', 1, 'm3', 850000],
            ['II PEKERJAAN KONSTRUKSI DINDING', 'loster angin-angin 15x30', 18, 'Bh', 25000],
            ['III PEKERJAAN ATAP & PLAFON', 'Pemakaian SPANDEK 0,25 - 0.30 SILVER', 22, 'Bh', 103000],
            ['III PEKERJAAN ATAP & PLAFON', 'pemakaian RENG', 18, 'Bh', 70000],
            ['III PEKERJAAN ATAP & PLAFON', 'pemakaian KANAL C', 16, 'Bh', 97000],
            ['III PEKERJAAN ATAP & PLAFON', 'pemakaian LIST PLAN 3 M', 4, 'Bh', 55000],
            ['III PEKERJAAN ATAP & PLAFON', 'baut rangka, baut spandek, paku beton, kawat penggantung', 3, 'Bh', 342036],
            ['III PEKERJAAN ATAP & PLAFON', 'holow 2x4', 50, 'Bh', 20000],
            ['III PEKERJAAN ATAP & PLAFON', 'Kawat Pengikat Besi', 10, 'm3', 20000],
            ['III PEKERJAAN ATAP & PLAFON', 'Paku 5,7,10', 15, 'Bh', 25000],
            ['III PEKERJAAN ATAP & PLAFON', 'pemakaian Gypsum/calsibord 43 m2 (120x240)', 18, 'Bh', 60000],
            ['IV PEKERJAAN KUSEN, PINTU & JENDELA', 'BORONGAN KUSENG PINTU DAN JENDELA PER UNIT', 1, 'Ls', 6500000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'keramik lantai rumah lantai 40x40', 40, 'dos', 80000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'keramik lantai kamar mandi 25x25', 4, 'dos', 81000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'keramik dinding kamar mandi tinggi 1 M 25x40', 8, 'dos', 84000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'saptitank (2 cincin + 1 penutup)', 1, 'bh', 300000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'closet jongkok', 1, 'bh', 205221],
            ['V PEKERJAAN KERAMIK & SANITARI', 'pipa air bersih 1/2 (pimas/lucky)', 4, 'bh', 20000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'asesoris sambungan pipa', 4, 'bh', 47885],
            ['V PEKERJAAN KERAMIK & SANITARI', 'pipa pembuangan septitank 3\" (lucky/pimas)', 2, 'bh', 60000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'pipa air kotor 3 inci', 4, 'bh', 60000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'PINTU KAMAR MANDI', 1, 'bh', 250000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'kran air biasa', 3, 'bh', 17000],
            ['V PEKERJAAN KERAMIK & SANITARI', 'flor drain (SARINGAN PEMBUANGAN)', 1, 'bh', 17000],
            ['VI PEKERJAAN TAMBAHAN', 'penggunaan material balok - balok, papan, bambu, paku, triplex', 1, 'Ls', 684072],
            ['VI PEKERJAAN TAMBAHAN', 'BAHAN MATERIAL KABEL,PIPA,SAKLAR,LAMPU DLL', 1, 'Ls', 1736286],
            ['VII PEKERJAAN PENGECATAN', 'Plafon CAT STANDART MEREK ARIES', 1, 'Ls', 370000],
            ['VII PEKERJAAN PENGECATAN', 'dan badan rumah', 2, 'Ls', 670000],
            ['VII PEKERJAAN PENGECATAN', 'NO DROP MOCHA 4 KG', 3.5, 'Ls', 54100],
            ['VII PEKERJAAN PENGECATAN', 'NO DROP PUTIH 6 KG', 3.5, 'Ls', 54100],
            ['VII PEKERJAAN PENGECATAN', 'NO DROP ABU-ABU MUDA 4 KG', 3.5, 'Ls', 54100],
            ['VII PEKERJAAN PENGECATAN', 'NO DROP LIKESTONE 4 KG', 3.5, 'Ls', 54100],
        ];

        Perumahan::query()->each(function (Perumahan $perumahan) use ($userId, $kelompokBangunan, $kawasanReference, $unitReference, $kawasanStages, $unitStages) {
            foreach ($kawasanStages as [$name, $order, $weight]) {
                $this->projectStage($name, 'kawasan', $order, $weight, $perumahan->id);
            }

            $hpp = PerumahanHpp::query()->firstOrCreate(
                ['perumahan_id' => $perumahan->id],
                ['user_id' => $userId, 'tanggal_dibuat' => now()->toDateString()],
            );

            $hpp->detailPerumahanHpps()
                ->whereNull('nama_pekerjaan')
                ->where('jumlah_rab', 0)
                ->delete();

            $hpp->detailPerumahanHpps()
                ->whereIn('nama_pekerjaan', ['RAB Tanah', 'RAB Sarana', 'RAB Prasarana'])
                ->delete();

            foreach ($kawasanReference as $index => [$stageName, $kelompokId, $jobName, $volume, $unit, $price]) {
                $this->kawasanItem($hpp, $stageName, $kelompokId, $jobName, $volume, $unit, $price, $index + 1);
            }

            DetailRumah::query()
                ->where('perumahan_id', $perumahan->id)
                ->where('tipe_rumah', 'like', '%36%')
                ->each(function (DetailRumah $rumah) use ($userId, $kelompokBangunan, $unitReference, $unitStages, $perumahan) {
                    foreach ($unitStages as [$name, $order, $weight]) {
                        $this->projectStage($name, 'unit', $order, $weight, $perumahan->id, $rumah->id);
                    }

                    $unitHpp = DetailRumahHpp::query()->firstOrCreate(
                        ['detail_rumah_id' => $rumah->id],
                        ['user_id' => $userId, 'tanggal_dibuat' => now()->toDateString()],
                    );

                    $unitHpp->items()
                        ->whereNull('nama_pekerjaan')
                        ->where('jumlah_rab', 0)
                        ->delete();

                    $unitHpp->items()
                        ->whereIn('nama_pekerjaan', ['Pondasi', 'Konstruksi dinding', 'Atap dan plafon', 'Kusen, pintu dan jendela', 'Keramik dan sanitari', 'Tambahan', 'Pengecatan'])
                        ->delete();

                    foreach ($unitReference as $index => [$stageName, $jobName, $volume, $unit, $price]) {
                        $stageName = $this->unitStageName($stageName, $jobName);
                        $stage = TahapanPembangunan::query()
                            ->where('konteks', 'unit')
                            ->where('detail_rumah_id', $rumah->id)
                            ->where('nama_tahapan', $stageName)
                            ->first();
                        $amount = $volume * $price;
                        $unitHpp->items()->updateOrCreate(
                            ['tahapan_pembangunan_id' => $stage?->id, 'nama_pekerjaan' => $jobName],
                            [
                                'kelompok_hpp_id' => $kelompokBangunan->id,
                                'volume' => $volume,
                                'satuan' => $unit,
                                'harga_satuan' => $price,
                                'jumlah_rab' => $amount,
                                'urutan' => $index + 1,
                            ],
                        );
                    }
                });

            $type36Count = DetailRumah::query()->where('perumahan_id', $perumahan->id)->where('tipe_rumah', 'like', '%36%')->count();
            if ($type36Count > 0) {
                $this->kawasanItem($hpp, 'IV RAB BANGUNAN', $kelompokBangunan->id, 'Bangunan Rumah Type 36', $type36Count, 'Unit', 53802527, 4);
            }
        });

        app(HppTemplateService::class)->refreshSystemTemplates();
        $this->seedKawasanSystemTemplate($kawasanStages, $kawasanReference);
        $this->seedUnitSystemTemplate($unitStages, $unitReference, $kelompokBangunan->id);
    }

    protected function kelompok(string $name, string $category): KelompokHpp
    {
        return KelompokHpp::query()->firstOrCreate(
            ['nama_hpp' => $name],
            ['kategori' => $category, 'status' => 'aktif'],
        );
    }

    protected function kawasanItem(PerumahanHpp $hpp, string $stageName, int $kelompokId, string $jobName, float $volume, string $unit, float $price, int $order): void
    {
        $stage = TahapanPembangunan::query()
            ->where('konteks', 'kawasan')
            ->where('perumahan_id', $hpp->perumahan_id)
            ->whereNull('detail_rumah_id')
            ->where('nama_tahapan', $stageName)
            ->first();
        $amount = trim($unit) === '%' ? ($volume * $price / 100) : ($volume * $price);

        $hpp->detailPerumahanHpps()->updateOrCreate(
            ['tahapan_pembangunan_id' => $stage?->id, 'nama_pekerjaan' => $jobName],
            [
                'kelompok_hpp_id' => $kelompokId,
                'volume' => $volume,
                'satuan' => $unit,
                'harga_satuan' => $price,
                'jumlah_rab' => $amount,
                'urutan' => $order,
            ],
        );
    }

    protected function projectStage(
        string $name,
        string $context,
        int $order,
        float $weight,
        int $perumahanId,
        ?int $detailRumahId = null,
    ): TahapanPembangunan {
        return TahapanPembangunan::query()->updateOrCreate(
            [
                'nama_tahapan' => $name,
                'konteks' => $context,
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
            ],
            [
                'urutan' => $order,
                'bobot_persen' => $weight,
                'status' => 'aktif',
            ],
        );
    }

    protected function seedUnitSystemTemplate(array $unitStages, array $unitReference, int $kelompokId): void
    {
        DB::transaction(function () use ($unitStages, $unitReference, $kelompokId): void {
            $oldStageIds = DB::table('hpp_template_stages')
                ->where('konteks', 'unit')
                ->pluck('id');
            DB::table('hpp_template_items')
                ->whereIn('hpp_template_stage_id', $oldStageIds)
                ->delete();
            DB::table('hpp_template_stages')
                ->where('konteks', 'unit')
                ->delete();

            $stageIds = [];
            foreach ($unitStages as [$name, $order, $weight]) {
                $stageIds[$name] = DB::table('hpp_template_stages')->insertGetId([
                    'konteks' => 'unit',
                    'nama_tahapan' => $name,
                    'bobot_persen' => $weight,
                    'urutan' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($unitReference as $index => [$oldStageName, $jobName, $volume, $unit]) {
                $stageName = $this->unitStageName($oldStageName, $jobName);

                DB::table('hpp_template_items')->insert([
                    'hpp_template_stage_id' => $stageIds[$stageName],
                    'kelompok_hpp_id' => $kelompokId,
                    'nama_pekerjaan' => $jobName,
                    'volume' => 0,
                    'satuan' => $unit,
                    'urutan' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    protected function seedKawasanSystemTemplate(array $kawasanStages, array $kawasanReference): void
    {
        DB::transaction(function () use ($kawasanStages, $kawasanReference): void {
            $oldStageIds = DB::table('hpp_template_stages')
                ->where('konteks', 'kawasan')
                ->pluck('id');
            DB::table('hpp_template_items')
                ->whereIn('hpp_template_stage_id', $oldStageIds)
                ->delete();
            DB::table('hpp_template_stages')
                ->where('konteks', 'kawasan')
                ->delete();

            $stageIds = [];
            foreach ($kawasanStages as [$name, $order, $weight]) {
                $stageIds[$name] = DB::table('hpp_template_stages')->insertGetId([
                    'konteks' => 'kawasan',
                    'nama_tahapan' => $name,
                    'bobot_persen' => $weight,
                    'urutan' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($kawasanReference as $index => [$stageName, $kelompokId, $jobName, $volume, $unit]) {
                DB::table('hpp_template_items')->insert([
                    'hpp_template_stage_id' => $stageIds[$stageName],
                    'kelompok_hpp_id' => $kelompokId,
                    'nama_pekerjaan' => $jobName,
                    'volume' => 0,
                    'satuan' => $unit,
                    'urutan' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    protected function unitStageName(string $oldStageName, string $jobName): string
    {
        $job = Str::lower($jobName);

        if (Str::contains($job, ['bouwplank', 'tanah timbunan', 'pondasi', 'bekisting', 'galian', 'urugan'])) {
            return 'PEK. PERSIAPAN & PONDASI';
        }

        if (Str::contains($job, ['semen perekat', 'acian', 'plester'])) {
            return 'PEK. FINISHING AWAL';
        }

        if (Str::contains($job, ['bata', 'besi', 'begel', 'semen 40', 'pasir', 'cipping', 'loster', 'dinding'])) {
            return 'PEK. DINDING';
        }

        if (Str::contains($job, ['spandek', 'reng', 'kanal', 'list plan', 'lisplang', 'baut rangka', 'baut spandek', 'kawat pengikat besi', 'paku 5', 'atap'])) {
            return 'PEK. PEMASANGAN ATAP';
        }

        if (Str::contains($job, ['gypsum', 'calsibord', 'calsiboard', 'holow', 'plafon'])) {
            return 'PEK. PEMASANGAN PLAFON';
        }

        if (Str::contains($job, ['kabel', 'lampu', 'saklar', 'listrik'])) {
            return 'PEK. INSTALASI LISTRIK';
        }

        if (Str::contains($job, ['pipa', 'air bersih', 'air kotor', 'pembuangan', 'saptitank', 'septitank', 'sambungan pipa'])) {
            return 'PEK. PIPA AIR BERSIH & KOTOR';
        }

        if (Str::contains($job, ['balok', 'papan', 'bambu', 'triplex', 'pagar', 'car port', 'carport'])) {
            return 'PEK. PAGAR & CAR PORT';
        }

        if (Str::contains($job, ['cat', 'aries', 'no drop', 'nodrop', 'mocha', 'likestone', 'badan rumah', 'profil', 'taman'])) {
            return 'PEK. TAMAN, PROFIL DAN PENGECATAN';
        }

        if (Str::contains($job, ['kuseng', 'kusen', 'pintu', 'jendela', 'keramik', 'closet', 'kran', 'flor drain', 'floor drain', 'kamar mandi', 'sanitari', 'sanitasi'])) {
            return 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI';
        }

        return match ($oldStageName) {
            'I PEKERJAAN PONDASI' => 'PEK. PERSIAPAN & PONDASI',
            'II PEKERJAAN KONSTRUKSI DINDING' => Str::contains($job, ['semen perekat', 'acian'])
                ? 'PEK. FINISHING AWAL'
                : 'PEK. DINDING',
            'III PEKERJAAN ATAP & PLAFON' => Str::contains($job, ['gypsum', 'calsibord', 'plafon', 'holow'])
                ? 'PEK. PEMASANGAN PLAFON'
                : 'PEK. PEMASANGAN ATAP',
            'IV PEKERJAAN KUSEN, PINTU & JENDELA' => 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI',
            'V PEKERJAAN KERAMIK & SANITARI' => Str::contains($job, ['pipa', 'air bersih', 'air kotor', 'pembuangan'])
                ? 'PEK. PIPA AIR BERSIH & KOTOR'
                : 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI',
            'VI PEKERJAAN TAMBAHAN' => Str::contains($job, ['kabel', 'lampu', 'saklar', 'listrik'])
                ? 'PEK. INSTALASI LISTRIK'
                : 'PEK. PAGAR & CAR PORT',
            'VII PEKERJAAN PENGECATAN' => 'PEK. TAMAN, PROFIL DAN PENGECATAN',
            default => 'PEK. PERSIAPAN & PONDASI',
        };
    }
}
