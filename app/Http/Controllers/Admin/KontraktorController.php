<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\Kontraktor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KontraktorController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $this->authorizeOwnerOrManager();
        $search = trim((string) $request->query('search', ''));

        $rows = Kontraktor::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_kontraktor', 'like', "%{$search}%")
                        ->orWhere('nama_kontraktor', 'like', "%{$search}%")
                        ->orWhere('bidang_pekerjaan', 'like', "%{$search}%")
                        ->orWhere('penanggung_jawab', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Kontraktor $row) => [
                'id' => $row->id,
                'kode_kontraktor' => $row->kode_kontraktor,
                'nama_kontraktor' => $row->nama_kontraktor,
                'jenis_badan' => $row->jenis_badan,
                'bidang_pekerjaan' => $row->bidang_pekerjaan,
                'penanggung_jawab' => $row->penanggung_jawab,
                'phone' => $row->phone,
                'email' => $row->email,
                'alamat' => $row->alamat,
                'catatan' => $row->catatan,
                'status' => $row->status,
                'created_by' => $row->creator?->name ?? '-',
                'updated_by' => $row->updater?->name ?? '-',
                'record_status' => $row->record_status ?? 'draft',
                'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            ]);

        return Inertia::render('Admin/Kontraktor/Index', [
            'title' => 'Management Kontraktor',
            'description' => 'Kelola daftar kontraktor yang bekerja sama untuk pembangunan rumah, jalan, dan pembukaan lahan.',
            'baseUrl' => route('admin.kontraktor.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $payload = $this->payload($request);

        Kontraktor::query()->create([
            ...$payload,
            'kode_kontraktor' => $this->nextCode(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Kontraktor berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $row = Kontraktor::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update([
            ...$this->payload($request),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Kontraktor berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeOwnerOrManager();
        $row = Kontraktor::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Kontraktor berhasil dihapus.');
    }

    protected function payload(Request $request): array
    {
        return $request->validate([
            'nama_kontraktor' => ['required', 'string', 'max:255'],
            'jenis_badan' => ['nullable', 'string', 'max:255'],
            'bidang_pekerjaan' => ['nullable', 'string', 'max:255'],
            'penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ], [], [
            'nama_kontraktor' => 'Nama kontraktor',
            'jenis_badan' => 'Jenis badan',
            'bidang_pekerjaan' => 'Bidang pekerjaan',
            'penanggung_jawab' => 'Penanggung jawab',
            'phone' => 'Nomor telepon',
            'email' => 'Email',
            'alamat' => 'Alamat',
            'catatan' => 'Catatan',
            'status' => 'Status',
        ]);
    }

    protected function nextCode(): string
    {
        $next = Kontraktor::withTrashed()->count() + 1;

        return 'KTR-'.now()->format('Ym').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function options(): array
    {
        return [
            'status' => [
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
            'jenisBadan' => [
                ['value' => '', 'label' => 'Tidak Ada'],
                ['value' => 'perorangan', 'label' => 'Perorangan'],
                ['value' => 'cv', 'label' => 'CV'],
                ['value' => 'pt', 'label' => 'PT'],
                ['value' => 'firma', 'label' => 'Firma'],
            ],
        ];
    }

    protected function authorizeOwnerOrManager(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        abort_unless($user->hasAnyRole(['owner', 'super_admin', 'manajer_pimpro']), 403, 'Hanya owner atau manager yang dapat mengelola kontraktor.');
    }

    protected function modelClass(): string
    {
        return Kontraktor::class;
    }
}
