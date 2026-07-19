<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $stages = [
        'I RAB TANAH' => [1, 28.77],
        'II RAB SARANA' => [2, 10.15],
        'III RAB PRASARANA' => [3, 12.54],
        'IV RAB BANGUNAN' => [4, 48.54],
    ];

    private array $legacyKawasanStages = [
        'Pematangan Lahan',
        'Cut and Fill',
        'Jalan Kawasan',
        'Drainase',
        'Pagar Kawasan',
        'Gerbang Perumahan',
        'Instalasi Air Bersih Kawasan',
        'Instalasi Listrik Kawasan',
        'Taman dan Fasum',
        'Septictank Komunal',
    ];

    private array $items = [
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Harga Dasar Pembelian Tanah', 20053, 'M2', 1],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Biaya Pematangan Lahan (Cut And Fill)', 20053, 'M2', 2],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Biaya Penebangan Dan Pembersihan Pohon', 1, 'Ls', 3],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'BPHTB (Pajak Pembelian)', 5, '%', 4],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Pengukuran Tanah BPN', 1, 'Ls', 5],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Biaya Pologoro/Kecamatan/Adat', 1, '%', 6],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Pengesahan Site Plan', 1, 'Ls', 7],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Ijin Lokasi', 1, 'Ls', 8],
        ['I RAB TANAH', 'Tanah & Legalitas Kawasan', 'tanah', 'Sertifikat HGB Induk a/n PT', 1, 'Ls', 9],
        ['II RAB SARANA', 'Sarana Kawasan', 'infrastruktur', 'Plat KPR', 150, 'Unit', 1],
        ['II RAB SARANA', 'Sarana Kawasan', 'infrastruktur', 'Sertifikat Pemecahan', 150, 'Unit', 2],
        ['II RAB SARANA', 'Sarana Kawasan', 'infrastruktur', 'Instalasi Listrik', 150, 'Unit', 3],
        ['II RAB SARANA', 'Sarana Kawasan', 'infrastruktur', 'IMB', 150, 'Unit', 4],
        ['II RAB SARANA', 'Sarana Kawasan', 'infrastruktur', 'Instalasi Air Bersih Dalam Rumah', 150, 'Unit', 5],
        ['II RAB SARANA', 'Sarana Kawasan', 'infrastruktur', 'SLF (Sertifikat Laik Fungsi)', 150, 'Unit', 6],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Jalan Perumahan Rabat Beton +/- 20%', 7334, 'M2', 1],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Saluran Drainase Perumahan', 2053, 'M', 2],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Taman & Penghijauan Perumahan', 1, 'Ls', 3],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Gapura Perumahan', 1, 'Ls', 4],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Perbaikan/Penyediaan Lahan Tempat Ibadah', 1, 'Ls', 5],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Jaringan Pipa Distribusi PDAM', 150, 'Unit', 6],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Jaringan & Tiang Listrik PLN', 150, 'Unit', 7],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Trafo Listrik 50 KVA', 3, 'Unit', 8],
        ['III RAB PRASARANA', 'Prasarana Kawasan', 'infrastruktur', 'Keamanan', 24, 'Bln', 9],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->normalizeMasterStages();
            $this->normalizeSystemTemplate();
            $this->normalizeExistingPerumahan();
        });
    }

    private function normalizeMasterStages(): void
    {
        foreach ($this->stages as $name => [$order, $weight]) {
            DB::table('tahapan_pembangunans')->updateOrInsert(
                [
                    'nama_tahapan' => $name,
                    'konteks' => 'kawasan',
                    'perumahan_id' => null,
                    'detail_rumah_id' => null,
                ],
                [
                    'bobot_persen' => $weight,
                    'urutan' => $order,
                    'status' => 'aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        DB::table('tahapan_pembangunans')
            ->where('konteks', 'kawasan')
            ->whereNull('perumahan_id')
            ->whereNull('detail_rumah_id')
            ->whereIn('nama_tahapan', $this->legacyKawasanStages)
            ->update(['status' => 'nonaktif', 'updated_at' => now()]);
    }

    private function normalizeSystemTemplate(): void
    {
        if (! Schema::hasTable('hpp_template_stages') || ! Schema::hasTable('hpp_template_items')) {
            return;
        }

        $oldStageIds = DB::table('hpp_template_stages')->where('konteks', 'kawasan')->pluck('id');
        DB::table('hpp_template_items')->whereIn('hpp_template_stage_id', $oldStageIds)->delete();
        DB::table('hpp_template_stages')->where('konteks', 'kawasan')->delete();

        $stageIds = [];
        foreach ($this->stages as $name => [$order, $weight]) {
            $stageIds[$name] = DB::table('hpp_template_stages')->insertGetId([
                'konteks' => 'kawasan',
                'nama_tahapan' => $name,
                'bobot_persen' => $weight,
                'urutan' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->items as [$stageName, $groupName, $category, $jobName, $volume, $unit, $order]) {
            DB::table('hpp_template_items')->insert([
                'hpp_template_stage_id' => $stageIds[$stageName],
                'kelompok_hpp_id' => $this->kelompokId($groupName, $category),
                'nama_pekerjaan' => $jobName,
                'volume' => 0,
                'satuan' => $unit,
                'urutan' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizeExistingPerumahan(): void
    {
        $perumahanIds = DB::table('perumahans')->pluck('id');

        foreach ($perumahanIds as $perumahanId) {
            $stageIds = $this->projectStageIds((int) $perumahanId);
            $hppId = DB::table('perumahan_hpps')->where('perumahan_id', $perumahanId)->value('id');

            if (! $hppId) {
                continue;
            }

            foreach ($this->items as [$stageName, $groupName, $category, $jobName, $volume, $unit, $order]) {
                $stageId = $stageIds[$stageName];
                $groupId = $this->kelompokId($groupName, $category);
                $existingId = DB::table('detail_perumahan_hpps')
                    ->where('perumahan_hpp_id', $hppId)
                    ->where('nama_pekerjaan', $jobName)
                    ->whereNull('deleted_at')
                    ->value('id');

                if ($existingId) {
                    DB::table('detail_perumahan_hpps')
                        ->where('id', $existingId)
                        ->update([
                            'tahapan_pembangunan_id' => $stageId,
                            'kelompok_hpp_id' => $groupId,
                            'satuan' => $unit,
                            'urutan' => $order,
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('detail_perumahan_hpps')->insert([
                    'perumahan_hpp_id' => $hppId,
                    'tahapan_pembangunan_id' => $stageId,
                    'kelompok_hpp_id' => $groupId,
                    'nama_pekerjaan' => $jobName,
                    'volume' => 0,
                    'satuan' => $unit,
                    'harga_satuan' => 0,
                    'jumlah_rab' => 0,
                    'urutan' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('detail_perumahan_hpps')
                ->where('perumahan_hpp_id', $hppId)
                ->where('nama_pekerjaan', 'like', 'Bangunan Rumah Type %')
                ->whereNull('deleted_at')
                ->update([
                    'tahapan_pembangunan_id' => $stageIds['IV RAB BANGUNAN'],
                    'updated_at' => now(),
                ]);

            $this->deactivateEmptyLegacyProjectStages((int) $perumahanId);
        }
    }

    private function projectStageIds(int $perumahanId): array
    {
        $ids = [];

        foreach ($this->stages as $name => [$order, $weight]) {
            DB::table('tahapan_pembangunans')->updateOrInsert(
                [
                    'nama_tahapan' => $name,
                    'konteks' => 'kawasan',
                    'perumahan_id' => $perumahanId,
                    'detail_rumah_id' => null,
                ],
                [
                    'bobot_persen' => $weight,
                    'urutan' => $order,
                    'status' => 'aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $ids[$name] = DB::table('tahapan_pembangunans')
                ->where('nama_tahapan', $name)
                ->where('konteks', 'kawasan')
                ->where('perumahan_id', $perumahanId)
                ->whereNull('detail_rumah_id')
                ->whereNull('deleted_at')
                ->value('id');
        }

        return $ids;
    }

    private function deactivateEmptyLegacyProjectStages(int $perumahanId): void
    {
        $usedStageIds = DB::table('detail_perumahan_hpps')
            ->join('perumahan_hpps', 'perumahan_hpps.id', '=', 'detail_perumahan_hpps.perumahan_hpp_id')
            ->where('perumahan_hpps.perumahan_id', $perumahanId)
            ->whereNull('detail_perumahan_hpps.deleted_at')
            ->whereNotNull('detail_perumahan_hpps.tahapan_pembangunan_id')
            ->pluck('detail_perumahan_hpps.tahapan_pembangunan_id')
            ->unique();

        DB::table('tahapan_pembangunans')
            ->where('konteks', 'kawasan')
            ->where('perumahan_id', $perumahanId)
            ->whereNull('detail_rumah_id')
            ->whereIn('nama_tahapan', $this->legacyKawasanStages)
            ->whereNotIn('id', $usedStageIds)
            ->update(['status' => 'nonaktif', 'updated_at' => now()]);
    }

    private function kelompokId(string $name, string $category): int
    {
        DB::table('kelompok_hpps')->updateOrInsert(
            ['nama_hpp' => $name],
            [
                'kategori' => $category,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (int) DB::table('kelompok_hpps')->where('nama_hpp', $name)->value('id');
    }

    public function down(): void
    {
        // Data RAB yang sudah dinormalisasi tidak dibalik otomatis.
    }
};
