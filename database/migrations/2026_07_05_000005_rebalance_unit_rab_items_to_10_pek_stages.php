<?php

use App\Models\DetailRumah;
use App\Models\DetailRumahHppItem;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $unitStages = [
        'PEK. PERSIAPAN & PONDASI' => [1, 7.48],
        'PEK. DINDING' => [2, 26.30],
        'PEK. FINISHING AWAL' => [3, 14.44],
        'PEK. PIPA AIR BERSIH & KOTOR' => [4, 1.66],
        'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI' => [5, 10.81],
        'PEK. PAGAR & CAR PORT' => [6, 14.96],
        'PEK. TAMAN, PROFIL DAN PENGECATAN' => [7, 6.38],
        'PEK. PEMASANGAN ATAP' => [8, 7.42],
        'PEK. PEMASANGAN PLAFON' => [9, 7.42],
        'PEK. INSTALASI LISTRIK' => [10, 3.13],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            DetailRumah::query()
                ->select('id', 'perumahan_id')
                ->get()
                ->each(function (DetailRumah $rumah): void {
                    foreach ($this->unitStages as $name => [$order, $weight]) {
                        $this->stageFor($name, (int) $rumah->perumahan_id, (int) $rumah->id);
                    }
                });

            DetailRumahHppItem::query()
                ->with('detailRumahHpp.detailRumah:id,perumahan_id')
                ->get()
                ->each(function (DetailRumahHppItem $item): void {
                    $rumah = $item->detailRumahHpp?->detailRumah;

                    if (! $rumah) {
                        return;
                    }

                    $stage = $this->stageFor(
                        $this->targetStageName((string) ($item->nama_pekerjaan ?? '')),
                        (int) $rumah->perumahan_id,
                        (int) $rumah->id,
                    );

                    $item->update(['tahapan_pembangunan_id' => $stage->id]);
                });

            $this->rebalanceUnitTemplate();

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereNotIn('nama_tahapan', array_keys($this->unitStages))
                ->update(['status' => 'nonaktif', 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        // Final state yang diinginkan adalah template 10 PEK yang sudah terisi.
    }

    private function rebalanceUnitTemplate(): void
    {
        if (! Schema::hasTable('hpp_template_stages') || ! Schema::hasTable('hpp_template_items')) {
            return;
        }

        $stageIds = [];
        foreach ($this->unitStages as $name => [$order, $weight]) {
            $stageIds[$name] = DB::table('hpp_template_stages')->updateOrInsert(
                ['konteks' => 'unit', 'nama_tahapan' => $name],
                ['bobot_persen' => $weight, 'urutan' => $order, 'updated_at' => now(), 'created_at' => now()],
            );
            $stageIds[$name] = DB::table('hpp_template_stages')
                ->where('konteks', 'unit')
                ->where('nama_tahapan', $name)
                ->value('id');
        }

        DB::table('hpp_template_items')
            ->join('hpp_template_stages', 'hpp_template_stages.id', '=', 'hpp_template_items.hpp_template_stage_id')
            ->where('hpp_template_stages.konteks', 'unit')
            ->select('hpp_template_items.id', 'hpp_template_items.nama_pekerjaan')
            ->orderBy('hpp_template_items.id')
            ->get()
            ->each(function ($item) use ($stageIds): void {
                $targetName = $this->targetStageName((string) $item->nama_pekerjaan);

                DB::table('hpp_template_items')
                    ->where('id', $item->id)
                    ->update([
                        'hpp_template_stage_id' => $stageIds[$targetName],
                        'updated_at' => now(),
                    ]);
            });
    }

    private function targetStageName(string $jobName): string
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

        return 'PEK. PERSIAPAN & PONDASI';
    }

    private function stageFor(string $name, int $perumahanId, int $detailRumahId): TahapanPembangunan
    {
        [$order, $weight] = $this->unitStages[$name];

        return TahapanPembangunan::query()->updateOrCreate(
            [
                'nama_tahapan' => $name,
                'konteks' => 'unit',
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
};
