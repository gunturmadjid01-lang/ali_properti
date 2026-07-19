<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\MaterialGroup;
use App\Services\ApprovalWorkflowService;
use App\Services\MaterialGroupService;
use App\Services\MaterialUnitConversionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'view');
        $search = trim((string) $request->query('search', ''));
        $rows = MaterialGroup::query()
            ->with(['items.material:id,kode_barang,nama_barang', 'items.unit:id,name,symbol'])
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MaterialGroup $group) => [
                'id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'base_quantity' => $group->base_quantity,
                'base_unit' => $group->base_unit,
                'items_summary' => $group->items->map(fn ($item) => [
                    'material' => $item->material?->nama_barang ?? '-',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit?->symbol ?? '-',
                ])->values(),
                'status' => $group->status,
            ]);

        return Inertia::render('Admin/Planning/MaterialGroup/Index', [
            'title' => 'Kelompok Material',
            'baseUrl' => route('admin.material-group.index', absolute: false),
            'createUrl' => route('admin.material-group.create', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'permissions' => $this->permissions($request),
        ]);
    }

    public function create(Request $request, MaterialUnitConversionService $conversionService): Response
    {
        $this->authorizeAction($request, 'create');

        return $this->renderForm(null, $conversionService);
    }

    public function edit(Request $request, MaterialGroup $materialGroup, MaterialUnitConversionService $conversionService): Response
    {
        $this->authorizeAction($request, 'update');
        $materialGroup->load('items');

        return $this->renderForm($materialGroup, $conversionService);
    }

    public function store(Request $request, ApprovalWorkflowService $approvalWorkflow, MaterialGroupService $groupService): RedirectResponse
    {
        $this->authorizeAction($request, 'create');
        $payload = $this->payload($request, $groupService);
        $payload['created_by'] = $request->user()->id;
        $submitAction = $request->string('submit_action')->toString() ?: 'save';

        return $approvalWorkflow->create('material-group', $payload, function (array $payload) use ($groupService) {
            DB::transaction(function () use ($payload, $groupService) {
                $group = MaterialGroup::query()->create(collect($payload)->except('items')->all());
                $groupService->syncItems($group, $payload['items']);
            });
        }, function (bool $queued) use ($submitAction) {
            $message = $queued ? 'Kelompok material dikirim ke daftar approval.' : 'Kelompok material berhasil ditambahkan.';

            return $submitAction === 'add_another'
                ? to_route('admin.material-group.create')->with('success', $message.' Silakan tambah kelompok berikutnya.')
                : to_route('admin.material-group.index')->with('success', $message);
        });
    }

    public function update(Request $request, MaterialGroup $materialGroup, ApprovalWorkflowService $approvalWorkflow, MaterialGroupService $groupService): RedirectResponse
    {
        $this->authorizeAction($request, 'update');
        $payload = $this->payload($request, $groupService);
        $payload['updated_by'] = $request->user()->id;

        $approvalWorkflow->update('material-group', $materialGroup, $payload, function (MaterialGroup $group, array $payload) use ($groupService) {
            DB::transaction(function () use ($group, $payload, $groupService) {
                $group->update(collect($payload)->except('items')->all());
                $groupService->syncItems($group, $payload['items']);
            });
        });

        return to_route('admin.material-group.index')->with('success', 'Kelompok material berhasil diperbarui.');
    }

    public function destroy(Request $request, MaterialGroup $materialGroup, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $this->authorizeAction($request, 'delete');

        return $approvalWorkflow->delete('material-group', $materialGroup, fn (MaterialGroup $group) => $group->delete());
    }

    private function payload(Request $request, MaterialGroupService $groupService): array
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_quantity' => ['required', 'numeric', 'gt:0'],
            'base_unit' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'distinct', 'exists:barang_materials,id'],
            'items.*.material_unit_id' => ['required', 'exists:material_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $payload['items'] = $groupService->normalizedItems($payload['items']);

        return $payload;
    }

    private function renderForm(?MaterialGroup $group, MaterialUnitConversionService $conversionService): Response
    {
        $materials = BarangMaterial::query()->finalized()
            ->with(['baseUnit', 'unitConversions.childUnit'])
            ->where('status', 'aktif')
            ->whereNotNull('base_unit_id')
            ->orderBy('nama_barang')
            ->get()
            ->map(fn (BarangMaterial $material) => [
                'value' => (string) $material->id,
                'label' => "{$material->kode_barang} - {$material->nama_barang}",
                'name' => $material->nama_barang,
                'base_unit' => $material->baseUnit?->symbol ?? $material->satuan,
                'unit_options' => $conversionService->options($material),
            ])->values();

        return Inertia::render('Admin/Planning/MaterialGroup/Form', [
            'title' => $group ? 'Edit Kelompok Material' : 'Tambah Kelompok Material',
            'indexUrl' => route('admin.material-group.index', absolute: false),
            'actionUrl' => $group ? route('admin.material-group.update', $group, false) : route('admin.material-group.store', absolute: false),
            'method' => $group ? 'put' : 'post',
            'group' => $group ? [
                ...$group->only(['id', 'name', 'base_quantity', 'base_unit', 'notes', 'status']),
                'items' => $group->items->map(fn ($item) => [
                    'barang_material_id' => (string) $item->barang_material_id,
                    'material_unit_id' => (string) $item->material_unit_id,
                    'quantity' => $item->quantity,
                ])->values(),
            ] : null,
            'materials' => $materials,
            'statusOptions' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($request->user()?->can("material-group.{$action}") || $request->user()?->can('material-group.manage'), 403);
    }

    private function permissions(Request $request): array
    {
        return [
            'canCreate' => (bool) ($request->user()?->can('material-group.create') || $request->user()?->can('material-group.manage')),
            'canUpdate' => (bool) ($request->user()?->can('material-group.update') || $request->user()?->can('material-group.manage')),
            'canDelete' => (bool) ($request->user()?->can('material-group.delete') || $request->user()?->can('material-group.manage')),
        ];
    }
}
