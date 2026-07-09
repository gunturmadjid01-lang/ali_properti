<?php

namespace App\Services;

use App\Models\TahapanPembangunan;
use Illuminate\Support\Collection;

class TahapanOptionService
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

    public function forContext(string $context): Collection
    {
        return TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', $context)
            ->when($context === 'unit', fn ($query) => $query->whereIn('nama_tahapan', self::UNIT_HPP_STAGES))
            ->when(
                $context === 'unit',
                fn ($query) => $query
                    ->whereNotNull('detail_rumah_id')
                    ->whereHas('detailRumah'),
                fn ($query) => $query
                    ->whereNull('detail_rumah_id')
                    ->whereNotNull('perumahan_id')
                    ->whereHas('perumahan'),
            )
            ->orderBy('urutan')
            ->orderBy('nama_tahapan')
            ->get([
                'id',
                'nama_tahapan',
                'bobot_persen',
                'perumahan_id',
                'detail_rumah_id',
            ])
            ->map(fn (TahapanPembangunan $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_tahapan.($row->bobot_persen > 0 ? ' ('.$row->bobot_persen.'%)' : ''),
                'nama_tahapan' => $row->nama_tahapan,
                'bobot_persen' => (float) $row->bobot_persen,
                'perumahan_id' => (string) ($row->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
            ])
            ->values();
    }
}
