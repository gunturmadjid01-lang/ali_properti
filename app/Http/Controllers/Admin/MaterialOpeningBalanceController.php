<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialOpeningBalance;
use App\Models\StokMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaterialOpeningBalanceController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));

        return Inertia::render('Admin/Logistik/SaldoAwalMaterial', [
            'title' => 'Saldo Awal Material',
            'baseUrl' => route('admin.saldo-awal-material.index', absolute: false),
            'rows' => MaterialOpeningBalance::query()
                ->with(['gudang:id,nama_gudang', 'barangMaterial:id,kode_barang,nama_barang,satuan,harga_hpp'])
                ->when($gudangId !== '', fn (Builder $query) => $query->where('gudang_id', $gudangId))
                ->when($search !== '', fn (Builder $query) => $query
                    ->whereHas('barangMaterial', fn (Builder $query) => $query
                        ->where('kode_barang', 'like', "%{$search}%")
                        ->orWhere('nama_barang', 'like', "%{$search}%"))
                    ->orWhereHas('gudang', fn (Builder $query) => $query->where('nama_gudang', 'like', "%{$search}%")))
                ->latest('tanggal_saldo')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialOpeningBalance $row) => [
                    'id' => $row->id,
                    'gudang_id' => (string) $row->gudang_id,
                    'barang_material_id' => (string) $row->barang_material_id,
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'kode_barang' => $row->barangMaterial?->kode_barang,
                    'nama_barang' => $row->barangMaterial?->nama_barang,
                    'satuan' => $row->barangMaterial?->satuan,
                    'tanggal_saldo' => optional($row->tanggal_saldo)->format('Y-m-d'),
                    'qty' => $row->qty,
                    'harga_satuan' => $row->harga_satuan,
                    'total_nilai' => $row->total_nilai,
                    'catatan' => $row->catatan,
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => ['search' => $search, 'gudang_id' => $gudangId],
            'options' => [
                'gudangs' => Gudang::query()->where('status', 'aktif')->orderBy('nama_gudang')->get(['id', 'nama_gudang'])->map(fn (Gudang $row) => [
                    'value' => (string) $row->id,
                    'label' => $row->nama_gudang,
                ])->values(),
                'materials' => BarangMaterial::query()->where('status', 'aktif')->orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_hpp'])->map(fn (BarangMaterial $row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->kode_barang} - {$row->nama_barang}",
                    'satuan' => $row->satuan,
                    'harga_hpp' => $row->harga_hpp,
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->payload($request);

        DB::transaction(function () use ($payload): void {
            $row = MaterialOpeningBalance::query()->create([
                ...$payload,
                'total_nilai' => (float) $payload['qty'] * (float) $payload['harga_satuan'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, (float) $row->qty);
        });

        return back()->with('success', 'Saldo awal material berhasil disimpan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $row = MaterialOpeningBalance::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $payload = $this->payload($request, $row->id);

        DB::transaction(function () use ($row, $payload): void {
            $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, -1 * (float) $row->qty);

            $row->update([
                ...$payload,
                'total_nilai' => (float) $payload['qty'] * (float) $payload['harga_satuan'],
                'updated_by' => auth()->id(),
            ]);

            $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, (float) $row->qty);
        });

        return back()->with('success', 'Saldo awal material berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = MaterialOpeningBalance::query()->findOrFail($id);
        $this->abortIfLocked($row);

        DB::transaction(function () use ($row): void {
            $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, -1 * (float) $row->qty);
            $row->delete();
        });

        return back()->with('success', 'Saldo awal material berhasil dihapus.');
    }

    protected function payload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'gudang_id' => [
                'required',
                'exists:gudangs,id',
                Rule::unique('material_opening_balances')
                    ->where(fn ($query) => $query->where('barang_material_id', $request->input('barang_material_id'))->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'barang_material_id' => ['required', 'exists:barang_materials,id'],
            'tanggal_saldo' => ['required', 'date'],
            'qty' => ['required', 'numeric', 'min:0'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
        ]);
    }

    protected function adjustStock(int $gudangId, int $materialId, float $delta): void
    {
        $stock = StokMaterial::query()->firstOrCreate(
            ['gudang_id' => $gudangId, 'barang_material_id' => $materialId],
            ['cabang_id' => null, 'qty' => 0],
        );

        $nextQty = (float) $stock->qty + $delta;
        abort_if($nextQty < 0, 422, 'Stok material tidak boleh menjadi minus.');

        $stock->update(['qty' => $nextQty]);
    }

    protected function modelClass(): string
    {
        return MaterialOpeningBalance::class;
    }
}
