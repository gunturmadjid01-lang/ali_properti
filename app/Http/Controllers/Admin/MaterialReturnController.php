<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\MaterialReturn;
use App\Models\SiteMaterialStock;
use App\Services\MaterialWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialReturnController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        abort_unless(! auth()->user()?->hasAnyRole(['user_area_gudang', 'admin_gudang']), 404);

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/MaterialReturn/Index', [
            'title' => 'Pengembalian Stok',
            'baseUrl' => route('admin.material-return.index', absolute: false),
            'filters' => ['search' => $search],
            'permissions' => [
                'canCreate' => (bool) auth()->user()?->can('material-return.create'),
                'canReceive' => (bool) auth()->user()?->hasAnyRole(['user_area_gudang', 'owner', 'super_admin']),
                'canLock' => (bool) auth()->check(),
                'canUnlock' => $this->currentUserCanManageLockedRecords(),
            ],
            'rows' => MaterialReturn::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'details.barangMaterial:id,nama_barang',
                    'creator:id,name',
                    'updater:id,name',
                    'receivedBy:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query
                    ->where('kode_pengembalian', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%"))
                ->latest('tanggal')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialReturn $row) => [
                    'id' => $row->id,
                    'kode_pengembalian' => $row->kode_pengembalian,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'items_text' => $row->details->map(fn ($detail) => "{$detail->barangMaterial?->nama_barang} {$detail->qty} {$detail->satuan}")->join(', '),
                    'status' => $row->status,
                    'keterangan' => $row->keterangan,
                    'record_status' => $row->record_status ?? 'draft',
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'received_by_name' => $row->receivedBy?->name ?? '-',
                    'can_receive' => $this->canReceive(),
                ]),
            'siteStocks' => SiteMaterialStock::query()
                ->with(['barangMaterial:id,nama_barang,satuan', 'gudang:id,nama_gudang', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah'])
                ->where('qty_available', '>', 0)
                ->get()
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->barangMaterial?->nama_barang} - sisa {$row->qty_available} {$row->barangMaterial?->satuan}",
                    'gudang_id' => (string) $row->gudang_id,
                    'gudang' => $row->gudang?->nama_gudang,
                    'perumahan_id' => (string) $row->perumahan_id,
                    'perumahan' => $row->perumahan?->nama_perusahaan,
                    'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                    'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
                    'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                    'satuan' => $row->barangMaterial?->satuan,
                    'qty_available' => $row->qty_available,
                ])->values(),
        ]);
    }

    public function store(Request $request, MaterialWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canCreateReturn(), 403, 'Hanya pengawas yang dapat mengajukan pengembalian stok.');
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'gudang_id' => ['required', 'exists:gudangs,id'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'keterangan' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.site_material_stock_id' => ['required', 'exists:site_material_stocks,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        $workflow->submitReturn($validated);

        return back()->with('success', 'Pengembalian material berhasil diajukan ke gudang.');
    }

    public function receive(Request $request, string $id, MaterialWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canReceive(), 403, 'Hanya petugas gudang yang dapat menerima pengembalian.');
        $validated = $request->validate(['receive_note' => ['nullable', 'string']]);
        $workflow->receiveReturn(MaterialReturn::query()->findOrFail($id), $validated['receive_note'] ?? null);

        return back()->with('success', 'Material diterima, stok gudang bertambah, dan realisasi HPP telah dikoreksi.');
    }

    public function reject(Request $request, string $id, MaterialWorkflowService $workflow): RedirectResponse
    {
        abort_unless($this->canReceive(), 403, 'Hanya petugas gudang yang dapat menolak pengembalian.');
        $validated = $request->validate(['receive_note' => ['nullable', 'string']]);
        $workflow->rejectReturn(MaterialReturn::query()->findOrFail($id), $validated['receive_note'] ?? null);

        return back()->with('success', 'Pengembalian ditolak dan material dikembalikan ke saldo lokasi.');
    }

    protected function modelClass(): string
    {
        return MaterialReturn::class;
    }

    private function canCreateReturn(): bool
    {
        return (bool) (auth()->user()?->can('material-return.create') || auth()->user()?->can('material-return.manage'));
    }

    private function canReceive(): bool
    {
        return (bool) (auth()->user()?->can('material-return.update')
            || auth()->user()?->can('material-return.manage')
            || auth()->user()?->hasRole('super_admin'));
    }
}
