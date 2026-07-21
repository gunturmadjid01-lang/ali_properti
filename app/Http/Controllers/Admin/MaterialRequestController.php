<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\DetailRumah;
use App\Models\Gudang;
use App\Models\MaterialRequest;
use App\Models\Perumahan;
use App\Services\AppNotificationService;
use App\Services\MaterialRequestTemplateService;
use App\Services\MaterialWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialRequestController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function templates(Request $request, MaterialRequestTemplateService $templates): JsonResponse
    {
        abort_unless($request->user()?->can('material-request.create') || $request->user()?->can('material-request.view'), 403);

        $validated = $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
        ]);

        return response()->json([
            'data' => $templates->build(
                $validated['perumahan_id'],
                $validated['detail_rumah_id'] ?? null,
                $validated['tahapan_pembangunan_id'] ?? null,
            ),
        ]);
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/MaterialRequest/Index', [
            'title' => 'Permintaan Barang',
            'baseUrl' => route('admin.material-request.index', absolute: false),
            'createUrl' => route('admin.material-request.create', absolute: false),
            'permissions' => [
                'canCreate' => $this->canCreateRequest(),
                'canUpdate' => $this->canUpdateRequest(),
                'canDelete' => $this->canDeleteRequest(),
                'canApproveGudang' => $this->canApproveGudang(),
                'canApproveOwner' => $this->canApproveOwner(),
                'canLock' => $this->canLockRequest(),
                'canUnlock' => $this->canManageLock(),
                'canIssue' => (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'owner', 'super_admin']),
            ],
            'rows' => MaterialRequest::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'details.barangMaterial:id,nama_barang',
                    'creator:id,name',
                    'updater:id,name',
                    'requestedBy:id,name',
                    'approvedByGudang:id,name',
                    'approvedByOwner:id,name',
                    'issuedBy:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query->where('kode_request', 'like', "%{$search}%")->orWhere('status', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialRequest $row) => [
                    'id' => $row->id,
                    'kode_request' => $row->kode_request,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'gudang_id' => (string) ($row->gudang_id ?? ''),
                    'perumahan_id' => (string) ($row->perumahan_id ?? ''),
                    'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                    'gudang' => $row->gudang?->nama_gudang ?? 'Gudang Umum',
                    'unit' => $row->detailRumah ? "{$row->detailRumah->kode_nlok} {$row->detailRumah->nomor_rumah}" : '-',
                    'status' => $row->status,
                    'created_by_name' => $row->creator?->name ?? $row->requestedBy?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'approved_by_gudang' => $row->approvedByGudang?->name ?? '-',
                    'approved_at_gudang' => optional($row->approved_at_gudang)->format('Y-m-d H:i'),
                    'approved_by_owner' => $row->approvedByOwner?->name ?? '-',
                    'approved_at_owner' => optional($row->approved_at_owner)->format('Y-m-d H:i'),
                    'issued_at' => optional($row->issued_at)->format('Y-m-d H:i'),
                    'issued_by_name' => $row->issuedBy?->name ?? '-',
                    'items_text' => $row->details->map(fn ($detail) => $detail->barangMaterial?->nama_barang.' '.$detail->qty.' '.$detail->satuan)->join(', '),
                    'items' => $row->details->map(fn ($detail) => [
                        'barang_material_id' => (string) $detail->barang_material_id,
                        'qty' => $detail->qty,
                        'satuan' => $detail->satuan,
                        'catatan' => $detail->catatan,
                    ])->values(),
                    'keterangan' => $row->keterangan,
                    'can_approve_gudang' => ($row->record_status ?? 'draft') === 'locked' && $this->canApproveGudang() && ($row->approved_at_gudang === null),
                    'can_approve_owner' => ($row->record_status ?? 'draft') === 'locked' && $this->canApproveOwner() && ($row->approved_at_owner === null),
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && $this->canLockRequest(),
                    'can_unlock' => $this->canManageLock(),
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked' && ! $row->issued_at && $this->canUpdateRequest(),
                    'can_delete' => ($row->record_status ?? 'draft') !== 'locked' && ! $row->issued_at && $this->canDeleteRequest(),
                    'can_issue' => $this->canIssueMaterial($row),
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => ['search' => $search],
            'options' => $this->options(),
            'canCreate' => $this->canCreateRequest(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/MaterialRequest/Create', $this->formProps($request));
    }

    public function edit(Request $request, string $id): Response
    {
        $materialRequest = MaterialRequest::query()
            ->with(['details', 'gudang:id,nama_gudang', 'perumahan:id,nama_perusahaan', 'detailRumah:id,perumahan_id,kode_nlok,nomor_rumah'])
            ->findOrFail($id);

        return Inertia::render('Admin/MaterialRequest/Create', $this->formProps($request, $materialRequest));
    }

    public function store(Request $request, AppNotificationService $notifications): RedirectResponse
    {
        abort_unless($this->canCreateRequest(), 403, 'Hanya pengawas yang dapat membuat permintaan barang.');
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $notifications) {
            $detailRumah = ! empty($validated['detail_rumah_id']) ? DetailRumah::query()->find($validated['detail_rumah_id']) : null;
            $request = MaterialRequest::query()->create([
                ...collect($validated)->except('items')->toArray(),
                'kode_request' => 'REQ-'.now()->format('YmdHisv').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'perumahan_id' => $detailRumah?->perumahan_id ?? $validated['perumahan_id'] ?? null,
                'requested_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $barang = BarangMaterial::query()->findOrFail($item['barang_material_id']);
                $request->details()->create([
                    ...$item,
                    'satuan' => $item['satuan'] ?? $barang->satuan,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $notifications->toRoles(
                ['user_area_gudang', 'keuangan', 'admin_keuangan'],
                'Permintaan barang baru',
                "Permintaan {$request->kode_request} menunggu tindak lanjut.",
                '/admin/permintaan-barang'
            );
        });

        return back()->with('success', 'Permintaan barang berhasil dikirim.');
    }

    public function approve(string $id, MaterialWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canApproveGudang(), 403, 'Hanya gudang yang dapat menyetujui permintaan barang.');

        $request = MaterialRequest::query()->findOrFail($id);
        abort_unless(($request->record_status ?? 'draft') === 'locked', 422, 'Permintaan barang harus di-lock terlebih dahulu.');
        $result = $workflow->approveGudang($request);

        if ($result->approved_at_owner && ! $result->issued_at) {
            return back()->with('error', 'Persetujuan gudang tersimpan, tetapi stok gudang belum cukup sehingga barang belum dapat dikeluarkan.');
        }

        return back()->with('success', $result->issued_at
            ? 'Persetujuan lengkap. Stok gudang berhasil dikeluarkan.'
            : 'Persetujuan gudang tersimpan dan menunggu approval owner.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless($this->canUpdateRequest(), 403, 'Hanya pengawas yang dapat mengubah permintaan barang.');
        $materialRequest = MaterialRequest::query()->with('details')->findOrFail($id);
        $this->abortIfLocked($materialRequest);
        abort_if($materialRequest->issued_at, 422, 'Permintaan yang sudah dikeluarkan dari gudang tidak dapat diedit.');

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $materialRequest) {
            $detailRumah = ! empty($validated['detail_rumah_id']) ? DetailRumah::query()->find($validated['detail_rumah_id']) : null;
            $materialRequest->update([
                ...collect($validated)->except('items')->toArray(),
                'perumahan_id' => $detailRumah?->perumahan_id ?? $validated['perumahan_id'] ?? null,
                'status' => MaterialRequest::STATUS_DIAJUKAN,
                'approved_by_gudang' => null,
                'approved_at_gudang' => null,
                'approval_note' => null,
                'approved_by_owner' => null,
                'approved_at_owner' => null,
                'owner_approval_note' => null,
                'updated_by' => auth()->id(),
            ]);
            $materialRequest->details()->delete();
            foreach ($validated['items'] as $item) {
                $barang = BarangMaterial::query()->findOrFail($item['barang_material_id']);
                $materialRequest->details()->create([
                    ...$item,
                    'satuan' => $item['satuan'] ?? $barang->satuan,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return back()->with('success', 'Permintaan barang berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless($this->canDeleteRequest(), 403, 'Hanya pengawas yang dapat menghapus permintaan barang.');
        $materialRequest = MaterialRequest::query()->findOrFail($id);
        $this->abortIfLocked($materialRequest);
        abort_if($materialRequest->issued_at, 422, 'Permintaan yang sudah dikeluarkan dari gudang tidak dapat dihapus.');
        $materialRequest->delete();

        return back()->with('success', 'Permintaan barang berhasil dihapus.');
    }

    public function approveOwner(string $id, MaterialWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canApproveOwner(), 403, 'Hanya owner yang dapat memberi persetujuan akhir.');

        $request = MaterialRequest::query()->findOrFail($id);
        abort_unless(($request->record_status ?? 'draft') === 'locked', 422, 'Permintaan barang harus di-lock terlebih dahulu.');
        $result = $workflow->approveOwner($request);

        if ($result->approved_at_gudang && ! $result->issued_at) {
            return back()->with('error', 'Approval owner berhasil disimpan, tetapi stok pada gudang tujuan belum cukup. Isi stok gudang terlebih dahulu.');
        }

        return back()->with('success', $result->issued_at
            ? 'Approval owner berhasil. Stok gudang langsung dikeluarkan.'
            : 'Approval owner tersimpan dan menunggu approval gudang.');
    }

    public function issue(string $id, MaterialWorkflowService $workflow): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['user_area_gudang', 'owner', 'super_admin']),
            403,
            'Hanya gudang atau owner yang dapat mengirim barang.'
        );

        $request = MaterialRequest::query()->findOrFail($id);
        $result = $workflow->issueApprovedRequest($request);

        return back()->with(
            $result->issued_at ? 'success' : 'error',
            $result->issued_at
                ? 'Barang berhasil dikirim. Stok gudang berkurang dan stok lokasi/unit bertambah.'
                : 'Stok gudang masih belum cukup, barang belum dapat dikirim.'
        );
    }

    public function lock(string $id): RedirectResponse
    {
        abort_unless(auth()->check(), 403, 'Silakan login untuk mengunci permintaan.');

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->canManageLock(), 403, 'Hanya user yang diberi akses yang dapat membuka lock permintaan.');

        return $this->traitUnlock($id);
    }

    protected function modelClass(): string
    {
        return MaterialRequest::class;
    }

    protected function options(): array
    {
        return [
            'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'gudangs' => Gudang::query()->where('status', 'aktif')->orderBy('nama_gudang')->get(['id', 'nama_gudang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_gudang])->values(),
            'detailRumahs' => DetailRumah::query()->finalized()->with('perumahan:id,nama_perusahaan')->orderBy('kode_nlok')->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->perumahan?->nama_perusahaan} - {$row->kode_nlok} {$row->nomor_rumah}", 'perumahan_id' => (string) $row->perumahan_id])->values(),
            'barangMaterials' => BarangMaterial::query()->where('status', 'aktif')->orderBy('nama_barang')->get(['id', 'kode_barang', 'nama_barang', 'satuan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->kode_barang} - {$row->nama_barang}", 'satuan' => $row->satuan])->values(),
        ];
    }

    protected function formProps(Request $request, ?MaterialRequest $row = null): array
    {
        return [
            'title' => $row ? 'Edit Permintaan Barang' : 'Tambah Permintaan Barang',
            'baseUrl' => $row ? route('admin.material-request.update', $row->id, absolute: false) : route('admin.material-request.store', absolute: false),
            'indexUrl' => route('admin.material-request.index', absolute: false),
            'nextCode' => $row?->kode_request ?? ('REQ-'.now()->format('YmdHisv').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT)),
            'request' => $row ? [
                'id' => (string) $row->id,
                'kode_request' => $row->kode_request,
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'gudang_id' => (string) ($row->gudang_id ?? ''),
                'perumahan_id' => (string) ($row->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                'keterangan' => $row->keterangan ?? '',
                'items' => $row->details->map(fn ($detail) => [
                    'barang_material_id' => (string) $detail->barang_material_id,
                    'kode_barang' => $detail->barangMaterial?->kode_barang ?? '',
                    'nama_barang' => $detail->barangMaterial?->nama_barang ?? '',
                    'satuan' => $detail->satuan ?? $detail->barangMaterial?->satuan ?? '',
                    'qty' => $detail->qty,
                    'catatan' => $detail->catatan ?? '',
                ])->values(),
            ] : null,
            'options' => $this->options(),
            'canCreate' => $this->canCreateRequest(),
        ];
    }

    protected function canCreateRequest(): bool
    {
        return (bool) auth()->user()?->can('material-request.create');
    }

    protected function canUpdateRequest(): bool
    {
        return (bool) auth()->user()?->can('material-request.update');
    }

    protected function canDeleteRequest(): bool
    {
        return (bool) auth()->user()?->can('material-request.delete');
    }

    protected function canLockRequest(): bool
    {
        return (bool) auth()->check();
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string'],
            'items.*.catatan' => ['nullable', 'string'],
        ]);
    }

    protected function canApproveGudang(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'owner', 'super_admin']);
    }

    protected function canApproveOwner(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']);
    }

    protected function canManageLock(): bool
    {
        $user = auth()->user();

        return (bool) $user && (
            $user->hasAnyRole(['super_admin'])
            || $user->can('material-request.unlock')
            || $user->can('material-request.manage')
        );
    }

    protected function canIssueMaterial(MaterialRequest $row): bool
    {
        if (! auth()->user()?->hasAnyRole(['user_area_gudang', 'owner', 'super_admin'])) {
            return false;
        }

        return (bool) $row->approved_at_gudang
            && (bool) $row->approved_at_owner
            && ! $row->issued_at
            && in_array($row->status, [
                MaterialRequest::STATUS_DIPROSES,
                MaterialRequest::STATUS_MENUNGGU_STOK,
            ], true);
    }
}
