<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialOpeningBalance;
use App\Models\StokMaterial;
use App\Services\MaterialUnitConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaterialOpeningBalanceController extends Controller
{
    use HandlesCrudLock {
        lock as protected lockRecord;
        unlock as protected unlockRecord;
    }

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'view');
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
                ->with(['baseUnit', 'unitConversions.childUnit'])
                ->where('status', 'aktif')
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'base_unit_id', 'satuan', 'harga_hpp', 'jenis_material', 'merk_material', 'kategori_material'])
                ->map(function (BarangMaterial $material) use ($balances, $selectedGudang, $gudangId) {
                    $balance = $balances->get($material->id);
                    $qty = (float) ($balance?->qty ?? 0);
                    $hargaSatuan = (float) ($balance?->harga_satuan ?? $material->harga_hpp);
                    $unitOptions = app(MaterialUnitConversionService::class)->options($material);
                    $inputUnitId = (string) ($balance?->input_unit_id ?? $material->base_unit_id ?? '');
                    $selectedUnit = collect($unitOptions)->firstWhere('value', $inputUnitId) ?? $unitOptions[0];
                    $factor = max(0.000001, (float) ($balance?->conversion_to_base ?? $selectedUnit['factor_to_base'] ?? 1));
                    $inputQty = (float) ($balance?->input_qty ?? $qty);

                    return [
                        'id' => $material->id,
                        'gudang_id' => $gudangId,
                        'gudang' => $selectedGudang?->nama_gudang ?? '-',
                        'kode_barang' => $material->kode_barang,
                        'nama_barang' => $material->nama_barang,
                        'jenis_material' => $material->jenis_material ?: $material->kategori_material,
                        'merk_material' => $material->merk_material,
                        'satuan' => $material->baseUnit?->symbol ?? $material->satuan,
                        'material_unit_id' => $inputUnitId,
                        'unit_options' => $unitOptions,
                        'harga_satuan' => $hargaSatuan / $factor,
                        'tanggal_saldo' => optional($balance?->tanggal_saldo)->format('Y-m-d') ?? now()->toDateString(),
                        'qty' => $inputQty,
                        'qty_base' => $qty,
                        'total_nilai' => (float) ($balance?->total_nilai ?? ($qty * $hargaSatuan)),
                        'catatan' => $balance?->catatan ?? '',
                        'balance_id' => (string) ($balance?->id ?? ''),
                        'record_status' => $balance?->record_status ?? 'draft',
                        'record_status_label' => ($balance?->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                        'can_delete' => (bool) $balance,
                        'can_edit' => (bool) ($balance ? auth()->user()?->can('material-opening-balance.update') : auth()->user()?->can('material-opening-balance.create')),
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
            'permissions' => [
                'canCreate' => (bool) $request->user()?->can('material-opening-balance.create'),
                'canUpdate' => (bool) $request->user()?->can('material-opening-balance.update'),
                'canDelete' => (bool) $request->user()?->can('material-opening-balance.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'create');
        $payload = $this->normalizeBalancePayload($this->payload($request));
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
        $this->authorizeAnyPermission($request, ['create', 'update']);
        $validated = $request->validate([
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'tanggal_saldo' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.material_unit_id' => ['required', 'exists:material_units,id'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.catatan' => ['nullable', 'string'],
        ]);

        $this->assertGudangAccess($validated['gudang_id']);

        $existingMaterialIds = MaterialOpeningBalance::query()->where('gudang_id', $validated['gudang_id'])->pluck('barang_material_id')->map(fn ($id) => (int) $id);
        $needsCreate = collect($validated['items'])->contains(fn (array $item) => (float) ($item['qty'] ?? 0) > 0 && ! $existingMaterialIds->contains((int) $item['barang_material_id']));
        $needsUpdate = collect($validated['items'])->contains(fn (array $item) => $existingMaterialIds->contains((int) $item['barang_material_id']));
        if ($needsCreate) {
            $this->authorizePermission($request, 'create');
        }
        if ($needsUpdate) {
            $this->authorizePermission($request, 'update');
        }

        DB::transaction(function () use ($validated): void {
            foreach ($validated['items'] as $item) {
                $materialId = (int) $item['barang_material_id'];
                $inputQty = (float) ($item['qty'] ?? 0);
                $catatan = $item['catatan'] ?? null;
                $existing = MaterialOpeningBalance::query()
                    ->where('gudang_id', $validated['gudang_id'])
                    ->where('barang_material_id', $materialId)
                    ->first();
                $normalized = $this->normalizeBalancePayload([
                    'gudang_id' => $validated['gudang_id'],
                    'barang_material_id' => $materialId,
                    'material_unit_id' => $item['material_unit_id'],
                    'tanggal_saldo' => $validated['tanggal_saldo'],
                    'qty' => $inputQty,
                    'harga_satuan' => (float) ($item['harga_satuan'] ?? 0),
                    'catatan' => $catatan,
                ]);

                if ($inputQty <= 0) {
                    if ($existing) {
                        $this->abortIfLocked($existing);
                        $this->adjustStock((int) $existing->gudang_id, (int) $existing->barang_material_id, -1 * (float) $existing->qty);
                        $existing->update([
                            'tanggal_saldo' => $validated['tanggal_saldo'],
                            'qty' => 0,
                            'harga_satuan' => $normalized['harga_satuan'],
                            'total_nilai' => 0,
                            'input_qty' => 0,
                            'input_unit_id' => $normalized['input_unit_id'],
                            'input_unit_symbol' => $normalized['input_unit_symbol'],
                            'conversion_to_base' => $normalized['conversion_to_base'],
                            'catatan' => $catatan,
                            'updated_by' => auth()->id(),
                        ]);
                    }

                    continue;
                }

                if ($existing) {
                    $this->abortIfLocked($existing);
                    $delta = (float) $normalized['qty'] - (float) $existing->qty;
                    if ($delta !== 0.0) {
                        $this->adjustStock((int) $existing->gudang_id, (int) $existing->barang_material_id, $delta);
                    }
                    $existing->update([
                        'tanggal_saldo' => $validated['tanggal_saldo'],
                        ...collect($normalized)->except(['gudang_id', 'barang_material_id'])->all(),
                        'total_nilai' => (float) $normalized['qty'] * (float) $normalized['harga_satuan'],
                        'catatan' => $catatan,
                        'updated_by' => auth()->id(),
                    ]);

                    continue;
                }

                $row = MaterialOpeningBalance::query()->create([
                    ...$normalized,
                    'total_nilai' => (float) $normalized['qty'] * (float) $normalized['harga_satuan'],
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, (float) $normalized['qty']);
            }
        });

        return back()->with('success', 'Saldo awal material berhasil disinkronkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'update');
        $row = MaterialOpeningBalance::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $payload = $this->normalizeBalancePayload($this->payload($request, $row->id));
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

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'delete');
        $row = MaterialOpeningBalance::query()->findOrFail($id);
        $this->assertGudangAccess($row->gudang_id);
        $this->abortIfLocked($row);

        DB::transaction(function () use ($row): void {
            $this->adjustStock((int) $row->gudang_id, (int) $row->barang_material_id, -1 * (float) $row->qty);
            $row->update([
                'qty' => 0,
                'input_qty' => 0,
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
            'material_unit_id' => ['required', 'exists:material_units,id'],
            'tanggal_saldo' => ['required', 'date'],
            'qty' => ['required', 'numeric', 'min:0'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
        ]);
    }

    protected function normalizeBalancePayload(array $payload): array
    {
        $material = BarangMaterial::query()->with(['baseUnit', 'unitConversions.childUnit'])->findOrFail($payload['barang_material_id']);
        $unitOption = collect(app(MaterialUnitConversionService::class)->options($material))->firstWhere('value', (string) $payload['material_unit_id']);
        $inputPrice = (float) $payload['harga_satuan'];
        if ($inputPrice <= 0) {
            $inputPrice = (float) ($unitOption['standard_price'] ?? 0);
        }
        $normalized = app(MaterialUnitConversionService::class)->normalize(
            $material,
            $payload['material_unit_id'],
            (float) $payload['qty'],
            $inputPrice,
        );

        return [
            ...collect($payload)->except(['material_unit_id'])->all(),
            'qty' => $normalized['quantity_base'],
            'harga_satuan' => $normalized['unit_price_base'],
            'input_qty' => (float) $payload['qty'],
            'input_unit_id' => $normalized['unit_id'],
            'input_unit_symbol' => $normalized['unit_symbol'],
            'conversion_to_base' => $normalized['factor_to_base'],
        ];
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

    public function lock(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'update');

        return $this->lockRecord($id);
    }

    public function unlock(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'unlock');

        return $this->unlockRecord($id);
    }

    protected function authorizePermission(Request $request, string $action): void
    {
        abort_unless(
            $request->user()?->can("material-opening-balance.{$action}") || $request->user()?->can('material-opening-balance.manage'),
            403,
            'Anda tidak memiliki permission Saldo Awal Material untuk aksi ini.',
        );
    }

    protected function authorizeAnyPermission(Request $request, array $actions): void
    {
        abort_unless(
            collect($actions)->contains(fn (string $action) => $request->user()?->can("material-opening-balance.{$action}"))
                || $request->user()?->can('material-opening-balance.manage'),
            403,
            'Anda tidak memiliki permission untuk memproses Saldo Awal Material.',
        );
    }

    protected function modelClass(): string
    {
        return MaterialOpeningBalance::class;
    }

    protected function accessibleGudangs()
    {
        $user = auth()->user();

        $query = Gudang::query()->finalized()->where('status', 'aktif');

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
