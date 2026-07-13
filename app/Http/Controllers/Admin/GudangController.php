<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\CabangPerusahaan;
use App\Models\Gudang;
use App\Models\MaterialRequest;
use App\Models\MaterialReturn;
use App\Models\SiteMaterialStock;
use App\Models\Perumahan;
use App\Models\MaterialUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GudangController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/Logistik/Gudang', [
            'title' => 'Dashboard Gudang',
            'baseUrl' => route('admin.gudang.index', absolute: false),
            'stats' => [
                'total_gudang' => Gudang::query()->count(),
                'gudang_aktif' => Gudang::query()->where('status', 'aktif')->count(),
                'stok_kosong' => SiteMaterialStock::query()->where('qty_available', '<=', 0)->count(),
                'permintaan_material' => MaterialRequest::query()->whereIn('status', [MaterialRequest::STATUS_DIAJUKAN, MaterialRequest::STATUS_MENUNGGU_OWNER, MaterialRequest::STATUS_MENUNGGU_STOK])->count(),
                'pengembalian_diajukan' => MaterialReturn::query()->where('status', MaterialReturn::STATUS_DIAJUKAN)->count(),
                'pemakaian_hari_ini' => MaterialUsage::query()->whereDate('tanggal', now()->toDateString())->count(),
            ],
            'rows' => Gudang::query()
                ->with(['cabang:id,nama_cabang', 'perumahan:id,nama_perusahaan'])
                ->when($search !== '', fn (Builder $query) => $query->where('kode_gudang', 'like', "%{$search}%")
                    ->orWhere('nama_gudang', 'like', "%{$search}%")
                    ->orWhere('penanggung_jawab', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(8)
                ->withQueryString()
                ->through(fn (Gudang $row) => [
                    'id' => $row->id,
                    'kode_gudang' => $row->kode_gudang,
                    'nama_gudang' => $row->nama_gudang,
                    'cabang' => $row->cabang?->nama_cabang ?? '-',
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'penanggung_jawab' => $row->penanggung_jawab,
                    'phone' => $row->phone,
                    'status' => $row->status,
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => ['search' => $search],
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gudang::query()->create([
            ...$this->payload($request),
            'kode_gudang' => $this->nextCode(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $row = Gudang::query()->findOrFail($id);
        $this->abortIfLocked($row);

        $row->update([
            ...$this->payload($request),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = Gudang::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Gudang berhasil dihapus.');
    }

    protected function payload(Request $request): array
    {
        return $request->validate([
            'nama_gudang' => ['required', 'string', 'max:255'],
            'cabang_id' => ['nullable', 'exists:cabang_perusahaans,id'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }

    protected function nextCode(): string
    {
        return 'GDG-'.now()->format('Ym').'-'.str_pad((string) (Gudang::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function options(): array
    {
        return [
            'cabangs' => CabangPerusahaan::query()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_cabang])->values(),
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ];
    }

    protected function modelClass(): string
    {
        return Gudang::class;
    }
}
