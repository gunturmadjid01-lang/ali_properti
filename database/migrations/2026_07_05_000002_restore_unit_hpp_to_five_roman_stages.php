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
    private array $romanStages = [
        'Tahap I' => [1, 20.00],
        'Tahap II' => [2, 20.00],
        'Tahap III' => [3, 20.00],
        'Tahap IV' => [4, 20.00],
        'Tahap V' => [5, 20.00],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $scopes = TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->select('perumahan_id', 'detail_rumah_id')
                ->distinct()
                ->get();

            DetailRumah::query()
                ->select('id', 'perumahan_id')
                ->get()
                ->each(function (DetailRumah $rumah) use ($scopes): void {
                    if (! $scopes->contains(fn ($scope) => (int) $scope->detail_rumah_id === (int) $rumah->id)) {
                        $scopes->push((object) [
                            'perumahan_id' => $rumah->perumahan_id,
                            'detail_rumah_id' => $rumah->id,
                        ]);
                    }
                });

            foreach ($scopes as $scope) {
                if (! $scope->perumahan_id || ! $scope->detail_rumah_id) {
                    continue;
                }

                foreach ($this->romanStages as $name => [$order, $weight]) {
                    TahapanPembangunan::query()->updateOrCreate(
                        [
                            'nama_tahapan' => $name,
                            'konteks' => 'unit',
                            'perumahan_id' => $scope->perumahan_id,
                            'detail_rumah_id' => $scope->detail_rumah_id,
                        ],
                        [
                            'urutan' => $order,
                            'bobot_persen' => $weight,
                            'status' => 'aktif',
                        ],
                    );
                }
            }

            DetailRumahHppItem::query()
                ->with(['tahapanPembangunan', 'detailRumahHpp.detailRumah:id,perumahan_id'])
                ->get()
                ->each(function (DetailRumahHppItem $item): void {
                    $rumah = $item->detailRumahHpp?->detailRumah;

                    if (! $rumah) {
                        return;
                    }

                    $targetName = $this->targetStageName(
                        $item->tahapanPembangunan?->nama_tahapan,
                        (string) ($item->nama_pekerjaan ?? ''),
                    );
                    $targetStage = $this->stageFor($targetName, (int) $rumah->perumahan_id, (int) $rumah->id);
                    $item->update(['tahapan_pembangunan_id' => $targetStage->id]);
                });

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereNotIn('nama_tahapan', array_keys($this->romanStages))
                ->update(['status' => 'nonaktif', 'updated_at' => now()]);

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereIn('nama_tahapan', array_keys($this->romanStages))
                ->update(['status' => 'aktif', 'updated_at' => now()]);

            $this->refreshUnitTemplate();
        });
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis agar item RAB yang sudah dipindahkan tetap stabil.
    }

    private function targetStageName(?string $stageName, string $jobName): string
    {
        $stage = Str::lower((string) $stageName);
        $job = Str::lower($jobName);

        if (Str::contains($stage, ['tahap i', 'pekerjaan pondasi', 'persiapan & pondasi', 'persiapan dan pondasi'])) {
            return 'Tahap I';
        }

        if (Str::contains($stage, ['tahap ii', 'konstruksi dinding', 'pek. dinding', 'finishing awal'])) {
            return 'Tahap II';
        }

        if (Str::contains($stage, ['tahap iii', 'atap', 'plafon'])) {
            return 'Tahap III';
        }

        if (Str::contains($stage, ['tahap iv', 'kusen', 'pintu', 'jendela', 'instalasi listrik'])) {
            return 'Tahap IV';
        }

        if (Str::contains($stage, ['tahap v', 'keramik', 'sanitari', 'pipa', 'pagar', 'car port', 'taman', 'pengecatan', 'tambahan'])) {
            return 'Tahap V';
        }

        if (Str::contains($job, ['pondasi', 'sloof', 'galian', 'urugan', 'bouwplank'])) {
            return 'Tahap I';
        }

        if (Str::contains($job, ['bata', 'dinding', 'kolom', 'ring balok', 'acian', 'plester'])) {
            return 'Tahap II';
        }

        if (Str::contains($job, ['atap', 'rangka', 'genteng', 'plafon', 'gypsum', 'calsiboard', 'calsibord'])) {
            return 'Tahap III';
        }

        if (Str::contains($job, ['kusen', 'pintu', 'jendela', 'kabel', 'lampu', 'saklar', 'listrik'])) {
            return 'Tahap IV';
        }

        return 'Tahap V';
    }

    private function stageFor(string $name, int $perumahanId, int $detailRumahId): TahapanPembangunan
    {
        [$order, $weight] = $this->romanStages[$name];

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
        foreach ($this->romanStages as $name => [$order, $weight]) {
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
