<?php

namespace App\Services;

use App\Models\DetailRumah;
use App\Models\MaterialUsage;
use App\Models\Perumahan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MaterialUsageReportService
{
    public function build(?Perumahan $perumahan, ?DetailRumah $unit, Carbon $start, Carbon $end): array
    {
        $usages = MaterialUsage::query()
            ->with([
                'perumahan:id,nama_perusahaan',
                'detailRumah:id,perumahan_id,kode_nlok,nomor_rumah,tipe_rumah',
                'tahapanPembangunan:id,nama_tahapan',
                'progressPembangunan:id,nama_progress,persentase,tanggal,approval_status',
                'details.barangMaterial:id,kode_barang,nama_barang,jenis_material,merk_material,satuan,harga_hpp',
                'details.detailRumahHppItem:id,nama_pekerjaan',
            ])
            ->whereNotNull('progress_pembangunan_id')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->when($perumahan, fn ($query) => $query->where('perumahan_id', $perumahan->id))
            ->when($unit, fn ($query) => $query->where('detail_rumah_id', $unit->id))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $details = $usages->flatMap(function (MaterialUsage $usage): Collection {
            return $usage->details->map(function ($detail) use ($usage): array {
                $material = $detail->barangMaterial;
                $price = (float) ($detail->unit_cost_snapshot ?? 0);
                $quantity = (float) $detail->qty;

                return [
                    'id' => $detail->id,
                    'usage_id' => $usage->id,
                    'date' => optional($usage->tanggal)->format('Y-m-d'),
                    'code' => $usage->kode_pemakaian,
                    'project' => $usage->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $usage->detailRumah
                        ? 'Blok '.$usage->detailRumah->kode_nlok.' No. '.$usage->detailRumah->nomor_rumah
                        : 'Kawasan',
                    'unit_type' => $usage->detailRumah?->tipe_rumah,
                    'stage' => $usage->tahapanPembangunan?->nama_tahapan ?? '-',
                    'progress' => $usage->progressPembangunan?->nama_progress ?: 'Progress pembangunan',
                    'progress_percentage' => (float) ($usage->progressPembangunan?->persentase ?? 0),
                    'work_item' => $detail->detailRumahHppItem?->nama_pekerjaan,
                    'material_id' => $material?->id,
                    'material_code' => $material?->kode_barang ?? '-',
                    'material' => $material?->nama_barang ?? 'Material tidak ditemukan',
                    'material_type' => $material?->jenis_material,
                    'brand' => $material?->merk_material,
                    'quantity' => $quantity,
                    'unit_name' => $detail->satuan ?: ($material?->satuan ?? '-'),
                    'unit_price' => $price,
                    'amount' => (float) ($detail->subtotal_snapshot ?: $quantity * $price),
                    'note' => $usage->keterangan,
                ];
            });
        })->values();

        $summary = $details
            ->groupBy(fn (array $row) => ($row['material_id'] ?? $row['material']).'|'.$row['unit_name'])
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'material_code' => $first['material_code'],
                    'material' => $first['material'],
                    'material_type' => $first['material_type'],
                    'brand' => $first['brand'],
                    'quantity' => (float) $rows->sum('quantity'),
                    'unit_name' => $first['unit_name'],
                    'unit_price' => (float) $first['unit_price'],
                    'amount' => (float) $rows->sum('amount'),
                    'transaction_count' => $rows->pluck('usage_id')->unique()->count(),
                ];
            })
            ->sortBy('material')
            ->values();

        return [
            'scope' => [
                'project' => $perumahan?->nama_perusahaan ?? 'Semua Perumahan',
                'unit' => $unit ? 'Blok '.$unit->kode_nlok.' No. '.$unit->nomor_rumah : 'Semua Unit',
                'unit_type' => $unit?->tipe_rumah,
            ],
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->format('d/m/Y')
                    : $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
            ],
            'totals' => [
                'transactions' => $usages->count(),
                'item_lines' => $details->count(),
                'materials' => $details->pluck('material_id')->filter()->unique()->count(),
                'amount' => (float) $details->sum('amount'),
            ],
            'summary' => $summary,
            'details' => $details,
        ];
    }
}
