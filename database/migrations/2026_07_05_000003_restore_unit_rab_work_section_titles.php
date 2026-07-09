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
    private array $unitSections = [
        'PEKERJAAN PONDASI' => [1, 20.00],
        'PEKERJAAN KONSTRUKSI DINDING' => [2, 20.00],
        'PEKERJAAN ATAP DAN PLAFON' => [3, 20.00],
        'PEKERJAAN KUSEN, PINTU & JENDELA' => [4, 20.00],
        'PEKERJAAN KERAMIK & SANITARI' => [5, 20.00],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            DetailRumah::query()
                ->select('id', 'perumahan_id')
                ->get()
                ->each(function (DetailRumah $rumah): void {
                    foreach ($this->unitSections as $name => [$order, $weight]) {
                        TahapanPembangunan::query()->updateOrCreate(
                            [
                                'nama_tahapan' => $name,
                                'konteks' => 'unit',
                                'perumahan_id' => $rumah->perumahan_id,
                                'detail_rumah_id' => $rumah->id,
                            ],
                            [
                                'urutan' => $order,
                                'bobot_persen' => $weight,
                                'status' => 'aktif',
                            ],
                        );
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
                        $this->targetSectionName($item->tahapanPembangunan?->nama_tahapan, (string) ($item->nama_pekerjaan ?? '')),
                        (int) $rumah->perumahan_id,
                        (int) $rumah->id,
                    );
                    $item->update(['tahapan_pembangunan_id' => $stage->id]);
                });

            TahapanPembangunan::query()
                ->where('konteks', 'unit')
                ->whereNotIn('nama_tahapan', array_keys($this->unitSections))
                ->update(['status' => 'nonaktif', 'updated_at' => now()]);

            $this->refreshUnitTemplate();
        });
    }

    public function down(): void
    {
        // Tidak dibalik otomatis agar pemetaan RAB yang sudah disimpan tetap stabil.
    }

    private function targetSectionName(?string $stageName, string $jobName): string
    {
        $stage = Str::lower((string) $stageName);
        $job = Str::lower($jobName);

        if (Str::contains($stage, ['tahap i', 'pondasi', 'persiapan']) || Str::contains($job, ['pondasi', 'sloof', 'galian', 'urugan', 'bouwplank', 'timbunan', 'bekisting'])) {
            return 'PEKERJAAN PONDASI';
        }

        if (Str::contains($stage, ['tahap ii', 'dinding', 'finishing awal']) || Str::contains($job, ['bata', 'dinding', 'besi', 'begel', 'semen', 'pasir', 'cipping', 'loster', 'plester', 'acian'])) {
            return 'PEKERJAAN KONSTRUKSI DINDING';
        }

        if (Str::contains($stage, ['tahap iii', 'atap', 'plafon']) || Str::contains($job, ['atap', 'spandek', 'reng', 'kanal', 'lisplang', 'gypsum', 'calsiboard', 'calsibord', 'holow', 'plafon'])) {
            return 'PEKERJAAN ATAP DAN PLAFON';
        }

        if (Str::contains($stage, ['tahap iv', 'kusen', 'pintu', 'jendela', 'listrik']) || Str::contains($job, ['kusen', 'pintu', 'jendela', 'kabel', 'saklar', 'lampu', 'listrik'])) {
            return 'PEKERJAAN KUSEN, PINTU & JENDELA';
        }

        return 'PEKERJAAN KERAMIK & SANITARI';
    }

    private function stageFor(string $name, int $perumahanId, int $detailRumahId): TahapanPembangunan
    {
        [$order, $weight] = $this->unitSections[$name];

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
        foreach ($this->unitSections as $name => [$order, $weight]) {
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
            $targetName = $this->targetSectionName($item->stage_name, (string) $item->nama_pekerjaan);

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
