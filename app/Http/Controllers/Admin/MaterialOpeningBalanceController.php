<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialOpeningBalance;
use App\Models\StokMaterial;
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
        $gudangId = trim((string) $request->query('gudang_id', ''));

        $gudangs = $this->accessibleGudangs();
        $allowedIds = $gudangs->pluck('value')->all();

        if ($gudangId !== '' && ! in_array((string) $gudangId, $allowedIds, true)) {
            $gudangId = '';
        }

        $selectedGudang = filled($gudangId) ? Gudang::query()->find($gudangId) : null;
        $balances = filled($gudangId)
            ? MaterialOpeningBalance::query()->where('gudang_id', $gudangId)->get()->keyBy('barang_material_id')
            : collect();

        $rows = filled($gudangId)
            ? BarangMaterial::query()
                ->where('status', 'aktif')
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_hpp', 'jenis_material', 'merk_material', 'kategori_material'])
                ->map(function (BarangMaterial $material) use ($balances, $selectedGudang, $gudangId) {
                    $balance = $balances->get($material->id);
                    $qty = (float) ($balance?->qty ?? 0);
                    $hargaSatuan = (float) ($balance?->harga_satuan ?? $material->harga_hpp);

                    return [
                    'id' => $material->id,
                    'gudang_id' => $gudangId,
                    'gudang' => $selectedGudang?->nama_gudang ?? '-',
                    'kode_barang' => $material->kode_barang,
                    'nama_barang' => $material->nama_barang,
                    'jenis_material' => $material->jenis_material ?: $material->kategori_material,
                    'merk_material' => $material->merk_material,
                    'satuan' => $material->satuan,
                    'harga_satuan' => $hargaSatuan,
                    'tanggal_saldo' => optional($balance?->tanggal_saldo)->format('Y-m-d') ?? now()->toDateString(),
                    'qty' => $qty,
                    'total_nilai' => (float) ($balance?->total_nilai ?? ($qty * $hargaSatuan)),
                    'catatan' => $balance?->catatan ?? '',
                    'balance_id' => (string) ($balance?->id ?? ''),
                    'record_status' => $balance?->record_status ?? 'draft',
                    'record_status_label' => ($balance?->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                    'can_delete' => (bool) $balance,
                ];
                })
                ->values()
            : collect();

        return Inertia::render('Admin/Logistik/SaldoAwalMaterial', [
            'title' => 'Saldo Awal Material',
            'baseUrl' => route('admin.saldo-awal-material.index', absolute: false),
            'rows' => $rows,
            'selectedGudang' => $selectedGudang ? [
                'id' => (string) $selectedGudang->id,
                'nama_gudang' => $selectedGudang->nama_gudang,
            ] : null,
            'assignmentWarning' => $this->assignmentWarning(),
            'filters' => ['gudang_id' => $gudangId],
            'options' => [
                'gudangs' => $gudangs,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $this->payload($request);
        $this->assertGudangAccess($payload['gudang_id']);

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

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'tanggal_saldo' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.catatan' => ['nullable', 'string'],
        ]);

        $this->assertGudangAccess($validated['gudang_id']);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['items'] as $item) {
                $materialId = (int) $item['barang_material_id'];
                $qty = (float) ($item['qty'] ?? 0);
                $catatan = $item['catatan'] ?? null;
                $existing = MaterialOpeningBalance::query()
                    ->where('gudang_id', $validated['gudang_id'])
                    ->where('barang_material_id', $materialId)
                    ->first();
                $material = BarangMaterial::query()->findOrFail($materialId);

                if ($qty <= 0) {
                    if ($existing) {
                        $this->abortIfLocked($existing);
                        $this->adjustStock((int) $existing->gudang_id, (int) $existing->barang_material_id, -1 * (float) $existing->qty);
                        $existing->update([
                            'tanggal_saldo' => $validated['tanggal_saldo'],
                            'qty' => 0,
                            'harga_satuan' => (float) ($item['harga_satuan'] ?? $existing->harga_satuan ?? $material->harga_hpp),
                            'total_nilai' => 0,
                            'catatan' => $catatan,
                            'updated_by' => auth()->id(),
                        ]);
                    }
                    continue;
                }

                $nextHarga = (float) ($item['harga_satuan'] ?? 0);
                if ($nextHarga <= 0) {
                    $nextHarga = (float) ($existing?->harga_satuan ?? $material->harga_hpp);
                }

                if ($existing) {
                    $this->abortIfLocked($existing);
                    $delta = $qty - (float) $existing->qty;
                    if ($delta !== 0.0) {
                        $this->adjustStock((int) $existing->gudang_id, (int) $existing->barang_material_id, $delta);
                    }
                    $existing->update([
                        'tanggal_saldo' => $validated['tanggal_saldo'],
                        'qty' => $qty,
                        'harga_satuan' => $nextHarga,
                        'total_nilai' => $qty * $nextHarga,
                        'catatan' => $catatan,
                        'updated_by' => auth()->id(),
                    ]);
                    continue;
                }

                $row = MaterialOpeningBalance::query()->create([
                    'gudang_id' => $validated['gudang_id'],
                    'barang_material_id' => $materialId,
                    'tanggal_saldo' => $validated['tanggal_saldo'],
                    'qty' => $qty,
                    'harga_satuan' => $nextHarga,
                    'total_nilai' => $qty * $nextHarga,
                    'catatan' => $catatan,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, $qty);
            }
        });

        return back()->with('success', 'Saldo awal material berhasil disinkronkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $row = MaterialOpeningBalance::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $payload = $this->payload($request, $row->id);
        $this->assertGudangAccess($payload['gudang_id']);

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
            $row->update([
                'qty' => 0,
                'total_nilai' => 0,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Saldo awal material berhasil dikosongkan.');
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

    protected function accessibleGudangs()
    {
        $user = auth()->user();

        $query = Gudang::query()->where('status', 'aktif');

        if ($user?->hasAnyRole(['user_area_gudang', 'admin_gudang'])) {
            $assignedIds = $this->assignedGudangIds($user);
            if ($assignedIds->isEmpty()) {
                return collect();
            }

            $query->whereIn('id', $assignedIds);
        }

        return $query->orderBy('nama_gudang')
            ->get(['id', 'nama_gudang'])
            ->map(fn (Gudang $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_gudang,
            ])
            ->values();
    }

    protected function assertGudangAccess(int|string|null $gudangId): void
    {
        $user = auth()->user();

        if (! $user?->hasAnyRole(['user_area_gudang', 'admin_gudang'])) {
            return;
        }

        $assignedIds = $this->assignedGudangIds($user);
        if ($assignedIds->isEmpty()) {
            abort(422, 'Akun gudang belum ditugaskan ke gudang tertentu.');
        }

        abort_unless($assignedIds->contains((int) $gudangId), 403, 'Akun ini hanya dapat mengakses gudang yang ditugaskan.');
    }

    protected function assignmentWarning(): ?string
    {
        $user = auth()->user();

        if (! $user?->hasAnyRole(['user_area_gudang', 'admin_gudang'])) {
            return null;
        }

        return $this->assignedGudangIds($user)->isNotEmpty()
            ? null
            : 'Akun gudang ini belum ditugaskan ke gudang tertentu. Minta admin menetapkan gudang pada data user.';
    }

    protected function assignedGudangIds($user)
    {
        if (! $user) {
            return collect();
        }

        $ids = $user->gudangs()->pluck('gudangs.id')->map(fn ($id) => (int) $id);

        if ($ids->isEmpty() && filled($user->gudang_id)) {
            $ids = collect([(int) $user->gudang_id]);
        }

        return $ids->filter()->unique()->values();
    }
}
