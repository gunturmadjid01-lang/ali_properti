<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\CabangPerusahaan;
use App\Models\Gudang;
use App\Models\MaterialRequest;
use App\Models\MaterialReturn;
use App\Models\MaterialUsage;
use App\Models\Perumahan;
use App\Models\SiteMaterialStock;
use Carbon\Carbon;
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
        $chartStart = now()->copy()->subMonths(5)->startOfMonth();
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->copy()->subMonths($offset));
        $requestsByMonth = MaterialRequest::query()->whereDate('tanggal', '>=', $chartStart)->get(['tanggal'])
            ->countBy(fn (MaterialRequest $row) => $row->tanggal?->format('Y-m'));
        $usagesByMonth = MaterialUsage::query()->whereDate('tanggal', '>=', $chartStart)->get(['tanggal'])
            ->countBy(fn (MaterialUsage $row) => $row->tanggal?->format('Y-m'));
        $returnsByMonth = MaterialReturn::query()->whereDate('tanggal', '>=', $chartStart)->get(['tanggal'])
            ->countBy(fn (MaterialReturn $row) => $row->tanggal?->format('Y-m'));
        $emptyStock = SiteMaterialStock::query()->where('qty_available', '<=', 0)->count();
        $availableStock = SiteMaterialStock::query()->where('qty_available', '>', 0)->count();

        return Inertia::render('Admin/Logistik/Gudang', [
            'title' => 'Dashboard Gudang',
            'baseUrl' => route('admin.gudang.index', absolute: false),
            'stats' => [
                'total_gudang' => Gudang::query()->count(),
                'gudang_aktif' => Gudang::query()->where('status', 'aktif')->count(),
                'stok_kosong' => $emptyStock,
                'permintaan_material' => MaterialRequest::query()->whereIn('status', [MaterialRequest::STATUS_DIAJUKAN, MaterialRequest::STATUS_MENUNGGU_OWNER, MaterialRequest::STATUS_MENUNGGU_STOK])->count(),
                'pengembalian_diajukan' => MaterialReturn::query()->where('status', MaterialReturn::STATUS_DIAJUKAN)->count(),
                'pemakaian_hari_ini' => MaterialUsage::query()->whereDate('tanggal', now()->toDateString())->count(),
            ],
            'charts' => [
                'activity' => [
                    'labels' => $months->map(fn (Carbon $month) => $month->translatedFormat('M Y'))->values(),
                    'requests' => $months->map(fn (Carbon $month) => $requestsByMonth->get($month->format('Y-m'), 0))->values(),
                    'usages' => $months->map(fn (Carbon $month) => $usagesByMonth->get($month->format('Y-m'), 0))->values(),
                    'returns' => $months->map(fn (Carbon $month) => $returnsByMonth->get($month->format('Y-m'), 0))->values(),
                ],
                'stock_health' => [
                    'available' => $availableStock,
                    'empty' => $emptyStock,
                ],
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

    public function create(): Response
    {
        return Inertia::render('Admin/Logistik/GudangForm', [
            'title' => 'Tambah Gudang Baru',
            'baseUrl' => route('admin.gudang.index', absolute: false),
            'gudang' => null,
            'options' => $this->options(),
        ]);
    }

    public function edit(string $id): Response
    {
        $gudang = Gudang::query()->findOrFail($id);

        return Inertia::render('Admin/Logistik/GudangForm', [
            'title' => 'Edit Gudang',
            'baseUrl' => route('admin.gudang.index', absolute: false),
            'gudang' => [
                'id' => $gudang->id,
                'kode_gudang' => $gudang->kode_gudang,
                'nama_gudang' => $gudang->nama_gudang,
                'cabang_id' => (string) $gudang->cabang_id,
                'perumahan_id' => (string) $gudang->perumahan_id,
                'penanggung_jawab' => $gudang->penanggung_jawab,
                'phone' => $gudang->phone,
                'alamat' => $gudang->alamat,
                'catatan' => $gudang->catatan,
                'status' => $gudang->status,
            ],
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

    public function manajemen(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');

        $query = Gudang::query()
            ->with(['cabang:id,nama_cabang', 'perumahan:id,nama_perusahaan', 'users:id,name,email']);

        if ($search !== '') {
            $query->where('kode_gudang', 'like', "%{$search}%")
                ->orWhere('nama_gudang', 'like', "%{$search}%")
                ->orWhere('penanggung_jawab', 'like', "%{$search}%");
        }

        if ($perumahanId) {
            $query->where('perumahan_id', $perumahanId);
        }

        $gudangs = $query->latest('id')->paginate(15)->withQueryString();

        $perumahans = Perumahan::query()
            ->select('id', 'nama_perusahaan')
            ->orderBy('nama_perusahaan')
            ->get();

        $allUsers = \App\Models\User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['admin', 'manager', 'user_area_gudang']))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Logistik/ManajemenGudang', [
            'title' => 'Manajemen Gudang',
            'baseUrl' => route('admin.gudang.manajemen', absolute: false),
            'rows' => $gudangs->through(fn (Gudang $row) => [
                'id' => $row->id,
                'kode_gudang' => $row->kode_gudang,
                'nama_gudang' => $row->nama_gudang,
                'cabang' => $row->cabang?->nama_cabang ?? '-',
                'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                'penanggung_jawab' => $row->penanggung_jawab,
                'status' => $row->status,
                'users' => $row->users->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])->values(),
            ]),
            'filters' => [
                'search' => $search,
                'perumahan_id' => $perumahanId,
            ],
            'perumahans' => $perumahans,
            'allUsers' => $allUsers,
        ]);
    }

    public function assignUser(string $id, Request $request): RedirectResponse
    {
        $gudang = Gudang::query()->findOrFail($id);
        $userId = $request->integer('user_id');

        $gudang->users()->syncWithoutDetaching([$userId]);

        return back()->with('success', 'Petugas berhasil ditugaskan ke gudang.');
    }

    public function removeUser(string $id, Request $request): RedirectResponse
    {
        $gudang = Gudang::query()->findOrFail($id);
        $userId = $request->integer('user_id');

        $gudang->users()->detach($userId);

        return back()->with('success', 'Petugas berhasil dihapus dari gudang.');
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
        return 'GDG-' . now()->format('Ym') . '-' . str_pad((string) (Gudang::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function options(): array
    {
        return [
            'cabangs' => CabangPerusahaan::query()->finalized()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_cabang])->values(),
            'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ];
    }

    protected function modelClass(): string
    {
        return Gudang::class;
    }
}
