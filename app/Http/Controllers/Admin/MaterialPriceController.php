<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\BarangMaterial;
use App\Models\MaterialPriceHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPriceController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/Logistik/HargaMaterial', [
            'title' => 'Harga Dasar Material',
            'baseUrl' => route('admin.harga-material.index', absolute: false),
            'rows' => MaterialPriceHistory::query()
                ->with('barangMaterial:id,kode_barang,nama_barang,satuan,harga_hpp')
                ->when($search !== '', fn (Builder $query) => $query->where('supplier', 'like', "%{$search}%")
                    ->orWhereHas('barangMaterial', fn (Builder $query) => $query->where('nama_barang', 'like', "%{$search}%")->orWhere('kode_barang', 'like', "%{$search}%")))
                ->latest('tanggal_berlaku')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialPriceHistory $row) => [
                    'id' => $row->id,
                    'barang_material_id' => (string) $row->barang_material_id,
                    'material' => $row->barangMaterial?->nama_barang,
                    'tanggal_berlaku' => optional($row->tanggal_berlaku)->format('Y-m-d'),
                    'harga_satuan' => $row->harga_satuan,
                    'supplier' => $row->supplier,
                    'keterangan' => $row->keterangan,
                    'status' => $row->status,
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => ['search' => $search],
            'options' => [
                'materials' => BarangMaterial::query()->where('status', 'aktif')->orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_hpp'])->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->kode_barang} - {$row->nama_barang}",
                    'harga_hpp' => $row->harga_hpp,
                ])->values(),
                'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->payload($request);

        DB::transaction(function () use ($payload) {
            MaterialPriceHistory::query()->create([
                ...$payload,
                'created_by' => auth()->id(),
            ]);

            if (($payload['status'] ?? 'aktif') === 'aktif') {
                BarangMaterial::query()->whereKey($payload['barang_material_id'])->update(['harga_hpp' => $payload['harga_satuan']]);
            }
        });

        return back()->with('success', 'Harga material berhasil ditambahkan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = MaterialPriceHistory::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Harga material berhasil dihapus.');
    }

    protected function payload(Request $request): array
    {
        return $request->validate([
            'barang_material_id' => ['required', 'exists:barang_materials,id'],
            'tanggal_berlaku' => ['required', 'date'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }

    protected function modelClass(): string
    {
        return MaterialPriceHistory::class;
    }
}
