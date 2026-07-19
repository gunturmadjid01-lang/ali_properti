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
                ->with(['tahapanPembangunan', 'detailRumahHpp.detailRumah:id,perumahan_id'])
                ->get()
                ->each(function (DetailRumahHppItem $item): void {
                    $rumah = $item->detailRumahHpp?->detailRumah;

                    if (! $rumah) {
                        return;
                    }

                    $stage = $this->stageFor(
                        $this->targetStageName($item->tahapanPembangunan?->nama_tahapan, (string) ($item->nama_pekerjaan ?? '')),
                        (int) $rumah->perumahan_id,
                        (int) $rumah->id,
                    );

                    $item->update(['tahapan_pembangunan_id' => $stage->id]);
                });

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereNotIn('nama_tahapan', array_keys($this->unitStages))
                ->update(['status' => 'nonaktif', 'updated_at' => now()]);

            $this->refreshUnitTemplate();
        });
    }

    public function down(): void
    {
        // Final state yang diinginkan adalah 10 PEK, jadi tidak dibalik otomatis.
    }

    private function targetStageName(?string $stageName, string $jobName): string
    {
        $stage = Str::lower((string) $stageName);
        $job = Str::lower($jobName);

        if (Str::contains($stage, ['persiapan', 'pondasi', 'tahap i']) || Str::contains($job, ['bouwplank', 'pondasi', 'galian', 'urugan', 'timbunan', 'bekisting'])) {
            return 'PEK. PERSIAPAN & PONDASI';
        }

        if (Str::contains($stage, ['finishing awal']) || Str::contains($job, ['plester', 'acian', 'semen perekat', 'semen acian'])) {
            return 'PEK. FINISHING AWAL';
        }

        if (Str::contains($stage, ['dinding', 'tahap ii']) || Str::contains($job, ['bata', 'dinding', 'besi', 'begel', 'semen 40', 'pasir', 'cipping', 'loster'])) {
            return 'PEK. DINDING';
        }

        if (Str::contains($stage, ['pipa', 'air bersih', 'air kotor']) || Str::contains($job, ['pipa', 'air bersih', 'air kotor', 'pembuangan', 'septitank'])) {
            return 'PEK. PIPA AIR BERSIH & KOTOR';
        }

        if (Str::contains($stage, ['keramik', 'sanitasi', 'kusen', 'pintu', 'jendela', 'tahap iv', 'tahap v']) || Str::contains($job, ['keramik', 'closet', 'flor drain', 'floor drain', 'kran', 'kamar mandi', 'kusen', 'kuseng', 'pintu', 'jendela'])) {
            return 'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI';
        }

        if (Str::contains($stage, ['pagar', 'car port']) || Str::contains($job, ['pagar', 'carport', 'car port', 'kanopi'])) {
            return 'PEK. PAGAR & CAR PORT';
        }

        if (Str::contains($stage, ['taman', 'profil', 'pengecatan']) || Str::contains($job, ['cat', 'aries', 'no drop', 'nodrop', 'profil', 'taman'])) {
            return 'PEK. TAMAN, PROFIL DAN PENGECATAN';
        }

        if (Str::contains($stage, ['plafon']) || Str::contains($job, ['plafon', 'gypsum', 'calsiboard', 'calsibord', 'holow'])) {
            return 'PEK. PEMASANGAN PLAFON';
        }

        if (Str::contains($stage, ['listrik']) || Str::contains($job, ['kabel', 'lampu', 'saklar', 'listrik'])) {
            return 'PEK. INSTALASI LISTRIK';
        }

        if (Str::contains($stage, ['atap', 'tahap iii']) || Str::contains($job, ['spandek', 'reng', 'kanal', 'list plan', 'lisplang', 'atap'])) {
            return 'PEK. PEMASANGAN ATAP';
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

    private function refreshUnitTemplate(): void
    {
        if (! Schema::hasTable('hpp_template_stages') || ! Schema::hasTable('hpp_template_items')) {
            return;
        }

        $oldStageIds = DB::table('hpp_template_stages')->where('konteks', 'unit')->pluck('id');
        $oldItems = DB::table('hpp_template_items')
            ->leftJoin('hpp_template_stages', 'hpp_template_stages.id', '=', 'hpp_template_items.hpp_template_stage_id')
            ->whereIn('hpp_template_items.hpp_template_stage_id', $oldStageIds)
            ->select('hpp_template_items.*', 'hpp_template_stages.nama_tahapan as stage_name')
            ->orderBy('hpp_template_items.urutan')
            ->get();

        DB::table('hpp_template_items')->whereIn('hpp_template_stage_id', $oldStageIds)->delete();
        DB::table('hpp_template_stages')->where('konteks', 'unit')->delete();

        $stageMap = collect();
        foreach ($this->unitStages as $name => [$order, $weight]) {
            $stageMap->put($name, DB::table('hpp_template_stages')->insertGetId([
                'konteks' => 'unit',
                'nama_tahapan' => $name,
                'bobot_persen' => $weight,
                'urutan' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        foreach ($oldItems as $index => $item) {
            $targetName = $this->targetStageName($item->stage_name, (string) $item->nama_pekerjaan);

            DB::table('hpp_template_items')->insert([
                'hpp_template_stage_id' => $stageMap->get($targetName),
                'kelompok_hpp_id' => $item->kelompok_hpp_id,
                'nama_pekerjaan' => $item->nama_pekerjaan,
                'volume' => $item->volume,
                'satuan' => $item->satuan,
                'urutan' => $item->urutan ?: $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
