<?php

namespace App\Services;

use App\Models\DetailPerumahanHpp;
use App\Models\DetailRumahHppItem;
use App\Models\TahapanPembangunan;
use Illuminate\Support\Collection;

class MaterialRequestTemplateService
{
    public function build(int|string $perumahanId, int|string|null $detailRumahId, int|string|null $tahapanPembangunanId): array
    {
        $scope = filled($detailRumahId) ? 'unit' : 'kawasan';
        $rows = $scope === 'unit'
            ? $this->unitItems($perumahanId, $detailRumahId, $tahapanPembangunanId)
            : $this->kawasanItems($perumahanId, $tahapanPembangunanId);

        $totalRab = (float) $rows->sum('jumlah_rab');
        $stageBudget = $this->stageBudget($scope, $perumahanId, $detailRumahId, $tahapanPembangunanId);

        return [
            'scope' => $scope,
            'perumahan_id' => (string) $perumahanId,
            'detail_rumah_id' => (string) ($detailRumahId ?? ''),
            'tahapan_pembangunan_id' => (string) ($tahapanPembangunanId ?? ''),
            'tahapan' => $this->tahapanLabel($tahapanPembangunanId),
            'stage_budget' => $stageBudget,
            'total_rab' => $totalRab,
            'estimated_progress' => $stageBudget > 0 ? min(100, round(($totalRab / $stageBudget) * 100, 2)) : 0,
            'items' => $rows->values()->all(),
        ];
    }

    private function unitItems(int|string $perumahanId, int|string|null $detailRumahId, int|string|null $tahapanPembangunanId): Collection
    {
        return DetailRumahHppItem::query()
            ->with([
                'tahapanPembangunan:id,nama_tahapan,bobot_persen',
                'barangMaterial:id,nama_barang,satuan,harga_hpp',
                'detailRumahHpp.detailRumah:id,perumahan_id',
            ])
            ->when(filled($detailRumahId), function ($query) use ($detailRumahId) {
                $query->whereHas('detailRumahHpp', fn ($subQuery) => $subQuery->where('detail_rumah_id', $detailRumahId));
            })
            ->when(filled($tahapanPembangunanId), fn ($query) => $query->where('tahapan_pembangunan_id', $tahapanPembangunanId))
            ->get()
            ->filter(fn (DetailRumahHppItem $item) => (int) $item->detailRumahHpp?->detail_rumah_id !== 0 || filled($detailRumahId))
            ->map(fn (DetailRumahHppItem $item) => $this->formatItem(
                scope: 'unit',
                perumahanId: $perumahanId,
                detailRumahId: $detailRumahId,
                tahapanPembangunanId: $item->tahapan_pembangunan_id,
                itemName: $item->nama_pekerjaan ?: $item->barangMaterial?->nama_barang ?: '-',
                tahapan: $item->tahapanPembangunan,
                barang: $item->barangMaterial,
                volume: $item->volume,
                satuan: $item->satuan,
                hargaSatuan: $item->harga_satuan,
                jumlahRab: $item->jumlah_rab,
                urutan: $item->urutan,
            ));
    }

    private function kawasanItems(int|string $perumahanId, int|string|null $tahapanPembangunanId): Collection
    {
        return DetailPerumahanHpp::query()
            ->with([
                'tahapanPembangunan:id,nama_tahapan,bobot_persen',
                'barangMaterial:id,nama_barang,satuan,harga_hpp',
                'perumahanHpp.perumahan:id,nama_perusahaan',
            ])
            ->whereHas('perumahanHpp', fn ($query) => $query->where('perumahan_id', $perumahanId))
            ->when(filled($tahapanPembangunanId), fn ($query) => $query->where('tahapan_pembangunan_id', $tahapanPembangunanId))
            ->get()
            ->map(fn (DetailPerumahanHpp $item) => $this->formatItem(
                scope: 'kawasan',
                perumahanId: $perumahanId,
                detailRumahId: null,
                tahapanPembangunanId: $item->tahapan_pembangunan_id,
                itemName: $item->nama_pekerjaan ?: $item->barangMaterial?->nama_barang ?: '-',
                tahapan: $item->tahapanPembangunan,
                barang: $item->barangMaterial,
                volume: $item->volume,
                satuan: $item->satuan,
                hargaSatuan: $item->harga_satuan,
                jumlahRab: $item->jumlah_rab,
                urutan: $item->urutan,
            ));
    }

    private function formatItem(
        string $scope,
        int|string $perumahanId,
        int|string|null $detailRumahId,
        int|string|null $tahapanPembangunanId,
        string $itemName,
        mixed $tahapan,
        mixed $barang,
        float $volume,
        ?string $satuan,
        float $hargaSatuan,
        float $jumlahRab,
        int $urutan,
    ): array {
        return [
            'scope' => $scope,
            'perumahan_id' => (string) $perumahanId,
            'detail_rumah_id' => (string) ($detailRumahId ?? ''),
            'tahapan_pembangunan_id' => (string) ($tahapanPembangunanId ?? ''),
            'tahapan' => $this->stripRomanPrefix($tahapan?->nama_tahapan ?? '-'),
            'barang_material_id' => (string) ($barang?->id ?? ''),
            'barang_label' => $barang?->nama_barang ?? $itemName,
            'volume' => $volume,
            'satuan' => $satuan ?? $barang?->satuan ?? '',
            'harga_satuan' => $hargaSatuan,
            'jumlah_rab' => $jumlahRab,
            'urutan' => $urutan,
        ];
    }

    private function stageBudget(string $scope, int|string $perumahanId, int|string|null $detailRumahId, int|string|null $tahapanPembangunanId): float
    {
        $query = $scope === 'unit'
            ? DetailRumahHppItem::query()->whereHas('detailRumahHpp', fn ($subQuery) => $subQuery->where('detail_rumah_id', $detailRumahId))
            : DetailPerumahanHpp::query()->whereHas('perumahanHpp', fn ($subQuery) => $subQuery->where('perumahan_id', $perumahanId));

        if (filled($tahapanPembangunanId)) {
            $query->where('tahapan_pembangunan_id', $tahapanPembangunanId);
        }

        return (float) $query->sum('jumlah_rab');
    }

    private function tahapanLabel(int|string|null $tahapanPembangunanId): string
    {
        if (blank($tahapanPembangunanId)) {
            return '-';
        }

        return TahapanPembangunan::query()
            ->find($tahapanPembangunanId)
            ?->nama_tahapan ?? '-';
    }

    private function stripRomanPrefix(?string $value): string
    {
        return trim((string) preg_replace('/^\s*[IVXLCDM]+\s*[\.\-]?\s+/i', '', (string) $value));
    }
}
