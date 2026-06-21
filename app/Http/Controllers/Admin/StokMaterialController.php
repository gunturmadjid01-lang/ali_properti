<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Models\StokMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StokMaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));

        return Inertia::render('Admin/Logistik/StokMaterial', [
            'title' => 'Stok Material',
            'baseUrl' => route('admin.stok-material.index', absolute: false),
            'rows' => StokMaterial::query()
                ->with(['barangMaterial:id,kode_barang,nama_barang,satuan,stok_minimum', 'gudang:id,nama_gudang'])
                ->when($gudangId !== '', fn (Builder $query) => $query->where('gudang_id', $gudangId))
                ->when($search !== '', fn (Builder $query) => $query->whereHas('barangMaterial', fn (Builder $query) => $query->where('nama_barang', 'like', "%{$search}%")->orWhere('kode_barang', 'like', "%{$search}%")))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (StokMaterial $row) => [
                    'id' => $row->id,
                    'gudang' => $row->gudang?->nama_gudang ?? 'Gudang Umum',
                    'kode_barang' => $row->barangMaterial?->kode_barang,
                    'nama_barang' => $row->barangMaterial?->nama_barang,
                    'qty' => $row->qty,
                    'satuan' => $row->barangMaterial?->satuan,
                    'stok_minimum' => $row->barangMaterial?->stok_minimum,
                    'status_stok' => $row->qty <= (float) ($row->barangMaterial?->stok_minimum ?? 0) ? 'Minimum' : 'Aman',
                ]),
            'filters' => ['search' => $search, 'gudang_id' => $gudangId],
            'options' => [
                'gudangs' => [['value' => '', 'label' => 'Semua Gudang'], ...Gudang::query()->where('status', 'aktif')->orderBy('nama_gudang')->get(['id', 'nama_gudang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_gudang])->all()],
            ],
        ]);
    }
}
