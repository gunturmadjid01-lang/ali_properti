<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialPurchaseRequest;
use App\Models\MaterialPurchaseRequestDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPurchaseRequestController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $status = trim((string) $request->query('status', ''));
        $sort = $request->query('sort', 'tanggal');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Admin/MaterialPurchaseRequest/Index', [
            'title' => 'Permintaan Pembelian Material',
            'baseUrl' => route('admin.material-purchase-request.index', absolute: false),
            'createUrl' => route('admin.material-purchase-request.create', absolute: false),
            'purchaseCreateUrl' => route('admin.material-purchase.create', absolute: false),
            'permissions' => $this->permissions(),
            'rows' => MaterialPurchaseRequest::query()
                ->with([
                    'gudang:id,nama_gudang,perumahan_id',
                    'gudang.perumahan:id,nama_perusahaan',
                    'requestedBy:id,name',
                    'approvedBy:id,name',
                    'processedBy:id,name',
                    'creator:id,name',
                    'updater:id,name',
                    'details.barangMaterial:id,kode_barang,nama_barang',
                ])
                ->withCount('details')
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_request', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%");
                }))
                ->when($gudangId !== '', fn (Builder $query) => $query->where('gudang_id', $gudangId))
                ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
                ->when(! $this->canViewAllPurchaseRequests(), function (Builder $query) {
                    $query->where(function (Builder $query) {
                        $query->where('record_status', 'locked')
                            ->orWhere('created_by', auth()->id());
                    });
                })
                ->orderBy(in_array($sort, ['tanggal', 'kode_request', 'status'], true) ? $sort : 'tanggal', $direction)
                ->orderBy('id', $direction)
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialPurchaseRequest $row) => $this->rowPayload($row)),
            'filters' => compact('search', 'gudangId', 'status', 'sort', 'direction'),
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->permissions()['canCreate'], 403, 'Anda tidak memiliki permission membuat permintaan pembelian.');

        return Inertia::render('Admin/MaterialPurchaseRequest/Create', [
            'title' => 'Tambah Permintaan Pembelian',
            'baseUrl' => route('admin.material-purchase-request.store', absolute: false),
            'indexUrl' => route('admin.material-purchase-request.index', absolute: false),
            'nextCode' => $this->nextRequestCode(),
            'requestRow' => null,
            'options' => $this->options(),
        ]);
    }

    public function edit(string $id): Response
    {
        $row = MaterialPurchaseRequest::query()->with('details.barangMaterial')->findOrFail($id);
        abort_unless($this->permissions()['canUpdate'], 403, 'Anda tidak memiliki permission mengubah permintaan pembelian.');
        $this->abortIfLocked($row);
        abort_unless($row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN, 422, 'Permintaan yang sudah diproses approval tidak dapat diubah.');

        return Inertia::render('Admin/MaterialPurchaseRequest/Create', [
            'title' => 'Edit Permintaan Pembelian',
            'baseUrl' => route('admin.material-purchase-request.update', $row->id, false),
            'indexUrl' => route('admin.material-purchase-request.index', absolute: false),
            'nextCode' => $row->kode_request,
            'requestRow' => $this->editPayload($row),
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->permissions()['canCreate'], 403, 'Anda tidak memiliki permission membuat permintaan pembelian.');
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $row = MaterialPurchaseRequest::query()->create([
                'kode_request' => $validated['kode_request'] ?: $this->nextRequestCode(),
                'tanggal' => $validated['tanggal'],
                'gudang_id' => $validated['gudang_id'],
                'status' => MaterialPurchaseRequest::STATUS_DIAJUKAN,
                'keterangan' => $validated['keterangan'] ?? null,
                'requested_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncDetails($row, $validated['items']);
        });

        return redirect()->route('admin.material-purchase-request.index')->with('success', 'Permintaan pembelian berhasil dibuat dan menunggu approval.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $row = MaterialPurchaseRequest::query()->findOrFail($id);
        abort_unless($this->permissions()['canUpdate'], 403, 'Anda tidak memiliki permission mengubah permintaan pembelian.');
        $this->abortIfLocked($row);
        abort_unless($row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN, 422, 'Permintaan yang sudah diproses approval tidak dapat diubah.');

        $validated = $this->validated($request, $row->id);

        DB::transaction(function () use ($row, $validated) {
            $row->update([
                'tanggal' => $validated['tanggal'],
                'gudang_id' => $validated['gudang_id'],
                'keterangan' => $validated['keterangan'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $row->details()->delete();
            $this->syncDetails($row, $validated['items']);
        });

        return redirect()->route('admin.material-purchase-request.index')->with('success', 'Permintaan pembelian berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = MaterialPurchaseRequest::query()->withCount('purchases')->findOrFail($id);
        abort_unless($this->permissions()['canDelete'], 403, 'Anda tidak memiliki permission menghapus permintaan pembelian.');
        $this->abortIfLocked($row);

        if ($row->purchases_count > 0 || $row->status === MaterialPurchaseRequest::STATUS_DIPROSES) {
            throw ValidationException::withMessages(['request' => 'Permintaan yang sudah dibuatkan pembelian tidak dapat dihapus.']);
        }

        $row->delete();

        return back()->with('success', 'Permintaan pembelian berhasil dihapus.');
    }

    public function approve(string $id): RedirectResponse
    {
        $row = MaterialPurchaseRequest::query()->findOrFail($id);
        abort_unless($this->permissions()['canApprove'], 403, 'Anda tidak memiliki permission approval permintaan pembelian.');
        $this->abortIfLocked($row);
        abort_unless($row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN, 422, 'Hanya permintaan berstatus diajukan yang bisa diapprove.');

        $row->update([
            'status' => MaterialPurchaseRequest::STATUS_DISETUJUI,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Permintaan pembelian berhasil diapprove.');
    }

    public function reject(string $id): RedirectResponse
    {
        $row = MaterialPurchaseRequest::query()->findOrFail($id);
        abort_unless($this->permissions()['canApprove'], 403, 'Anda tidak memiliki permission menolak permintaan pembelian.');
        $this->abortIfLocked($row);
        abort_unless($row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN, 422, 'Hanya permintaan berstatus diajukan yang bisa ditolak.');

        $row->update([
            'status' => MaterialPurchaseRequest::STATUS_DITOLAK,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Permintaan pembelian ditolak.');
    }

    protected function modelClass(): string
    {
        return MaterialPurchaseRequest::class;
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'kode_request' => ['nullable', 'string', 'max:255', 'unique:material_purchase_requests,kode_request'.($ignoreId ? ','.$ignoreId : '')],
            'tanggal' => ['required', 'date'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string', 'max:50'],
            'items.*.catatan' => ['nullable', 'string'],
        ]);
    }

    protected function syncDetails(MaterialPurchaseRequest $row, array $items): void
    {
        collect($items)
            ->filter(fn ($item) => (float) ($item['qty'] ?? 0) > 0)
            ->each(function ($item) use ($row) {
                $barang = BarangMaterial::query()->findOrFail($item['barang_material_id']);
                $row->details()->create([
                    'barang_material_id' => $barang->id,
                    'qty' => $item['qty'],
                    'satuan' => $item['satuan'] ?? $barang->satuan,
                    'catatan' => $item['catatan'] ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            });
    }

    protected function rowPayload(MaterialPurchaseRequest $row): array
    {
        $canEdit = ($row->record_status ?? 'draft') !== 'locked' && $row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN;
        $canDelete = ($row->record_status ?? 'draft') !== 'locked' && in_array($row->status, [MaterialPurchaseRequest::STATUS_DIAJUKAN, MaterialPurchaseRequest::STATUS_DITOLAK], true);
        $canApprove = ($row->record_status ?? 'draft') !== 'locked' && $row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN;
        $canProcess = ($row->record_status ?? 'draft') !== 'locked' && $row->status === MaterialPurchaseRequest::STATUS_DISETUJUI;

        return [
            'id' => $row->id,
            'kode_request' => $row->kode_request,
            'tanggal' => $row->tanggal?->format('Y-m-d'),
            'gudang' => $row->gudang?->nama_gudang ?? '-',
            'perumahan' => $row->gudang?->perumahan?->nama_perusahaan ?? '-',
            'gudang_id' => (string) $row->gudang_id,
            'status' => $row->status,
            'status_label' => match ($row->status) {
                MaterialPurchaseRequest::STATUS_DIAJUKAN => 'Menunggu Approval',
                MaterialPurchaseRequest::STATUS_DISETUJUI => 'Approved',
                MaterialPurchaseRequest::STATUS_DIPROSES => 'Diproses',
                MaterialPurchaseRequest::STATUS_DITOLAK => 'Ditolak',
                default => str($row->status)->replace('_', ' ')->title()->toString(),
            },
            'keterangan' => $row->keterangan ?? '-',
            'items_count' => $row->details_count ?? $row->details->count(),
            'requested_by_name' => $row->requestedBy?->name ?? $row->creator?->name ?? '-',
            'approved_by_name' => $row->approvedBy?->name ?? '-',
            'processed_by_name' => $row->processedBy?->name ?? '-',
            'created_by_name' => $row->creator?->name ?? '-',
            'updated_by_name' => $row->updater?->name ?? '-',
            'record_status' => $row->record_status ?? 'draft',
            'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_approve' => $canApprove,
            'can_process' => $canProcess,
            'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && auth()->check(),
            'can_unlock' => ($row->record_status ?? 'draft') === 'locked' && $this->currentUserCanManageLockedRecords(),
            'items' => $row->details->map(fn (MaterialPurchaseRequestDetail $detail) => [
                'id' => $detail->id,
                'kode_barang' => $detail->barangMaterial?->kode_barang ?? '-',
                'barang' => $detail->barangMaterial?->nama_barang ?? '-',
                'qty' => $detail->qty,
                'satuan' => $detail->satuan,
                'catatan' => $detail->catatan,
            ])->values(),
        ];
    }

    protected function editPayload(MaterialPurchaseRequest $row): array
    {
        return [
            'id' => $row->id,
            'kode_request' => $row->kode_request,
            'tanggal' => $row->tanggal?->format('Y-m-d'),
            'gudang_id' => (string) $row->gudang_id,
            'perumahan_id' => (string) ($row->gudang?->perumahan_id ?? ''),
            'keterangan' => $row->keterangan ?? '',
            'items' => $row->details->map(fn (MaterialPurchaseRequestDetail $detail) => [
                'barang_material_id' => (string) $detail->barang_material_id,
                'qty' => $detail->qty,
                'satuan' => $detail->satuan,
                'catatan' => $detail->catatan ?? '',
            ])->values(),
        ];
    }

    protected function options(): array
    {
        return [
            'gudangs' => Gudang::query()
                ->where('status', 'aktif')
                ->orderBy('nama_gudang')
                ->get(['id', 'nama_gudang'])
                ->map(fn (Gudang $row) => ['value' => (string) $row->id, 'label' => $row->nama_gudang])
                ->values(),
            'barangMaterials' => BarangMaterial::query()
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_hpp'])
                ->map(fn (BarangMaterial $row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->kode_barang} - {$row->nama_barang}",
                    'kode_barang' => $row->kode_barang,
                    'nama_barang' => $row->nama_barang,
                    'satuan' => $row->satuan,
                    'harga_hpp' => $row->harga_hpp,
                ])
                ->values(),
            'statuses' => [
                ['value' => '', 'label' => 'Semua Status'],
                ['value' => MaterialPurchaseRequest::STATUS_DIAJUKAN, 'label' => 'Diajukan'],
                ['value' => MaterialPurchaseRequest::STATUS_DISETUJUI, 'label' => 'Disetujui'],
                ['value' => MaterialPurchaseRequest::STATUS_DIPROSES, 'label' => 'Diproses'],
                ['value' => MaterialPurchaseRequest::STATUS_DITOLAK, 'label' => 'Ditolak'],
            ],
        ];
    }

    protected function permissions(): array
    {
        $user = auth()->user();

        return [
            'canCreate' => (bool) ($user?->can('material-purchase.create') || $user?->can('material-purchase.manage') || $user?->hasAnyRole(['user_area_gudang', 'admin_gudang'])),
            'canUpdate' => (bool) ($user?->can('material-purchase.update') || $user?->can('material-purchase.manage') || $user?->hasAnyRole(['user_area_gudang', 'admin_gudang'])),
            'canDelete' => (bool) ($user?->can('material-purchase.delete') || $user?->can('material-purchase.manage')),
            'canApprove' => (bool) $user?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'super_admin']),
            'canProcess' => (bool) ($user?->can('material-purchase.create') || $user?->can('material-purchase.manage') || $user?->hasAnyRole(['admin', 'keuangan', 'admin_keuangan', 'owner', 'super_admin'])),
            'canLock' => (bool) auth()->check(),
            'canUnlock' => (bool) $user?->can('material-purchase.unlock'),
        ];
    }

    protected function nextRequestCode(): string
    {
        $prefix = 'PPB-'.now()->format('Ym').'-';
        $lastCode = MaterialPurchaseRequest::withTrashed()
            ->where('kode_request', 'like', "{$prefix}%")
            ->orderByDesc('kode_request')
            ->value('kode_request');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected function canViewAllPurchaseRequests(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'admin', 'keuangan', 'admin_keuangan', 'super_admin'])
            || $user?->can('material-purchase.manage')
            || $user?->can('material-purchase.unlock'));
    }
}
