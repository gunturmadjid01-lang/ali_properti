<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseDetail;
use App\Models\MaterialPurchaseRequest;
use App\Models\MaterialRequest;
use App\Models\MasterBank;
use App\Models\Supplier;
use App\Services\MaterialPurchaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPurchaseController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $gudangId = trim((string) $request->query('gudang_id', ''));
        $days = trim((string) $request->query('days', ''));
        $sort = $request->query('sort', 'tanggal');
        $direction = $request->query('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return Inertia::render('Admin/MaterialPurchase/Index', [
            'title' => 'Daftar Pembelian Material',
            'baseUrl' => route('admin.material-purchase.index', absolute: false),
            'createUrl' => route('admin.material-purchase.create', absolute: false),
            'permissions' => [
                'canCreate' => $this->canCreatePurchase(),
                'canUpdate' => $this->canUpdatePurchase(),
                'canApprove' => (bool) auth()->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']),
                'canRelease' => (bool) auth()->user()?->hasAnyRole(['keuangan', 'admin_keuangan', 'owner', 'super_admin']),
                'canMarkPurchased' => (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'admin', 'super_admin']),
                'canLock' => (bool) auth()->check(),
                'canUnlock' => (bool) auth()->user()?->can('material-purchase.unlock'),
            ],
            'rows' => MaterialPurchase::query()
                ->with([
                'gudang:id,nama_gudang',
                'details.barangMaterial:id,kode_barang,nama_barang',
                'materialRequest:id,kode_request',
                'materialPurchaseRequest.gudang:id,nama_gudang,perumahan_id',
                'materialPurchaseRequest.gudang.perumahan:id,nama_perusahaan',
                'plannedMasterBank:id,nama_bank,nomor_rekening',
                'paymentMasterBank:id,nama_bank,nomor_rekening',
                'supplierData:id,kode_supplier,nama_supplier',
                    'creator:id,name',
                    'updater:id,name',
                    'approvedBy:id,name',
                    'fundReleasedBy:id,name',
                    'receivedBy:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_pembelian', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                }))
                ->when($gudangId !== '', fn (Builder $query) => $query->where('gudang_id', $gudangId))
                ->when($days !== '', fn (Builder $query) => $query->whereDate('tanggal', '>=', now()->subDays((int) $days)->toDateString()))
                ->when(! $this->canViewAllPurchases(), function (Builder $query) {
                    $query->where(function (Builder $query) {
                        $query->where('record_status', 'locked')
                            ->orWhere('created_by', auth()->id());
                    });
                })
                ->orderBy(in_array($sort, ['tanggal', 'kode_pembelian', 'total_nominal'], true) ? $sort : 'tanggal', $direction)
                ->orderBy('id', $direction)
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialPurchase $row) => [
                    'id' => $row->id,
                    'kode_pembelian' => $row->kode_pembelian,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'tanggal_barang_masuk' => $row->tanggal_barang_masuk?->format('Y-m-d') ?? '-',
                    'request' => $row->materialRequest?->kode_request ?? '-',
                    'purchase_request' => $row->materialPurchaseRequest
                        ? $row->materialPurchaseRequest->kode_request
                            .($row->materialPurchaseRequest->gudang?->nama_gudang ? ' - '.$row->materialPurchaseRequest->gudang->nama_gudang : '')
                            .($row->materialPurchaseRequest->gudang?->perumahan?->nama_perusahaan ? ' / '.$row->materialPurchaseRequest->gudang->perumahan->nama_perusahaan : '')
                        : '-',
                    'gudang' => $row->gudang?->nama_gudang ?? 'Gudang Umum',
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? $row->materialPurchaseRequest?->gudang?->perumahan?->nama_perusahaan ?? '-',
                    'supplier_code' => $row->supplierData?->kode_supplier ?? '-',
                    'supplier_id' => (string) ($row->supplier_id ?? ''),
                    'supplier' => $row->supplierData?->nama_supplier ?? $row->supplier ?? '-',
                    'metode_pembayaran' => $row->metode_pembayaran ?? 'tunai',
                    'planned_master_bank_id' => (string) ($row->planned_master_bank_id ?? ''),
                    'subtotal_nominal' => (float) ($row->subtotal_nominal ?? $row->total_nominal),
                    'diskon_transaksi' => (float) ($row->diskon_transaksi ?? 0),
                    'can_approve' => ($row->record_status ?? 'draft') !== 'locked' && in_array($row->status, [MaterialPurchase::STATUS_MENUNGGU_APPROVAL, MaterialPurchase::STATUS_MENUNGGU_MANAGER], true),
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked' && $row->details->every(fn ($detail) => ($detail->inspection_status ?? 'pending') === 'pending' && (float) ($detail->qty_diterima ?? 0) <= 0),
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && auth()->check(),
                    'can_unlock' => ($row->record_status ?? 'draft') === 'locked' && $this->currentUserCanManageLockedRecords(),
                    'planned_bank' => $row->plannedMasterBank
                        ? "{$row->plannedMasterBank->nama_bank} - {$row->plannedMasterBank->nomor_rekening}"
                        : '-',
                    'payment_bank' => $row->paymentMasterBank
                        ? "{$row->paymentMasterBank->nama_bank} - {$row->paymentMasterBank->nomor_rekening}"
                        : '-',
                    'total_nominal' => $row->total_nominal,
                    'status' => $row->status,
                    'status_label' => $this->purchaseStatusLabel($row->status),
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'approved_by_name' => $row->approvedBy?->name ?? '-',
                    'fund_released_by_name' => $row->fundReleasedBy?->name ?? '-',
                    'received_by_name' => $row->receivedBy?->name ?? '-',
                    'items' => $row->details->map(fn ($detail) => [
                        'id' => $detail->id,
                        'kode_barang' => $detail->barangMaterial?->kode_barang ?? '-',
                        'barang' => $detail->barangMaterial?->nama_barang ?? '-',
                        'qty' => $detail->qty,
                        'qty_diterima' => $detail->qty_diterima,
                        'satuan' => $detail->satuan,
                        'harga_satuan' => $detail->harga_satuan,
                        'diskon' => (float) ($detail->diskon ?? 0),
                        'subtotal' => $detail->subtotal,
                        'inspection_status' => $detail->inspection_status,
                        'inspection_note' => $detail->inspection_note,
                    ])->values(),
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => ['search' => $search, 'gudang_id' => $gudangId, 'days' => $days, 'sort' => $sort, 'direction' => $direction],
            'options' => $this->options(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->canCreatePurchase(), 403, 'Anda tidak memiliki permission membuat pembelian material.');
        $selectedRequest = $this->selectedPurchaseRequest($request->query('purchase_request_id'));

        return Inertia::render('Admin/MaterialPurchase/Create', [
            'title' => 'Transaksi Pembelian Material',
            'baseUrl' => route('admin.material-purchase.index', absolute: false),
            'indexUrl' => route('admin.material-purchase.index', absolute: false),
            'nextCode' => $this->nextPurchaseCode(),
            'purchase' => null,
            'selectedRequest' => $selectedRequest,
            'options' => $this->options(),
        ]);
    }

    public function edit(string $id): Response
    {
        abort_unless($this->canUpdatePurchase(), 403, 'Anda tidak memiliki permission mengubah pembelian material.');

        $purchase = MaterialPurchase::query()->with(['details.barangMaterial', 'materialPurchaseRequest.gudang'])->findOrFail($id);
        $this->abortIfLocked($purchase);

        if ($purchase->details->contains(fn (MaterialPurchaseDetail $detail) => ($detail->inspection_status ?? 'pending') !== 'pending' || (float) $detail->qty_diterima > 0)) {
            abort(422, 'Pembelian yang sudah masuk pengecekan barang tidak dapat diedit.');
        }

        return Inertia::render('Admin/MaterialPurchase/Create', [
            'title' => 'Edit Transaksi Pembelian Material',
            'baseUrl' => route('admin.material-purchase.update', $purchase->id, false),
            'indexUrl' => route('admin.material-purchase.index', absolute: false),
            'nextCode' => $purchase->kode_pembelian,
            'purchase' => $this->editPayload($purchase),
            'selectedRequest' => null,
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request, MaterialPurchaseService $service): RedirectResponse
    {
        $validated = $this->validated($request);
        $purchaseRequest = $this->approvedPurchaseRequest($validated['material_purchase_request_id'] ?? null);

        $service->createPurchase($validated, purchaseRequest: $purchaseRequest);

        return redirect()->route('admin.material-purchase.index')->with('success', 'Pembelian barang berhasil dibuat dan menunggu pengecekan gudang.');
    }

    public function update(Request $request, string $id, MaterialPurchaseService $service): RedirectResponse
    {
        abort_unless($this->canUpdatePurchase(), 403, 'Anda tidak memiliki permission mengubah pembelian material.');

        $row = MaterialPurchase::query()->with('details')->findOrFail($id);
        $this->abortIfLocked($row);
        $validated = $this->validated($request, $row->id);
        unset($validated['material_purchase_request_id']);

        $service->updatePurchase($row, $validated);

        return redirect()->route('admin.material-purchase.index')->with('success', 'Pembelian barang berhasil diperbarui.');
    }

    public function fromRequest(string $id, Request $request, MaterialPurchaseService $service): RedirectResponse
    {
        $materialRequest = MaterialRequest::query()->with('details.barangMaterial')->findOrFail($id);
        $items = $materialRequest->details->map(fn ($detail) => [
            'barang_material_id' => $detail->barang_material_id,
            'qty' => $detail->qty,
            'satuan' => $detail->satuan,
            'harga_satuan' => $detail->barangMaterial?->harga_hpp ?? 0,
        ])->all();

        $service->createPurchase([
            'tanggal' => now()->toDateString(),
            'supplier_id' => $request->input('supplier_id'),
            'supplier' => $request->input('supplier'),
            'metode_pembayaran' => $request->input('metode_pembayaran', 'tunai'),
            'keterangan' => $request->input('keterangan'),
            'items' => $items,
        ], $materialRequest);

        return back()->with('success', 'Pembelian dari permintaan barang berhasil dibuat.');
    }

    public function approve(string $id, MaterialPurchaseService $service): RedirectResponse
    {
        $row = MaterialPurchase::query()->findOrFail($id);
        abort_unless($this->canApprovePurchase(), 403, 'Anda tidak memiliki permission approval pembelian material.');
        $this->abortIfLocked($row);
        $service->approve($row);

        return back()->with('success', 'Pembelian berhasil di-approve dan menunggu pembayaran.');
    }

    public function releaseFund(Request $request, string $id, MaterialPurchaseService $service): RedirectResponse
    {
        $validated = $request->validate([
            'payment_master_bank_id' => ['required', 'exists:master_banks,id'],
        ]);

        $row = MaterialPurchase::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $service->releaseFund($row, (int) $validated['payment_master_bank_id']);

        return back()->with('success', 'Dana pembelian berhasil dicairkan.');
    }

    public function markPurchased(string $id, MaterialPurchaseService $service): RedirectResponse
    {
        $row = MaterialPurchase::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $service->markPurchased($row);

        return back()->with('success', 'Pembelian ditandai sudah dibeli.');
    }

    public function receive(Request $request, string $id, MaterialPurchaseService $service): RedirectResponse
    {
        $validated = $request->validate([
            'receive_note' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:material_purchase_details,id'],
            'items.*.qty_diterima' => ['required', 'numeric', 'min:0'],
        ]);

        $row = MaterialPurchase::query()->with('details', 'materialRequest')->findOrFail($id);
        $this->abortIfLocked($row);
        $service->receive($row, $validated);

        return back()->with('success', 'Barang masuk berhasil dicek dan stok diperbarui.');
    }

    public function inspectionIndex(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $gudangId = $request->query('gudang_id');

        return Inertia::render('Admin/MaterialPurchase/Inspection', [
            'title' => 'Pemeriksaan Barang Masuk',
            'baseUrl' => route('admin.material-purchase.inspection.index', absolute: false),
            'rows' => MaterialPurchase::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'details.barangMaterial:id,nama_barang',
                    'creator:id,name',
                    'updater:id,name',
                    'receivedBy:id,name',
                ])
                ->whereIn('status', [
                    MaterialPurchase::STATUS_APPROVED,
                    MaterialPurchase::STATUS_DIBELI,
                    MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
                    'menunggu_pemeriksaan_gudang',
                    MaterialPurchase::STATUS_DITERIMA,
                    MaterialPurchase::STATUS_DITERIMA_SEBAGIAN,
                    MaterialPurchase::STATUS_DITOLAK_GUDANG,
                    MaterialPurchase::STATUS_PENGECEKAN_SELESAI,
                ])
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_pembelian', 'like', "%{$search}%")
                        ->orWhere('supplier', 'like', "%{$search}%");
                }))
                ->when($dateFrom, fn (Builder $query) => $query->whereDate('tanggal', '>=', $dateFrom))
                ->when($dateTo, fn (Builder $query) => $query->whereDate('tanggal', '<=', $dateTo))
                ->when($status, fn (Builder $query) => $query->where('status', $status))
                ->when($gudangId, fn (Builder $query) => $query->where('gudang_id', $gudangId))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialPurchase $row) => [
                    'id' => $row->id,
                    'kode_pembelian' => $row->kode_pembelian,
                    'tanggal' => $row->tanggal?->format('Y-m-d'),
                    'tanggal_barang_masuk' => $row->tanggal_barang_masuk?->format('Y-m-d') ?? '',
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'supplier' => $row->supplier ?? '-',
                    'status' => $row->status,
                    'status_label' => $this->purchaseStatusLabel($row->status),
                    'items_count' => $row->details->count(),
                    'pending_count' => $row->details->where('inspection_status', 'pending')->count(),
                    'accepted_count' => $row->details->where('inspection_status', 'sesuai')->count(),
                    'rejected_count' => $row->details->where('inspection_status', 'tidak_sesuai')->count(),
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'received_by_name' => $row->receivedBy?->name ?? '-',
                    'items' => $row->details->map(fn (MaterialPurchaseDetail $detail) => [
                        'id' => $detail->id,
                        'barang' => $detail->barangMaterial?->nama_barang ?? '-',
                        'qty' => $detail->qty,
                        'qty_diterima' => $detail->qty_diterima,
                        'satuan' => $detail->satuan,
                        'inspection_status' => $detail->inspection_status,
                        'inspection_note' => $detail->inspection_note,
                        'checked_at' => $detail->checked_at?->format('Y-m-d H:i'),
                    ])->values(),
                ]),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
                'gudang_id' => $gudangId,
            ],
            'options' => [
                'gudangs' => Gudang::query()
                    ->where('status', 'aktif')
                    ->orderBy('nama_gudang')
                    ->get(['id', 'nama_gudang'])
                    ->map(fn (Gudang $gudang) => ['value' => (string) $gudang->id, 'label' => $gudang->nama_gudang])
                    ->values(),
                'statuses' => [
                    ['value' => '', 'label' => 'Semua Status'],
                    ['value' => MaterialPurchase::STATUS_APPROVED, 'label' => 'Approved'],
                    ['value' => MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN, 'label' => 'Menunggu Pengecekan'],
                    ['value' => MaterialPurchase::STATUS_PENGECEKAN_SELESAI, 'label' => 'Pengecekan Selesai'],
                ],
            ],
        ]);
    }

    public function inspectItem(Request $request, string $id, string $detailId, MaterialPurchaseService $service): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:sesuai,tidak_sesuai'],
            'qty_diterima' => ['nullable', 'numeric', 'min:0'],
            'tanggal_barang_masuk' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);

        $purchase = MaterialPurchase::query()->findOrFail($id);
        $detail = $purchase->details()->findOrFail($detailId);
        $service->inspectItem($purchase, $detail, $validated);

        return back()->with(
            'success',
            $validated['status'] === 'sesuai'
                ? 'Item dinyatakan sesuai dan stok gudang telah ditambahkan.'
                : 'Item dinyatakan tidak sesuai dan tidak ditambahkan ke stok.'
        );
    }

    protected function modelClass(): string
    {
        return MaterialPurchase::class;
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'kode_pembelian' => ['nullable', 'string', 'max:255', 'unique:material_purchases,kode_pembelian'.($ignoreId ? ','.$ignoreId : '')],
            'material_purchase_request_id' => ['nullable', 'exists:material_purchase_requests,id'],
            'tanggal' => ['required', 'date'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'metode_pembayaran' => ['required', 'in:tunai,hutang'],
            'planned_master_bank_id' => ['nullable', 'required_if:metode_pembayaran,tunai', 'exists:master_banks,id'],
            'keterangan' => ['nullable', 'string'],
            'diskon_transaksi' => ['nullable', 'numeric', 'min:0'],
            'update_material_prices' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function options(): array
    {
        return [
            'gudangs' => Gudang::query()->where('status', 'aktif')->orderBy('nama_gudang')->get(['id', 'nama_gudang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_gudang])->values(),
            'barangMaterials' => BarangMaterial::query()
                ->orderBy('kode_barang')
                ->get(['id', 'kode_barang', 'nama_barang', 'satuan', 'harga_hpp'])
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->kode_barang} - {$row->nama_barang}",
                    'kode_barang' => $row->kode_barang,
                    'nama_barang' => $row->nama_barang,
                    'satuan' => $row->satuan,
                    'harga_hpp' => $row->harga_hpp,
                ])
                ->values(),
            'suppliers' => Supplier::query()
                ->where('status', 'aktif')
                ->orderBy('nama_supplier')
                ->get(['id', 'kode_supplier', 'nama_supplier', 'phone'])
                ->map(fn (Supplier $row) => [
                    'value' => (string) $row->id,
                    'label' => trim("{$row->kode_supplier} - {$row->nama_supplier}".($row->phone ? " ({$row->phone})" : '')),
                ])
                ->values(),
            'purchaseRequests' => MaterialPurchaseRequest::query()
                ->with(['gudang:id,nama_gudang,perumahan_id', 'gudang.perumahan:id,nama_perusahaan'])
                ->where('status', MaterialPurchaseRequest::STATUS_DISETUJUI)
                ->orderByDesc('tanggal')
                ->orderByDesc('id')
                ->get(['id', 'kode_request', 'tanggal', 'gudang_id'])
                ->map(fn (MaterialPurchaseRequest $row) => [
                    'value' => (string) $row->id,
                    'label' => trim("{$row->kode_request} - ".($row->gudang?->nama_gudang ?? 'Gudang').($row->gudang?->perumahan?->nama_perusahaan ? ' / '.$row->gudang->perumahan->nama_perusahaan : '')),
                ])
                ->values(),
            'metodePembayaran' => [
                ['value' => 'tunai', 'label' => 'Tunai / Cash'],
                ['value' => 'hutang', 'label' => 'Hutang Supplier'],
            ],
            'masterBanks' => MasterBank::query()
                ->where('status', 'aktif')
                ->orderBy('nama_bank')
                ->get(['id', 'nama_bank', 'nomor_rekening', 'nama_rekening'])
                ->map(fn ($bank) => [
                    'value' => (string) $bank->id,
                    'label' => trim("{$bank->nama_bank} - {$bank->nomor_rekening} - {$bank->nama_rekening}"),
                ])
                ->values(),
        ];
    }

    protected function approvedPurchaseRequest(null|string|int $id): ?MaterialPurchaseRequest
    {
        if (! filled($id)) {
            return null;
        }

        $row = MaterialPurchaseRequest::query()->with(['details.barangMaterial', 'gudang'])->findOrFail($id);
        abort_unless($row->status === MaterialPurchaseRequest::STATUS_DISETUJUI, 422, 'Permintaan pembelian belum disetujui atau sudah diproses.');

        return $row;
    }

    protected function selectedPurchaseRequest(null|string|int $id): ?array
    {
        $row = $this->approvedPurchaseRequest($id);

        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'kode_request' => $row->kode_request,
            'tanggal' => $row->tanggal?->format('Y-m-d'),
            'gudang_id' => (string) $row->gudang_id,
            'perumahan_id' => (string) ($row->gudang?->perumahan_id ?? ''),
            'keterangan' => $row->keterangan ?? '',
            'items' => $row->details->map(fn ($detail) => [
                'barang_material_id' => (string) $detail->barang_material_id,
                'qty' => $detail->qty,
                'satuan' => $detail->satuan ?? $detail->barangMaterial?->satuan,
                'harga_satuan' => $detail->barangMaterial?->harga_hpp ?? 0,
            ])->values(),
        ];
    }

    protected function editPayload(MaterialPurchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'kode_pembelian' => $purchase->kode_pembelian,
            'tanggal' => $purchase->tanggal?->format('Y-m-d'),
            'supplier_id' => (string) ($purchase->supplier_id ?? ''),
            'supplier' => $purchase->supplier ?? '',
            'metode_pembayaran' => $purchase->metode_pembayaran ?? 'hutang',
            'planned_master_bank_id' => (string) ($purchase->planned_master_bank_id ?? ''),
            'gudang_id' => (string) ($purchase->gudang_id ?? ''),
            'keterangan' => $purchase->keterangan ?? '',
            'diskon_transaksi' => (float) ($purchase->diskon_transaksi ?? 0),
            'material_purchase_request_id' => (string) ($purchase->material_purchase_request_id ?? ''),
            'items' => $purchase->details->map(fn (MaterialPurchaseDetail $detail) => [
                'barang_material_id' => (string) $detail->barang_material_id,
                'qty' => $detail->qty,
                'satuan' => $detail->satuan,
                'harga_satuan' => $detail->harga_satuan,
                'diskon' => (float) ($detail->diskon ?? 0),
            ])->values(),
        ];
    }

    protected function nextPurchaseCode(): string
    {
        $prefix = 'PB-'.now()->format('Ym').'-';
        $lastCode = MaterialPurchase::withTrashed()
            ->where('kode_pembelian', 'like', "{$prefix}%")
            ->orderByDesc('kode_pembelian')
            ->value('kode_pembelian');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected function purchaseStatusLabel(?string $status): string
    {
        return match ($status) {
            MaterialPurchase::STATUS_MENUNGGU_APPROVAL, MaterialPurchase::STATUS_MENUNGGU_MANAGER => 'Menunggu Approval',
            MaterialPurchase::STATUS_APPROVED, MaterialPurchase::STATUS_MENUNGGU_DANA, MaterialPurchase::STATUS_DANA_CAIR, MaterialPurchase::STATUS_DIBELI => 'Approved',
            MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN, 'menunggu_pemeriksaan_gudang' => 'Menunggu Pengecekan',
            MaterialPurchase::STATUS_PENGECEKAN_SELESAI, MaterialPurchase::STATUS_DITERIMA, MaterialPurchase::STATUS_DITERIMA_SEBAGIAN, MaterialPurchase::STATUS_DITOLAK_GUDANG => 'Pengecekan Selesai',
            MaterialPurchase::STATUS_DITOLAK => 'Ditolak',
            default => $status ? str($status)->replace('_', ' ')->title()->toString() : '-',
        };
    }

    protected function canCreatePurchase(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('material-purchase.create')
            || $user?->can('material-purchase.manage')
            || $user?->hasAnyRole(['admin', 'keuangan', 'admin_keuangan', 'owner', 'super_admin']));
    }

    protected function canUpdatePurchase(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('material-purchase.update')
            || $user?->can('material-purchase.manage')
            || $user?->hasAnyRole(['admin', 'keuangan', 'admin_keuangan', 'super_admin']));
    }

    protected function canApprovePurchase(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'super_admin']);
    }

    protected function canViewAllPurchases(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'admin', 'keuangan', 'admin_keuangan', 'super_admin'])
            || $user?->can('material-purchase.manage')
            || $user?->can('material-purchase.unlock'));
    }
}
