<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Services\TahapanOptionService;

trait BuildsFieldOptions
{
    protected function fieldOptions(): array
    {
        return [
            'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'detailRumahs' => DetailRumah::query()->finalized()->with('perumahan:id,nama_perusahaan')->orderBy('kode_nlok')->orderBy('nomor_rumah')
                ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->perumahan?->nama_perusahaan} - {$row->kode_nlok} {$row->nomor_rumah}",
                    'perumahan_id' => (string) $row->perumahan_id,
                ])->values(),
            'tahapanPembangunans' => app(TahapanOptionService::class)->forContext('unit'),
            'tahapanPembangunansUnit' => app(TahapanOptionService::class)->forContext('unit'),
            'tahapanPembangunansKawasan' => app(TahapanOptionService::class)->forContext('kawasan'),
        ];
    }

    protected function authorizeFieldUser(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['pengawas', 'manajer_pimpro', 'owner', 'super_admin']), 403, 'Akses hanya untuk tim pengawasan proyek.');
    }

    protected function canApproveFieldData(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']);
    }
}
