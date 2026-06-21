<?php

namespace App\Http\Controllers\Admin\Logistik;

use App\Http\Controllers\Controller;
use App\Models\HppRealisasi;
use App\Models\TransaksiLogistik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransaksiLogistikController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/Logistik/Index', [
            'title' => 'Riwayat Mutasi Gudang',
            'baseUrl' => route('admin.logistik.index', absolute: false),
            'filters' => ['search' => $search],
            'rows' => TransaksiLogistik::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'kelompokHpp:id,nama_hpp',
                    'details.barangMaterial:id,nama_barang',
                ])
                ->when($search !== '', function (Builder $query) use ($search) {
                    $query->where('kode_transaksi', 'like', "%{$search}%")
                        ->orWhere('jenis', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%");
                })
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (TransaksiLogistik $row) => [
                    'id' => $row->id,
                    'kode_transaksi' => $row->kode_transaksi,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'jenis' => $row->jenis,
                    'gudang' => $row->gudang?->nama_gudang ?? 'Gudang Umum',
                    'perumahan' => $row->perumahan?->nama_perusahaan,
                    'detail_rumah' => $row->detailRumah ? "{$row->detailRumah->kode_nlok} - {$row->detailRumah->nomor_rumah}" : '-',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'kelompok_hpp' => $row->kelompokHpp?->nama_hpp,
                    'total_nominal' => $row->total_nominal,
                    'keterangan' => $row->keterangan,
                    'sumber' => $this->sourceLabel($row->source_type),
                    'items_text' => $row->details
                        ->map(fn ($detail) => "{$detail->barangMaterial?->nama_barang} {$detail->qty} {$detail->satuan}")
                        ->join(', '),
                ]),
            'summary' => [
                'total_masuk' => TransaksiLogistik::query()->where('jenis', TransaksiLogistik::JENIS_MASUK)->sum('total_nominal'),
                'total_keluar' => TransaksiLogistik::query()->where('jenis', TransaksiLogistik::JENIS_KELUAR)->sum('total_nominal'),
                'total_realisasi_perumahan' => HppRealisasi::query()->whereNull('detail_rumah_id')->sum('nominal'),
                'total_realisasi_rumah' => HppRealisasi::query()->whereNotNull('detail_rumah_id')->sum('nominal'),
            ],
        ]);
    }

    private function sourceLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            \App\Models\MaterialRequest::class => 'Permintaan material disetujui',
            \App\Models\MaterialReturn::class => 'Pengembalian dari lokasi',
            \App\Models\MaterialPurchase::class => 'Penerimaan pembelian',
            null => 'Transaksi lama/manual',
            default => class_basename($sourceType),
        };
    }
}
