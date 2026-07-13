<?php

namespace App\Services;

use App\Models\DetailPerumahanHpp;
use App\Models\DetailRumahHppItem;
use App\Models\HppRealisasi;
use App\Models\MaterialUsage;
use Illuminate\Support\Facades\DB;

class MaterialHppRealizationService
{
    public function syncFromUsage(MaterialUsage $usage): void
    {
        DB::transaction(function () use ($usage): void {
            $this->removeForUsage($usage);

            $usage->loadMissing([
                'perumahan:id,nama_perusahaan',
                'detailRumah:id,kode_nlok,nomor_rumah,perumahan_id',
                'tahapanPembangunan:id,nama_tahapan',
                'details.barangMaterial:id,nama_barang,harga_hpp',
                'details.siteMaterialStock:id,barang_material_id',
                'details.detailRumahHppItem:id,kelompok_hpp_id,tahapan_pembangunan_id,nama_pekerjaan,jumlah_rab',
            ]);

            foreach ($usage->details as $detail) {
                $barangMaterialId = (int) ($detail->barangMaterial?->id ?? $detail->siteMaterialStock?->barang_material_id ?? 0);
                if (! $barangMaterialId) {
                    continue;
                }

                $matchedItem = $detail->detailRumahHppItem ?: $this->matchHppItem($usage, $barangMaterialId);
                $nominal = (float) $detail->qty * (float) ($detail->barangMaterial?->harga_hpp ?? 0);

                HppRealisasi::query()->create([
                    'target_type' => $usage->detail_rumah_id ? 'App\\Models\\DetailRumah' : 'App\\Models\\Perumahan',
                    'target_id' => $usage->detail_rumah_id ?: $usage->perumahan_id,
                    'perumahan_id' => $usage->perumahan_id,
                    'detail_rumah_id' => $usage->detail_rumah_id,
                    'tahapan_pembangunan_id' => $matchedItem?->tahapan_pembangunan_id ?? $usage->tahapan_pembangunan_id,
                    'kelompok_hpp_id' => $matchedItem?->kelompok_hpp_id,
                    'detail_rumah_hpp_item_id' => $detail->detail_rumah_hpp_item_id,
                    'source_type' => MaterialUsage::class,
                    'source_id' => $usage->id,
                    'sumber_type' => MaterialUsage::class,
                    'sumber_id' => $usage->id,
                    'tanggal' => $usage->tanggal->format('Y-m-d'),
                    'nominal' => $nominal,
                    'keterangan' => $this->noteForUsage($usage, $detail->barangMaterial?->nama_barang ?? 'Material'),
                    'user_id' => auth()->id(),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        });
    }

    public function removeForUsage(MaterialUsage $usage): void
    {
        HppRealisasi::query()
            ->where('source_type', MaterialUsage::class)
            ->where('source_id', $usage->id)
            ->delete();
    }

    protected function matchHppItem(MaterialUsage $usage, int $barangMaterialId): DetailPerumahanHpp|DetailRumahHppItem|null
    {
        if ($usage->detail_rumah_id) {
            return DetailRumahHppItem::query()
                ->with('detailRumahHpp')
                ->whereHas('detailRumahHpp', fn ($query) => $query->where('detail_rumah_id', $usage->detail_rumah_id))
                ->where('tahapan_pembangunan_id', $usage->tahapan_pembangunan_id)
                ->where('barang_material_id', $barangMaterialId)
                ->first();
        }

        return DetailPerumahanHpp::query()
            ->with('perumahanHpp')
            ->whereHas('perumahanHpp', fn ($query) => $query->where('perumahan_id', $usage->perumahan_id))
            ->where('tahapan_pembangunan_id', $usage->tahapan_pembangunan_id)
            ->where('barang_material_id', $barangMaterialId)
            ->first();
    }

    protected function noteForUsage(MaterialUsage $usage, string $materialName): string
    {
        $location = $usage->detailRumah
            ? trim(($usage->detailRumah->kode_nlok ?? '').' '.($usage->detailRumah->nomor_rumah ?? ''))
            : ($usage->perumahan?->nama_perusahaan ?? 'Perumahan');

        return trim(sprintf(
            'Pemakaian material %s %s pada %s%s',
            $usage->kode_pemakaian,
            $materialName,
            $location,
            $usage->tahapanPembangunan?->nama_tahapan ? ' - '.$usage->tahapanPembangunan->nama_tahapan : '',
        ));
    }
}
