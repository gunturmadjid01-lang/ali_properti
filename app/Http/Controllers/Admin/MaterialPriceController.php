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
        $tanggalBerlaku = $request->query('tanggal_berlaku') ?: now()->toDateString();
        $materials = BarangMaterial::query()
            ->where('status', 'aktif')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('kode_barang', 'like', "%{$search}%")
                    ->orWhere('nama_barang', 'like', "%{$search}%")
                    ->orWhere('jenis_material', 'like', "%{$search}%")
                    ->orWhere('merk_material', 'like', "%{$search}%");
            }))
            ->orderBy('kode_barang')
            ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_hpp', 'jenis_material', 'merk_material', 'kategori_material']);

        $latestHistories = MaterialPriceHistory::query()
            ->whereIn('barang_material_id', $materials->pluck('id'))
            ->whereDate('tanggal_berlaku', '<=', $tanggalBerlaku)
            ->latest('tanggal_berlaku')
            ->latest('id')
            ->get()
            ->unique('barang_material_id')
            ->keyBy('barang_material_id');

        return Inertia::render('Admin/Logistik/HargaMaterial', [
            'title' => 'Harga Dasar Material',
            'baseUrl' => route('admin.harga-material.index', absolute: false),
            'syncUrl' => route('admin.harga-material.sync', absolute: false),
            'rows' => $materials->map(function (BarangMaterial $material) use ($latestHistories) {
                $history = $latestHistories->get($material->id);

                return [
                    'id' => $material->id,
                    'kode_barang' => $material->kode_barang,
                    'nama_barang' => $material->nama_barang,
                    'jenis_material' => $material->jenis_material ?: $material->kategori_material,
                    'merk_material' => $material->merk_material,
                    'satuan' => $material->satuan,
                    'harga_hpp' => (float) ($history?->harga_satuan ?? $material->harga_hpp),
                    'harga_terakhir' => (float) ($history?->harga_satuan ?? $material->harga_hpp),
                    'tanggal_terakhir' => optional($history?->tanggal_berlaku)->format('Y-m-d'),
                    'keterangan_terakhir' => $history?->keterangan,
                ];
            })->values(),
            'filters' => ['search' => $search, 'tanggal_berlaku' => $tanggalBerlaku],
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

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal_berlaku' => ['required', 'date'],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['items'] as $item) {
                $material = BarangMaterial::query()->findOrFail($item['barang_material_id']);
                $nextPrice = (float) ($item['harga_satuan'] ?? 0);
                $priceAtDate = (float) (MaterialPriceHistory::query()
                    ->where('barang_material_id', $material->id)
                    ->whereDate('tanggal_berlaku', '<=', $validated['tanggal_berlaku'])
                    ->latest('tanggal_berlaku')
                    ->latest('id')
                    ->value('harga_satuan') ?? $material->harga_hpp);

                if ($nextPrice <= 0 || $nextPrice === $priceAtDate) {
                    continue;
                }

                MaterialPriceHistory::query()->create([
                    'barang_material_id' => $material->id,
                    'tanggal_berlaku' => $validated['tanggal_berlaku'],
                    'harga_satuan' => $nextPrice,
                    'keterangan' => $validated['keterangan'] ?? 'Update harga massal.',
                    'status' => 'aktif',
                    'created_by' => auth()->id(),
                ]);

                $this->syncMaterialCurrentPrice($material);
            }
        });

        return back()->with('success', 'Harga material berhasil disimpan sekaligus.');
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
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }

    protected function syncMaterialCurrentPrice(BarangMaterial $material): void
    {
        $latestPrice = MaterialPriceHistory::query()
            ->where('barang_material_id', $material->id)
            ->where('status', 'aktif')
            ->latest('tanggal_berlaku')
            ->latest('id')
            ->value('harga_satuan');

        if ($latestPrice !== null) {
            $material->update(['harga_hpp' => $latestPrice]);
        }
    }

    protected function modelClass(): string
    {
        return MaterialPriceHistory::class;
    }
}
