<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\MaterialUsage;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Models\SiteMaterialStock;
use App\Models\TahapanPembangunan;
use App\Services\MaterialWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MaterialUsageController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');
        $tahapanId = $request->query('tahapan_pembangunan_id');

        return Inertia::render('Admin/MaterialUsage/Index', [
            'title' => 'Pemakaian Material',
            'baseUrl' => route('admin.material-usage.index', absolute: false),
            'filters' => [
                'search' => $search,
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
                'tahapan_pembangunan_id' => $tahapanId,
            ],
            'rows' => MaterialUsage::query()
                ->with([
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'progressPembangunan:id,nama_progress,persentase,tanggal',
                    'details.barangMaterial:id,nama_barang',
                    'creator:id,name',
                    'updater:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query
                    ->where('kode_pemakaian', 'like', "%{$search}%")
                    ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%")))
                ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
                ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
                ->when($tahapanId, fn (Builder $query) => $query->where('tahapan_pembangunan_id', $tahapanId))
                ->latest('tanggal')
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (MaterialUsage $row) => [
                    'id' => $row->id,
                    'kode_pemakaian' => $row->kode_pemakaian,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'perumahan_id' => (string) $row->perumahan_id,
                    'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                    'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                    'progress_pembangunan_id' => (string) ($row->progress_pembangunan_id ?? ''),
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah
                        ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah)
                        : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'progress' => $row->progressPembangunan
                        ? ($row->progressPembangunan->nama_progress ?: 'Progress').' - '.$row->progressPembangunan->persentase.'% - '.optional($row->progressPembangunan->tanggal)->format('Y-m-d')
                        : '-',
                    'items_text' => $row->details->map(fn ($detail) => "{$detail->barangMaterial?->nama_barang} {$detail->qty} {$detail->satuan}")->join(', '),
                    'items' => $row->details->map(fn ($detail) => [
                        'site_material_stock_id' => (string) $detail->site_material_stock_id,
                        'qty' => $detail->qty,
                        'satuan' => $detail->satuan,
                    ])->values(),
                    'keterangan' => $row->keterangan,
                    'foto_url' => $row->foto ? route('media', ['path' => $row->foto], false) : null,
                    'record_status' => $row->record_status ?? 'draft',
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked',
                    'can_delete' => ($row->record_status ?? 'draft') !== 'locked',
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && (bool) auth()->user()?->hasAnyRole(['pengawas', 'owner', 'super_admin']),
                    'can_unlock' => (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']),
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                ]),
            'options' => $this->options(),
            'siteStockRows' => SiteMaterialStock::query()
                ->with([
                    'gudang:id,nama_gudang',
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'barangMaterial:id,nama_barang,satuan',
                    'kelompokHpp:id,nama_hpp',
                ])
                ->orderByDesc('qty_available')
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'gudang' => $row->gudang?->nama_gudang ?? '-',
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'kelompok_hpp' => $row->kelompokHpp?->nama_hpp ?? '-',
                    'material' => $row->barangMaterial?->nama_barang ?? '-',
                    'satuan' => $row->barangMaterial?->satuan ?? '-',
                    'diterima' => $row->qty_received,
                    'dipakai' => $row->qty_used,
                    'dikembalikan' => $row->qty_returned,
                    'menunggu_pengembalian' => $row->qty_reserved_return,
                    'sisa' => $row->qty_available,
                ])->values(),
        ]);
    }

    public function store(Request $request, MaterialWorkflowService $workflow): RedirectResponse
    {
        $this->authorizePengawas();
        $validated = $this->validatePayload($request);
        $this->ensureProgressMatches($validated);

        $workflow->recordUsage($validated, $request->file('foto'));

        return back()->with('success', 'Pemakaian material berhasil dicatat dan sisa material lokasi telah diperbarui.');
    }

    public function update(Request $request, string $id, MaterialWorkflowService $workflow): RedirectResponse
    {
        $this->authorizePengawas();
        $usage = MaterialUsage::query()->findOrFail($id);
        $this->abortIfLocked($usage);
        $validated = $this->validatePayload($request);
        $this->ensureProgressMatches($validated);

        if ($request->hasFile('foto') && $usage->foto) {
            Storage::disk('public')->delete($usage->foto);
        }

        $workflow->updateUsage($usage, $validated, $request->file('foto'));

        return back()->with('success', 'Pemakaian material berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizePengawas();
        $usage = MaterialUsage::query()->with('details')->findOrFail($id);
        $this->abortIfLocked($usage);

        foreach ($usage->details as $detail) {
            $detail->siteMaterialStock()->increment('qty_available', $detail->qty);
            $detail->siteMaterialStock()->decrement('qty_used', $detail->qty);
        }

        if ($usage->foto) {
            Storage::disk('public')->delete($usage->foto);
        }

        $usage->delete();

        return back()->with('success', 'Pemakaian material berhasil dihapus dan saldo lokasi dikembalikan.');
    }

    protected function modelClass(): string
    {
        return MaterialUsage::class;
    }

    private function authorizePengawas(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['pengawas', 'owner', 'super_admin']), 403, 'Hanya pengawas yang dapat mencatat pemakaian material.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['required', 'exists:tahapan_pembangunans,id'],
            'progress_pembangunan_id' => ['required', 'exists:progress_pembangunans,id'],
            'keterangan' => ['required', 'string'],
            'foto' => ['nullable', 'image', 'max:4096'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.site_material_stock_id' => ['required', 'exists:site_material_stocks,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.satuan' => ['nullable', 'string'],
        ], [
            'progress_pembangunan_id.required' => 'Progress pembangunan wajib dipilih agar pemakaian material tersinkron.',
        ]);
    }

    private function ensureProgressMatches(array $validated): void
    {
        $progress = ProgressPembangunan::query()->findOrFail($validated['progress_pembangunan_id']);

        abort_unless($progress->approval_status === 'approved', 422, 'Pemakaian material hanya dapat dikaitkan ke progress yang sudah disetujui.');
        abort_unless((int) $progress->detail_rumah_id === (int) ($validated['detail_rumah_id'] ?? 0), 422, 'Progress tidak sesuai dengan unit yang dipilih.');
        abort_unless((int) $progress->tahapan_pembangunan_id === (int) $validated['tahapan_pembangunan_id'], 422, 'Progress tidak sesuai dengan tahapan yang dipilih.');
    }

    private function options(): array
    {
        return [
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'detailRumahs' => DetailRumah::query()->with('perumahan:id,nama_perusahaan')->orderBy('kode_nlok')->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->perumahan?->nama_perusahaan} - {$row->kode_nlok} {$row->nomor_rumah}", 'perumahan_id' => (string) $row->perumahan_id])->values(),
            'tahapanPembangunans' => TahapanPembangunan::query()->where('status', 'aktif')->orderBy('urutan')->get(['id', 'nama_tahapan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_tahapan])->values(),
            'progressPembangunans' => ProgressPembangunan::query()->where('approval_status', 'approved')->latest('tanggal')->get(['id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'tanggal', 'nama_progress', 'persentase'])
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => ($row->nama_progress ?: 'Progress').' - '.optional($row->tanggal)->format('Y-m-d')." - {$row->persentase}%",
                    'detail_rumah_id' => (string) $row->detail_rumah_id,
                    'tahapan_pembangunan_id' => (string) $row->tahapan_pembangunan_id,
                ])->values(),
            'siteStocks' => SiteMaterialStock::query()->with(['barangMaterial:id,nama_barang,satuan', 'gudang:id,nama_gudang'])->where('qty_available', '>', 0)->get()
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => "{$row->barangMaterial?->nama_barang} - sisa {$row->qty_available} {$row->barangMaterial?->satuan}",
                    'perumahan_id' => (string) $row->perumahan_id,
                    'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                    'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                    'satuan' => $row->barangMaterial?->satuan,
                    'qty_available' => $row->qty_available,
                    'gudang' => $row->gudang?->nama_gudang,
                ])->values(),
        ];
    }
}
