<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\BarangMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MasterMaterialController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = BarangMaterial::query()
            ->when($search !== '', fn (Builder $query) => $query->where('kode_barang', 'like', "%{$search}%")
                ->orWhere('nama_barang', 'like', "%{$search}%")
                ->orWhere('kategori_material', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (BarangMaterial $row) => [
                'id' => $row->id,
                'kode_barang' => $row->kode_barang,
                'nama_barang' => $row->nama_barang,
                'kategori_material' => $row->kategori_material,
                'satuan' => $row->satuan,
                'harga_hpp' => $row->harga_hpp,
                'stok_minimum' => $row->stok_minimum,
                'catatan' => $row->catatan,
                'status' => $row->status,
                'record_status' => $row->record_status ?? 'draft',
                'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            ]);

        return Inertia::render('Admin/Logistik/MasterMaterial', [
            'title' => 'Kelola Item Material',
            'baseUrl' => route('admin.master-material.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'options' => [
                'kategoriMaterial' => $this->kategoriMaterialOptions(),
                'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->payload($request);

        DB::transaction(function () use ($payload) {
            $material = BarangMaterial::query()->create([
                ...$payload,
                'kode_barang' => $this->nextCode(),
            ]);

            $this->recordPrice($material, (float) $payload['harga_hpp'], 'Harga awal material.');
        });

        return back()->with('success', 'Material berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $material = BarangMaterial::query()->findOrFail($id);
        $this->abortIfLocked($material);
        $payload = $this->payload($request);
        $oldPrice = (float) $material->harga_hpp;

        DB::transaction(function () use ($material, $payload, $oldPrice) {
            $material->update($payload);

            if ((float) $payload['harga_hpp'] !== $oldPrice) {
                $this->recordPrice($material, (float) $payload['harga_hpp'], 'Update harga dari master material.');
            }
        });

        return back()->with('success', 'Material berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $material = BarangMaterial::query()->findOrFail($id);
        $this->abortIfLocked($material);
        $material->delete();

        return back()->with('success', 'Material berhasil dihapus.');
    }

    protected function payload(Request $request): array
    {
        return $request->validate([
            'nama_barang' => ['required', 'string', 'max:255'],
            'kategori_material' => ['nullable', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:255'],
            'harga_hpp' => ['required', 'numeric', 'min:0'],
            'stok_minimum' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }

    protected function nextCode(): string
    {
        return 'MAT-'.str_pad((string) (BarangMaterial::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function recordPrice(BarangMaterial $material, float $price, string $note): void
    {
        $material->priceHistories()->create([
            'tanggal_berlaku' => now()->toDateString(),
            'harga_satuan' => $price,
            'keterangan' => $note,
            'status' => 'aktif',
            'created_by' => auth()->id(),
        ]);
    }

    protected function kategoriMaterialOptions(): array
    {
        $defaults = [
            'Struktur',
            'Dinding',
            'Atap',
            'Plafon',
            'Lantai & Keramik',
            'Sanitair',
            'Pipa & Plumbing',
            'Listrik',
            'Cat & Finishing',
            'Pintu, Jendela & Kusen',
            'Besi & Baja',
            'Kayu & Papan',
            'Pasir, Batu & Semen',
            'Alat Kerja',
            'Lainnya',
        ];

        $existing = BarangMaterial::query()
            ->whereNotNull('kategori_material')
            ->where('kategori_material', '!=', '')
            ->distinct()
            ->orderBy('kategori_material')
            ->pluck('kategori_material')
            ->all();

        return collect(['' => 'Tanpa Kategori'])
            ->merge(collect($defaults)->combine($defaults))
            ->merge(collect($existing)->combine($existing))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    protected function modelClass(): string
    {
        return BarangMaterial::class;
    }
}
