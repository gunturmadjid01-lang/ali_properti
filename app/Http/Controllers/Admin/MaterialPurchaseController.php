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
        $selectedRequest = MaterialPurchaseRequest::query()
            ->with(['gudang:id,nama_gudang', 'details.barangMaterial:id,nama_barang,satuan,harga_hpp'])
            ->where('status', MaterialPurchaseRequest::STATUS_DIAJUKAN)
            ->find($request->query('request_id'));

        return Inertia::render('Admin/MaterialPurchase/Index', [
            'title' => 'Pembelian Barang',
            'baseUrl' => route('admin.material-purchase.index', absolute: false),
            'permissions' => [
                'canCreate' => (bool) auth()->user()?->can('material-purchase.create') || auth()->user()?->can('material-purchase.manage'),
                'canApprove' => (bool) auth()->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']),
                'canRelease' => (bool) auth()->user()?->hasAnyRole(['keuangan', 'admin_keuangan', 'owner', 'super_admin']),
                'canMarkPurchased' => (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'admin', 'super_admin']),
                'canReceive' => (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'admin', 'super_admin']),
                'canLock' => (bool) auth()->check(),
                'canUnlock' => (bool) auth()->user()?->can('material-purchase.unlock'),
            ],
            'rows' => MaterialPurchase::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'details.barangMaterial:id,nama_barang',
                    'materialRequest:id,kode_request',
                    'materialPurchaseRequest:id,kode_request',
                    'plannedMasterBank:id,nama_bank,nomor_rekening',
                    'paymentMasterBank:id,nama_bank,nomor_rekening',
                    'creator:id,name',
                    'updater:id,name',
                    'approvedBy:id,name',
                    'fundReleasedBy:id,name',
                    'receivedBy:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query->where('kode_pembelian', 'like', "%{$search}%")->orWhere('status', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialPurchase $row) => [
                    'id' => $row->id,
                    'kode_pembelian' => $row->kode_pembelian,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'request' => $row->materialPurchaseRequest?->kode_request ?? $row->materialRequest?->kode_request ?? '-',
                    'gudang' => $row->gudang?->nama_gudang ?? 'Gudang Umum',
                    'supplier' => $row->supplier ?? '-',
                    'metode_pembayaran' => $row->metode_pembayaran ?? 'tunai',
                    'planned_master_bank_id' => (string) ($row->planned_master_bank_id ?? ''),
                    'planned_bank' => $row->plannedMasterBank
                        ? "{$row->plannedMasterBank->nama_bank} - {$row->plannedMasterBank->nomor_rekening}"
                        : '-',
                    'payment_bank' => $row->paymentMasterBank
                        ? "{$row->paymentMasterBank->nama_bank} - {$row->paymentMasterBank->nomor_rekening}"
                        : '-',
                    'total_nominal' => $row->total_nominal,
                    'status' => $row->status,
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'approved_by_name' => $row->approvedBy?->name ?? '-',
                    'fund_released_by_name' => $row->fundReleasedBy?->name ?? '-',
                    'received_by_name' => $row->receivedBy?->name ?? '-',
                    'items' => $row->details->map(fn ($detail) => [
                        'id' => $detail->id,
                        'barang' => $detail->barangMaterial?->nama_barang,
                        'qty' => $detail->qty,
                        'qty_diterima' => $detail->qty_diterima,
                        'satuan' => $detail->satuan,
                        'inspection_status' => $detail->inspection_status,
                        'inspection_note' => $detail->inspection_note,
                    ])->values(),
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => ['search' => $search],
            'options' => $this->options(),
            'selectedRequest' => $selectedRequest ? [
                'id' => $selectedRequest->id,
                'kode_request' => $selectedRequest->kode_request,
                'gudang_id' => (string) $selectedRequest->gudang_id,
                'keterangan' => $selectedRequest->keterangan,
                'items' => $selectedRequest->details->map(fn ($detail) => [
                    'barang_material_id' => (string) $detail->barang_material_id,
                    'qty' => $detail->qty,
                    'satuan' => $detail->satuan,
                    'harga_satuan' => $detail->barangMaterial?->harga_hpp ?? 0,
                ])->values(),
            ] : null,
        ]);
    }

    public function store(Request $request, MaterialPurchaseService $service): RedirectResponse
    {
        $validated = $this->validated($request);
        $purchaseRequest = ! empty($validated['material_purchase_request_id'])
            ? MaterialPurchaseRequest::query()->findOrFail($validated['material_purchase_request_id'])
            : null;

        $service->createPurchase($validated, purchaseRequest: $purchaseRequest);

        return back()->with('success', 'Pembelian barang berhasil dibuat dan menunggu approval manajer.');
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
        $this->abortIfLocked($row);
        $service->approve($row);

        return back()->with('success', 'Pembelian berhasil disetujui dan menunggu pencairan dana.');
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
                    MaterialPurchase::STATUS_DIBELI,
                    MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
                    MaterialPurchase::STATUS_DITERIMA,
                    MaterialPurchase::STATUS_DITERIMA_SEBAGIAN,
                    MaterialPurchase::STATUS_DITOLAK_GUDANG,
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
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'supplier' => $row->supplier ?? '-',
                    'status' => $row->status,
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
                    ['value' => MaterialPurchase::STATUS_DIBELI, 'label' => 'Sudah Dibeli'],
                    ['value' => MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN, 'label' => 'Menunggu Pemeriksaan'],
                    ['value' => MaterialPurchase::STATUS_DITERIMA, 'label' => 'Diterima Logistik'],
                    ['value' => MaterialPurchase::STATUS_DITERIMA_SEBAGIAN, 'label' => 'Diterima Sebagian'],
                    ['value' => MaterialPurchase::STATUS_DITOLAK_GUDANG, 'label' => 'Ditolak Gudang'],
                ],
            ],
        ]);
    }

    public function inspectItem(Request $request, string $id, string $detailId, MaterialPurchaseService $service): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:sesuai,tidak_sesuai'],
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

    protected function validated(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'material_purchase_request_id' => ['nullable', 'exists:material_purchase_requests,id'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'metode_pembayaran' => ['required', 'in:tunai,hutang'],
            'planned_master_bank_id' => ['nullable', 'required_if:metode_pembayaran,tunai', 'exists:master_banks,id'],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string'],
            'items.*.harga_satuan' => ['required', 'numeric', 'min:0'],
        ]);
    }

    protected function options(): array
    {
        return [
            'gudangs' => Gudang::query()->where('status', 'aktif')->orderBy('nama_gudang')->get(['id', 'nama_gudang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_gudang])->values(),
            'barangMaterials' => BarangMaterial::query()->orderBy('nama_barang')->get(['id', 'nama_barang', 'satuan', 'harga_hpp'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_barang, 'satuan' => $row->satuan, 'harga_hpp' => $row->harga_hpp])->values(),
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
}
