<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseRequest;
use App\Models\MasterBank;
use App\Services\AppNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialPurchaseRequestController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/MaterialPurchaseRequest/Index', [
            'title' => 'Permintaan Pembelian Barang',
            'baseUrl' => route('admin.material-purchase-request.index', absolute: false),
            'permissions' => [
                'canCreate' => (bool) auth()->user()?->can('material-purchase.create') || auth()->user()?->can('material-purchase.manage'),
                'canUpdate' => (bool) auth()->user()?->can('material-purchase.update') || auth()->user()?->can('material-purchase.manage'),
                'canDelete' => (bool) auth()->user()?->can('material-purchase.delete') || auth()->user()?->can('material-purchase.manage'),
            ],
            'rows' => MaterialPurchaseRequest::query()
                ->with(['gudang:id,nama_gudang', 'requestedBy:id,name', 'details.barangMaterial:id,nama_barang'])
                ->when($search !== '', fn (Builder $query) => $query
                    ->where('kode_request', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialPurchaseRequest $row) => [
                    'id' => $row->id,
                    'kode_request' => $row->kode_request,
                    'tanggal' => $row->tanggal?->format('Y-m-d'),
                    'gudang_id' => (string) $row->gudang_id,
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'pemohon' => $row->requestedBy?->name ?? '-',
                    'status' => $row->status,
                    'keterangan' => $row->keterangan,
                    'items_text' => $row->details->map(fn ($detail) => "{$detail->barangMaterial?->nama_barang} {$detail->qty} {$detail->satuan}")->join(', '),
                    'items' => $row->details->map(fn ($detail) => [
                        'barang_material_id' => (string) $detail->barang_material_id,
                        'qty' => $detail->qty,
                        'satuan' => $detail->satuan,
                        'catatan' => $detail->catatan,
                    ])->values(),
                    'record_status' => $row->record_status,
                    'can_edit' => $row->record_status !== 'locked' && $row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN,
                    'can_delete' => $row->record_status !== 'locked' && $row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN,
                ]),
            'filters' => ['search' => $search],
            'options' => $this->options(),
        ]);
    }

    public function financeIndex(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/MaterialPurchaseRequest/FinanceIndex', [
            'title' => 'Approval Permintaan Pembelian Gudang',
            'baseUrl' => route('admin.material-purchase-request.finance-index', absolute: false),
            'purchaseUrl' => route('admin.material-purchase.index', absolute: false),
            'purchaseActionUrl' => '/admin/pembelian-material',
            'permissions' => [
                'canProcess' => (bool) auth()->user()?->hasAnyRole(['admin_keuangan', 'keuangan', 'manajer_pimpro', 'owner', 'super_admin']),
                'canApprove' => (bool) auth()->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']),
                'canRelease' => (bool) auth()->user()?->hasAnyRole(['keuangan', 'admin_keuangan', 'owner', 'super_admin']),
                'canMarkPurchased' => (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'admin', 'super_admin']),
            ],
            'rows' => MaterialPurchaseRequest::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'requestedBy:id,name',
                    'details.barangMaterial:id,nama_barang',
                    'purchases' => fn ($query) => $query
                        ->with([
                            'plannedMasterBank:id,nama_bank,nomor_rekening',
                            'paymentMasterBank:id,nama_bank,nomor_rekening',
                            'creator:id,name',
                            'updater:id,name',
                            'approvedBy:id,name',
                            'fundReleasedBy:id,name',
                        ])
                        ->latest('id'),
                ])
                ->when($search !== '', fn (Builder $query) => $query
                    ->where('kode_request', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(function (MaterialPurchaseRequest $row) {
                    $purchase = $row->purchases->first();

                    return [
                        'id' => $row->id,
                        'kode_request' => $row->kode_request,
                        'tanggal' => $row->tanggal?->format('Y-m-d'),
                        'gudang' => $row->gudang?->nama_gudang ?? '-',
                        'pemohon' => $row->requestedBy?->name ?? '-',
                        'status' => $row->status,
                        'keterangan' => $row->keterangan,
                        'items_text' => $row->details->map(fn ($detail) => "{$detail->barangMaterial?->nama_barang} {$detail->qty} {$detail->satuan}")->join(', '),
                        'items_count' => $row->details->count(),
                        'items' => $row->details->map(fn ($detail) => [
                            'id' => $detail->id,
                            'barang' => $detail->barangMaterial?->nama_barang ?? '-',
                            'qty' => $detail->qty,
                            'satuan' => $detail->satuan,
                            'catatan' => $detail->catatan,
                        ])->values(),
                        'purchase_id' => $purchase?->id,
                        'purchase_code' => $purchase?->kode_pembelian,
                        'purchase_status' => $purchase?->status,
                        'planned_master_bank_id' => (string) ($purchase?->planned_master_bank_id ?? ''),
                        'planned_bank' => $purchase?->plannedMasterBank
                            ? "{$purchase->plannedMasterBank->nama_bank} - {$purchase->plannedMasterBank->nomor_rekening}"
                            : '-',
                        'payment_bank' => $purchase?->paymentMasterBank
                            ? "{$purchase->paymentMasterBank->nama_bank} - {$purchase->paymentMasterBank->nomor_rekening}"
                            : '-',
                        'purchase_created_by_name' => $purchase?->creator?->name ?? '-',
                        'purchase_updated_by_name' => $purchase?->updater?->name ?? '-',
                        'purchase_approved_by_name' => $purchase?->approvedBy?->name ?? '-',
                        'purchase_released_by_name' => $purchase?->fundReleasedBy?->name ?? '-',
                        'can_process' => $row->status === MaterialPurchaseRequest::STATUS_DIAJUKAN,
                        'can_approve' => ($purchase?->record_status ?? 'draft') === 'locked' && $purchase?->status === MaterialPurchase::STATUS_MENUNGGU_MANAGER,
                        'can_release' => $purchase?->status === MaterialPurchase::STATUS_MENUNGGU_DANA,
                        'can_mark_purchased' => $purchase?->status === MaterialPurchase::STATUS_DANA_CAIR,
                    ];
                }),
            'filters' => ['search' => $search],
            'bankOptions' => MasterBank::query()
                ->where('status', 'aktif')
                ->orderBy('nama_bank')
                ->get(['id', 'nama_bank', 'nomor_rekening', 'nama_rekening'])
                ->map(fn ($bank) => [
                    'value' => (string) $bank->id,
                    'label' => trim("{$bank->nama_bank} - {$bank->nomor_rekening} - {$bank->nama_rekening}"),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request, AppNotificationService $notifications): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $notifications) {
            $purchaseRequest = MaterialPurchaseRequest::query()->create([
                ...collect($validated)->except('items')->all(),
                'kode_request' => 'PR-GDG-'.now()->format('YmdHisv'),
                'requested_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncDetails($purchaseRequest, $validated['items']);

            $notifications->toRoles(
                ['keuangan', 'admin_keuangan', 'owner', 'super_admin'],
                'Permintaan pembelian barang baru',
                "Gudang mengajukan {$purchaseRequest->kode_request}.",
                '/admin/daftar-permintaan-pembelian'
            );
        });

        return back()->with('success', 'Permintaan pembelian berhasil dikirim ke Admin Keuangan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $purchaseRequest = MaterialPurchaseRequest::query()->with('details')->findOrFail($id);
        $this->abortIfLocked($purchaseRequest);
        abort_unless($purchaseRequest->status === MaterialPurchaseRequest::STATUS_DIAJUKAN, 422, 'Permintaan yang sedang diproses tidak dapat diubah.');

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($purchaseRequest, $validated) {
            $purchaseRequest->update([
                ...collect($validated)->except('items')->all(),
                'updated_by' => auth()->id(),
            ]);
            $purchaseRequest->details()->delete();
            $this->syncDetails($purchaseRequest, $validated['items']);
        });

        return back()->with('success', 'Permintaan pembelian berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $purchaseRequest = MaterialPurchaseRequest::query()->findOrFail($id);
        $this->abortIfLocked($purchaseRequest);
        abort_unless($purchaseRequest->status === MaterialPurchaseRequest::STATUS_DIAJUKAN, 422, 'Permintaan yang sedang diproses tidak dapat dihapus.');
        $purchaseRequest->delete();

        return back()->with('success', 'Permintaan pembelian berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return MaterialPurchaseRequest::class;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'keterangan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_material_id' => ['required', 'exists:barang_materials,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string', 'max:100'],
            'items.*.catatan' => ['nullable', 'string'],
        ]);
    }

    private function syncDetails(MaterialPurchaseRequest $purchaseRequest, array $items): void
    {
        foreach ($items as $item) {
            $material = BarangMaterial::query()->findOrFail($item['barang_material_id']);
            $purchaseRequest->details()->create([
                ...$item,
                'satuan' => $item['satuan'] ?: $material->satuan,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }
    }

    private function options(): array
    {
        return [
            'gudangs' => Gudang::query()->where('status', 'aktif')->orderBy('nama_gudang')->get(['id', 'nama_gudang'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_gudang])->values(),
            'barangMaterials' => BarangMaterial::query()->orderBy('nama_barang')->get(['id', 'nama_barang', 'satuan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_barang, 'satuan' => $row->satuan])->values(),
        ];
    }
}
