<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Models\SiteMaterialStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteMaterialStockController extends Controller
{
    use BuildsFieldOptions;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');

        return Inertia::render('Admin/SiteMaterialStock/Index', [
            'title' => 'Sisa Material Lokasi',
            'baseUrl' => route('admin.site-material-stock.index', absolute: false),
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'options' => $this->fieldOptions(),
            'rows' => SiteMaterialStock::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'kelompokHpp:id,nama_hpp',
                    'barangMaterial:id,nama_barang,satuan',
                ])
                ->when($search !== '', function (Builder $query) use ($search) {
                    $query->whereHas('barangMaterial', fn (Builder $query) => $query->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"))
                        ->orWhereHas('detailRumah', fn (Builder $query) => $query
                            ->where('kode_nlok', 'like', "%{$search}%")
                            ->orWhere('nomor_rumah', 'like', "%{$search}%"));
                })
                ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
                ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
                ->orderByDesc('qty_available')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (SiteMaterialStock $row) => [
                    'id' => $row->id,
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah
                        ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah)
                        : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'kelompok_hpp' => $row->kelompokHpp?->nama_hpp ?? '-',
                    'material' => $row->barangMaterial?->nama_barang ?? '-',
                    'satuan' => $row->barangMaterial?->satuan ?? '-',
                    'diterima' => $row->qty_received,
                    'dipakai' => $row->qty_used,
                    'menunggu_pengembalian' => $row->qty_reserved_return,
                    'dikembalikan' => $row->qty_returned,
                    'sisa' => $row->qty_available,
                ]),
            'summary' => [
                'jenis_material' => SiteMaterialStock::query()->where('qty_available', '>', 0)->count(),
                'total_diterima' => SiteMaterialStock::query()->sum('qty_received'),
                'total_dipakai' => SiteMaterialStock::query()->sum('qty_used'),
                'total_sisa' => SiteMaterialStock::query()->sum('qty_available'),
            ],
        ]);
    }
}
