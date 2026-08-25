<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialBrand;
use App\Models\MaterialType;
use App\Models\MaterialUnit;
use App\Services\ApprovalWorkflowService;
use App\Services\MaterialUnitConversionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MasterMaterialController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('master-material.view') || $request->user()?->can('master-material.manage'), 403);
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $gudangs = $this->accessibleGudangs();
        $allowedGudangIds = $gudangs->pluck('value')->all();

        if ($gudangId !== '' && ! in_array((string) $gudangId, $allowedGudangIds, true)) {
            $gudangId = '';
        }

        $stockGudangIds = $gudangId !== ''
            ? collect([(int) $gudangId])
            : collect($allowedGudangIds)->map(fn ($id) => (int) $id)->filter()->values();

        $rows = BarangMaterial::query()
            ->with(['materialType:id,name', 'materialBrand:id,name', 'baseUnit:id,name,symbol'])
            ->withSum([
                'stokMaterials as stok_tersedia' => fn (Builder $query) => $stockGudangIds->isNotEmpty()
                    ? $query->whereIn('gudang_id', $stockGudangIds)
                    : $query,
            ], 'qty')
            ->when($search !== '', fn (Builder $query) => $query->where('kode_barang', 'like', "%{$search}%")
                ->orWhere('nama_barang', 'like', "%{$search}%")
                ->orWhere('jenis_material', 'like', "%{$search}%")
                ->orWhere('merk_material', 'like', "%{$search}%")
                ->orWhere('kategori_material', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (BarangMaterial $row) => [
                'id' => $row->id,
                'kode_barang' => $row->kode_barang,
                'nama_barang' => $row->nama_barang,
                'jenis_material' => $row->materialType?->name ?? $row->jenis_material ?: $row->kategori_material,
                'merk_material' => $row->materialBrand?->name ?? $row->merk_material,
                'satuan' => $row->baseUnit?->symbol ?? $row->satuan,
                'harga_hpp' => $row->harga_hpp,
                'stok_tersedia' => (float) ($row->stok_tersedia ?? 0),
                'stok_minimum' => $row->stok_minimum,
                'catatan' => $row->catatan,
                'status' => $row->status,
                'record_status' => $row->record_status ?? 'draft',
                'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            ]);

        return Inertia::render('Admin/Logistik/MasterMaterial', [
            'title' => 'Kelola Item Material',
            'baseUrl' => route('admin.master-material.index', absolute: false),
            'createUrl' => route('admin.master-material.create', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search, 'gudang_id' => $gudangId],
            'options' => [
                'gudangs' => $gudangs,
                'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
            ],
            'permissions' => [
                'canCreate' => (bool) ($request->user()?->can('master-material.create') || $request->user()?->can('master-material.manage')),
                'canUpdate' => (bool) ($request->user()?->can('master-material.update') || $request->user()?->can('master-material.manage')),
                'canDelete' => (bool) ($request->user()?->can('master-material.delete') || $request->user()?->can('master-material.manage')),
                'canLock' => (bool) ($request->user()?->can('master-material.lock') || $request->user()?->hasRole('super_admin')),
                'canUnlock' => $this->currentUserCanManageLockedRecords(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('master-material.create') || $request->user()?->can('master-material.manage'), 403);

        return $this->renderForm();
    }

    public function edit(Request $request, string $id): Response
    {
        abort_unless($request->user()?->can('master-material.update') || $request->user()?->can('master-material.manage'), 403);
        $material = BarangMaterial::query()->with('unitConversions')->findOrFail($id);
        $this->abortIfLocked($material);

        return $this->renderForm($material);
    }

    public function store(Request $request, ApprovalWorkflowService $approvalWorkflow, MaterialUnitConversionService $conversionService): RedirectResponse
    {
        abort_unless($request->user()?->can('master-material.create') || $request->user()?->can('master-material.manage'), 403);
        $payload = $this->payload($request);
        $submitAction = $request->input('submit_action', 'save');

        return $approvalWorkflow->create('master-material', $payload, function (array $payload) use ($conversionService) {
            DB::transaction(function () use ($payload, $conversionService) {
                $material = BarangMaterial::query()->create([
                    ...$payload,
                    'kode_barang' => $this->nextCode(),
                ]);

                $this->recordPrice($material, (float) $payload['harga_hpp'], 'Harga awal material.');
                $conversionService->sync($material, $payload['conversions'] ?? []);
            });
        }, function (bool $queued) use ($submitAction) {
            $message = $queued ? 'Material dikirim ke daftar approval.' : 'Material berhasil ditambahkan.';

            return $submitAction === 'add_another'
                ? to_route('admin.master-material.create')->with('success', $message.' Silakan tambah item berikutnya.')
                : to_route('admin.master-material.index')->with('success', $message);
        });
    }

    public function update(Request $request, string $id, ApprovalWorkflowService $approvalWorkflow, MaterialUnitConversionService $conversionService): RedirectResponse
    {
        abort_unless($request->user()?->can('master-material.update') || $request->user()?->can('master-material.manage'), 403);
        $material = BarangMaterial::query()->findOrFail($id);
        $this->abortIfLocked($material);
        $payload = $this->payload($request);
        $oldPrice = (float) $material->harga_hpp;

        $approvalWorkflow->update('master-material', $material, $payload, function (BarangMaterial $material, array $payload) use ($oldPrice, $conversionService) {
            DB::transaction(function () use ($material, $payload, $oldPrice, $conversionService) {
                $material->update($payload);

                if ((float) $payload['harga_hpp'] !== $oldPrice) {
                    $this->recordPrice($material, (float) $payload['harga_hpp'], 'Update harga dari master material.');
                }
                $conversionService->sync($material, $payload['conversions'] ?? []);
            });
        });

        return to_route('admin.master-material.index')->with('success', 'Material berhasil diperbarui.');
    }

    public function destroy(string $id, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $material = BarangMaterial::query()->findOrFail($id);
        $this->abortIfLocked($material);
        abort_unless(auth()->user()?->can('master-material.delete') || auth()->user()?->can('master-material.manage'), 403);

        return $approvalWorkflow->delete('master-material', $material, fn (BarangMaterial $material) => $material->delete());
    }

    protected function payload(Request $request): array
    {
        $payload = $request->validate([
            'nama_barang' => ['required', 'string', 'max:255'],
            'material_type_id' => ['required', 'exists:material_types,id'],
            'material_brand_id' => ['nullable', 'exists:material_brands,id'],
            'base_unit_id' => ['required', 'exists:material_units,id'],
            'harga_hpp' => ['required', 'numeric', 'min:0'],
            'stok_minimum' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'conversions' => ['nullable', 'array'],
            'conversions.*.unit_id' => ['required', 'distinct', 'exists:material_units,id'],
            'conversions.*.factor' => ['required', 'numeric', 'gt:0'],
        ]);

        $type = MaterialType::query()->findOrFail($payload['material_type_id']);
        $brand = filled($payload['material_brand_id'] ?? null) ? MaterialBrand::query()->findOrFail($payload['material_brand_id']) : null;
        $unit = MaterialUnit::query()->findOrFail($payload['base_unit_id']);
        $payload['jenis_material'] = $type->name;
        $payload['kategori_material'] = $type->name;
        $payload['merk_material'] = $brand?->name;
        $payload['satuan'] = $unit->symbol;

        return $payload;
    }

    protected function renderForm(?BarangMaterial $material = null): Response
    {
        return Inertia::render('Admin/Logistik/MasterMaterial/Form', [
            'title' => $material ? 'Edit Material' : 'Tambah Material',
            'indexUrl' => route('admin.master-material.index', absolute: false),
            'actionUrl' => $material ? route('admin.master-material.update', $material->id, false) : route('admin.master-material.store', absolute: false),
            'method' => $material ? 'put' : 'post',
            'material' => $material ? [
                ...$material->only(['id', 'nama_barang', 'material_type_id', 'material_brand_id', 'base_unit_id', 'harga_hpp', 'stok_minimum', 'catatan', 'status']),
                'conversions' => $material->unitConversions->map(fn ($row) => ['unit_id' => (string) $row->child_unit_id, 'factor' => $row->factor])->values(),
            ] : null,
            'options' => [
                'types' => MaterialType::query()->finalized()->where('status', 'aktif')->orderBy('name')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name])->values(),
                'brands' => MaterialBrand::query()->finalized()->where('status', 'aktif')->orderBy('name')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name])->values(),
                'units' => MaterialUnit::query()->finalized()->where('status', 'aktif')->orderBy('name')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->name} ({$row->symbol})", 'symbol' => $row->symbol])->values(),
                'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
            ],
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

    protected function jenisMaterialOptions(): array
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
            ->whereNotNull('jenis_material')
            ->where('jenis_material', '!=', '')
            ->distinct()
            ->orderBy('jenis_material')
            ->pluck('jenis_material')
            ->all();

        return collect(['' => 'Tanpa Jenis'])
            ->merge(collect($defaults)->combine($defaults))
            ->merge(collect($existing)->combine($existing))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
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

    protected function modelClass(): string
    {
        return BarangMaterial::class;
    }

    public function lock(string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->can('master-material.lock') || auth()->user()?->hasRole('super_admin'), 403);

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->currentUserCanManageLockedRecords(), 403);

        return $this->traitUnlock($id);
    }
}
