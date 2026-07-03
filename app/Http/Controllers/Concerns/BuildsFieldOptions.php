<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\TahapanPembangunan;

trait BuildsFieldOptions
{
    protected function fieldOptions(): array
    {
        return [
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'detailRumahs' => DetailRumah::query()->with('perumahan:id,nama_perusahaan')->orderBy('kode_nlok')->orderBy('nomor_rumah')
                ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->perumahan?->nama_perusahaan} - {$row->kode_nlok} {$row->nomor_rumah}",
                    'perumahan_id' => (string) $row->perumahan_id,
                ])->values(),
            'tahapanPembangunans' => TahapanPembangunan::query()->where('status', 'aktif')->where('konteks', 'unit')->orderBy('urutan')->get(['id', 'nama_tahapan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_tahapan])->values(),
            'tahapanPembangunansUnit' => TahapanPembangunan::query()->where('status', 'aktif')->where('konteks', 'unit')->orderBy('urutan')->get(['id', 'nama_tahapan', 'bobot_persen'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_tahapan.' ('.$row->bobot_persen.'%)', 'bobot_persen' => $row->bobot_persen])->values(),
            'tahapanPembangunansKawasan' => TahapanPembangunan::query()->where('status', 'aktif')->where('konteks', 'kawasan')->orderBy('urutan')->get(['id', 'nama_tahapan', 'bobot_persen'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_tahapan.' ('.$row->bobot_persen.'%)', 'bobot_persen' => $row->bobot_persen])->values(),
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
