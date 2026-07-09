<?php

namespace App\Services;

use App\Models\DetailRumah;
use App\Models\DetailRumahHpp;
use App\Models\KelompokHpp;
use App\Models\Perumahan;
use App\Models\PerumahanHpp;
use App\Models\TahapanPembangunan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HppTemplateService
{
    private const UNIT_HPP_STAGES = [
        'PEK. PERSIAPAN & PONDASI',
        'PEK. DINDING',
        'PEK. FINISHING AWAL',
        'PEK. PIPA AIR BERSIH & KOTOR',
        'PEK. KERAMIK, SANITASI, PINTU & KAMAR MANDI',
        'PEK. PAGAR & CAR PORT',
        'PEK. TAMAN, PROFIL DAN PENGECATAN',
        'PEK. PEMASANGAN ATAP',
        'PEK. PEMASANGAN PLAFON',
        'PEK. INSTALASI LISTRIK',
    ];

    public function initializePerumahan(Perumahan $perumahan): void
    {
        $userId = auth()->id() ?? \App\Models\User::query()->value('id');

        if (! $userId) {
            return;
        }

        DB::transaction(function () use ($perumahan, $userId): void {
            $sourceHpp = PerumahanHpp::query()
                ->with(['detailPerumahanHpps.tahapanPembangunan'])
                ->withCount('detailPerumahanHpps')
                ->where('perumahan_id', '!=', $perumahan->id)
                ->orderByDesc('detail_perumahan_hpps_count')
                ->first();

            $templateStages = $this->systemTemplateStages('kawasan');
            $sourceStages = $templateStages->isNotEmpty()
                ? $templateStages
                : ($sourceHpp
                    ? TahapanPembangunan::query()
                    ->where('perumahan_id', $sourceHpp->perumahan_id)
                    ->whereNull('detail_rumah_id')
                    ->where('konteks', 'kawasan')
                    ->orderBy('urutan')
                    ->get()
                    : $this->masterStages('kawasan'));

            $stageMap = $this->cloneStages($sourceStages, $perumahan->id);
            $hpp = PerumahanHpp::query()->firstOrCreate(
                ['perumahan_id' => $perumahan->id],
                ['user_id' => $userId, 'tanggal_dibuat' => now()->toDateString()],
            );

            $sourceItems = $templateStages->isNotEmpty()
                ? $this->systemTemplateItems($templateStages)
                : collect($sourceHpp?->detailPerumahanHpps ?? []);

            foreach ($sourceItems as $item) {
                if (str_starts_with(strtolower((string) $item->nama_pekerjaan), 'bangunan rumah type')) {
                    continue;
                }

                $stageId = $stageMap->get($item->tahapan_pembangunan_id);

                if (! $stageId) {
                    continue;
                }

                $hpp->detailPerumahanHpps()->firstOrCreate(
                    [
                        'tahapan_pembangunan_id' => $stageId,
                        'nama_pekerjaan' => $item->nama_pekerjaan,
                    ],
                    [
                        'kelompok_hpp_id' => $item->kelompok_hpp_id,
                        'volume' => 0,
                        'satuan' => $item->satuan,
                        'harga_satuan' => 0,
                        'jumlah_rab' => 0,
                        'urutan' => $item->urutan,
                    ],
                );
            }
        });
    }

    public function initializeUnit(DetailRumah $rumah): void
    {
        $userId = auth()->id() ?? \App\Models\User::query()->value('id');

        if (! $userId) {
            return;
        }

        DB::transaction(function () use ($rumah, $userId): void {
            $sourceHpp = DetailRumahHpp::query()
                ->with(['items.tahapanPembangunan', 'detailRumah'])
                ->withCount('items')
                ->where('detail_rumah_id', '!=', $rumah->id)
                ->orderByDesc('items_count')
                ->first();

            $templateStages = $this->systemTemplateStages('unit');
            $sourceStages = $templateStages->isNotEmpty()
                ? $templateStages
                : ($sourceHpp
                    ? TahapanPembangunan::query()
                    ->where('detail_rumah_id', $sourceHpp->detail_rumah_id)
                    ->where('konteks', 'unit')
                    ->whereIn('nama_tahapan', self::UNIT_HPP_STAGES)
                    ->orderBy('urutan')
                    ->get()
                    : $this->masterStages('unit'));

            $stageMap = $this->cloneStages($sourceStages, $rumah->perumahan_id, $rumah->id);
            $hpp = DetailRumahHpp::query()->firstOrCreate(
                ['detail_rumah_id' => $rumah->id],
                ['user_id' => $userId, 'tanggal_dibuat' => now()->toDateString()],
            );

            $sourceItems = $templateStages->isNotEmpty()
                ? $this->systemTemplateItems($templateStages)
                : collect($sourceHpp?->items ?? []);

            foreach ($sourceItems as $item) {
                $stageId = $stageMap->get($item->tahapan_pembangunan_id);

                if (! $stageId) {
                    continue;
                }

                $hpp->items()->firstOrCreate(
                    [
                        'tahapan_pembangunan_id' => $stageId,
                        'nama_pekerjaan' => $item->nama_pekerjaan,
                    ],
                    [
                        'kelompok_hpp_id' => $item->kelompok_hpp_id,
                        'volume' => 0,
                        'satuan' => $item->satuan,
                        'harga_satuan' => 0,
                        'jumlah_rab' => 0,
                        'urutan' => $item->urutan,
                    ],
                );
            }

            $this->syncBuildingTypeSummary($rumah->perumahan_id);
        });
    }

    public function syncBuildingTypeSummary(int $perumahanId): void
    {
        $perumahan = Perumahan::query()->find($perumahanId);

        if (! $perumahan) {
            return;
        }

        $this->initializePerumahan($perumahan);
        $hpp = PerumahanHpp::query()->where('perumahan_id', $perumahanId)->first();
        $stage = TahapanPembangunan::query()
            ->where('perumahan_id', $perumahanId)
            ->whereNull('detail_rumah_id')
            ->where('konteks', 'kawasan')
            ->where('nama_tahapan', 'IV RAB BANGUNAN')
            ->first();
        $kelompokId = KelompokHpp::query()->where('nama_hpp', 'Bangunan Rumah')->value('id')
            ?? KelompokHpp::query()->orderBy('id')->value('id');

        if (! $hpp || ! $stage || ! $kelompokId) {
            return;
        }

        $types = DetailRumah::query()
            ->where('perumahan_id', $perumahanId)
            ->whereNotNull('tipe_rumah')
            ->where('tipe_rumah', '!=', '')
            ->selectRaw('tipe_rumah, COUNT(*) as jumlah')
            ->groupBy('tipe_rumah')
            ->get();
        $unitRabByType = DetailRumah::query()
            ->where('detail_rumahs.perumahan_id', $perumahanId)
            ->whereNotNull('detail_rumahs.tipe_rumah')
            ->where('detail_rumahs.tipe_rumah', '!=', '')
            ->join('detail_rumah_hpps', function ($join): void {
                $join->on('detail_rumah_hpps.detail_rumah_id', '=', 'detail_rumahs.id')
                    ->whereNull('detail_rumah_hpps.deleted_at');
            })
            ->join('detail_rumah_hpp_items', function ($join): void {
                $join->on('detail_rumah_hpp_items.detail_rumah_hpp_id', '=', 'detail_rumah_hpps.id')
                    ->whereNull('detail_rumah_hpp_items.deleted_at');
            })
            ->selectRaw('detail_rumahs.tipe_rumah, detail_rumahs.id as rumah_id, SUM(detail_rumah_hpp_items.jumlah_rab) as total_rab')
            ->groupBy('detail_rumahs.tipe_rumah', 'detail_rumahs.id')
            ->get()
            ->groupBy(fn ($row) => trim((string) $row->tipe_rumah))
            ->map(function (Collection $rows): float {
                $totals = $rows
                    ->map(fn ($row) => (float) $row->total_rab)
                    ->filter(fn (float $total) => $total > 0)
                    ->values();

                return $totals->isNotEmpty() ? (float) $totals->avg() : 0.0;
            });

        $activeNames = $types->map(fn ($type) => 'Bangunan Rumah Type '.trim($type->tipe_rumah));

        $hpp->detailPerumahanHpps()
            ->where('tahapan_pembangunan_id', $stage->id)
            ->where('nama_pekerjaan', 'like', 'Bangunan Rumah Type %')
            ->whereNotIn('nama_pekerjaan', $activeNames)
            ->get()
            ->each
            ->delete();

        foreach ($types as $index => $type) {
            $typeName = trim($type->tipe_rumah);
            $name = 'Bangunan Rumah Type '.$typeName;
            $item = $hpp->detailPerumahanHpps()->firstOrNew([
                'tahapan_pembangunan_id' => $stage->id,
                'nama_pekerjaan' => $name,
            ]);
            $unitRab = (float) ($unitRabByType->get($typeName) ?? 0);
            $price = $unitRab > 0 ? $unitRab : (float) ($item->harga_satuan ?? 0);
            $item->fill([
                'kelompok_hpp_id' => $item->kelompok_hpp_id ?: $kelompokId,
                'volume' => (int) $type->jumlah,
                'satuan' => 'Unit',
                'harga_satuan' => $price,
                'jumlah_rab' => (int) $type->jumlah * $price,
                'urutan' => $index + 1,
            ])->save();
        }
    }

    public function refreshSystemTemplates(): void
    {
        if (! Schema::hasTable('hpp_template_stages')) {
            return;
        }

        DB::transaction(function (): void {
            $this->captureKawasanTemplate();
            $this->captureUnitTemplate();
        });
    }

    private function masterStages(string $context): Collection
    {
        return TahapanPembangunan::query()
            ->where('konteks', $context)
            ->when($context === 'unit', fn ($query) => $query->whereIn('nama_tahapan', self::UNIT_HPP_STAGES))
            ->whereNull('perumahan_id')
            ->whereNull('detail_rumah_id')
            ->where('status', 'aktif')
            ->orderBy('urutan')
            ->get();
    }

    private function systemTemplateStages(string $context): Collection
    {
        if (! Schema::hasTable('hpp_template_stages')) {
            return collect();
        }

        return DB::table('hpp_template_stages')
            ->where('konteks', $context)
            ->when($context === 'unit', fn ($query) => $query->whereIn('nama_tahapan', self::UNIT_HPP_STAGES))
            ->orderBy('urutan')
            ->get();
    }

    private function systemTemplateItems(Collection $stages): Collection
    {
        return DB::table('hpp_template_items')
            ->whereIn('hpp_template_stage_id', $stages->pluck('id'))
            ->orderBy('urutan')
            ->get()
            ->map(function ($item) {
                $item->tahapan_pembangunan_id = $item->hpp_template_stage_id;

                return $item;
            });
    }

    private function cloneStages(
        Collection $sourceStages,
        int $perumahanId,
        ?int $detailRumahId = null,
    ): Collection {
        return $sourceStages->mapWithKeys(function ($source) use ($perumahanId, $detailRumahId) {
            $stage = TahapanPembangunan::query()->firstOrCreate(
                [
                    'nama_tahapan' => $source->nama_tahapan,
                    'konteks' => $detailRumahId ? 'unit' : 'kawasan',
                    'perumahan_id' => $perumahanId,
                    'detail_rumah_id' => $detailRumahId,
                ],
                [
                    'bobot_persen' => $source->bobot_persen,
                    'urutan' => $source->urutan,
                    'status' => 'aktif',
                ],
            );

            return [$source->id => $stage->id];
        });
    }

    private function captureKawasanTemplate(): void
    {
        $source = PerumahanHpp::query()
            ->withCount('detailPerumahanHpps')
            ->orderByDesc('detail_perumahan_hpps_count')
            ->first();

        if (! $source || $source->detail_perumahan_hpps_count === 0) {
            return;
        }

        $stages = TahapanPembangunan::query()
            ->where('perumahan_id', $source->perumahan_id)
            ->whereNull('detail_rumah_id')
            ->where('konteks', 'kawasan')
            ->orderBy('urutan')
            ->get();
        $this->appendMissingMasterStages($stages, 'kawasan');
        $items = $source->detailPerumahanHpps()
            ->where('nama_pekerjaan', 'not like', 'Bangunan Rumah Type %')
            ->get();

        $this->replaceSystemTemplate('kawasan', $stages, $items);
    }

    private function captureUnitTemplate(): void
    {
        $source = DetailRumahHpp::query()
            ->withCount('items')
            ->orderByDesc('items_count')
            ->first();

        if (! $source || $source->items_count === 0) {
            return;
        }

        $stages = TahapanPembangunan::query()
            ->where('detail_rumah_id', $source->detail_rumah_id)
            ->where('konteks', 'unit')
            ->where('status', 'aktif')
            ->whereIn('nama_tahapan', self::UNIT_HPP_STAGES)
            ->whereIn(
                'id',
                $source->items()
                    ->whereNotNull('tahapan_pembangunan_id')
                    ->pluck('tahapan_pembangunan_id')
                    ->unique(),
            )
            ->orderBy('urutan')
            ->get();

        $this->replaceSystemTemplate('unit', $stages, $source->items()->get());
    }

    private function replaceSystemTemplate(string $context, Collection $stages, Collection $items): void
    {
        $oldStageIds = DB::table('hpp_template_stages')->where('konteks', $context)->pluck('id');
        DB::table('hpp_template_items')->whereIn('hpp_template_stage_id', $oldStageIds)->delete();
        DB::table('hpp_template_stages')->where('konteks', $context)->delete();

        $stageMap = collect();
        foreach ($stages as $stage) {
            $templateStageId = DB::table('hpp_template_stages')->insertGetId([
                'konteks' => $context,
                'nama_tahapan' => $stage->nama_tahapan,
                'bobot_persen' => $stage->bobot_persen,
                'urutan' => $stage->urutan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stageMap->put($stage->id, $templateStageId);
        }

        foreach ($items as $item) {
            $templateStageId = $stageMap->get($item->tahapan_pembangunan_id);

            if (! $templateStageId || ! $item->nama_pekerjaan) {
                continue;
            }

            DB::table('hpp_template_items')->insert([
                'hpp_template_stage_id' => $templateStageId,
                'kelompok_hpp_id' => $item->kelompok_hpp_id,
                'nama_pekerjaan' => $item->nama_pekerjaan,
                'volume' => $item->volume,
                'satuan' => $item->satuan,
                'urutan' => $item->urutan,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function appendMissingMasterStages(Collection $stages, string $context): void
    {
        foreach ($this->masterStages($context) as $masterStage) {
            if (! $stages->contains('urutan', $masterStage->urutan)) {
                $stages->push($masterStage);
            }
        }
    }
}
