<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tukang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TukangController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAction('view');
        $search = trim((string) $request->query('search', ''));

        $rows = Tukang::query()
            ->with('gajiAktif')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%")
                        ->orWhere('posisi', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Tukang $tukang) => [
                'id' => $tukang->id,
                'nama' => $tukang->nama,
                'alamat' => $tukang->alamat,
                'posisi' => $tukang->posisi,
                'posisi_label' => Tukang::POSITIONS[$tukang->posisi] ?? $tukang->posisi,
                'gaji_aktif' => $tukang->gajiAktif ? [
                    'nominal' => (string) $tukang->gajiAktif->nominal,
                    'tanggal_berlaku' => $tukang->gajiAktif->tanggal_berlaku?->format('Y-m-d'),
                    'tanggal_berlaku_label' => $tukang->gajiAktif->tanggal_berlaku?->translatedFormat('d F Y'),
                ] : null,
            ]);

        return Inertia::render('Admin/Tukang/Index', [
            'title' => 'Daftar Tukang',
            'description' => 'Kelola data tukang agar dapat dipilih saat mencatat kebutuhan tenaga kerja.',
            'baseUrl' => route('admin.tukang.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'positions' => $this->positionOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAction('create');

        Tukang::query()->create([
            ...$this->payload($request),
            'gaji' => 0,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Data tukang berhasil ditambahkan.');
    }

    public function update(Request $request, Tukang $tukang): RedirectResponse
    {
        $this->authorizeAction('update');

        $tukang->update([
            ...$this->payload($request),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Data tukang berhasil diperbarui.');
    }

    public function destroy(Tukang $tukang): RedirectResponse
    {
        $this->authorizeAction('delete');
        $tukang->delete();

        return back()->with('success', 'Data tukang berhasil dihapus.');
    }

    private function payload(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['required', 'string', 'max:2000'],
            'posisi' => ['required', Rule::in(array_keys(Tukang::POSITIONS))],
        ], [], [
            'nama' => 'Nama tukang',
            'alamat' => 'Alamat',
            'posisi' => 'Posisi tukang',
        ]);
    }

    private function positionOptions(): array
    {
        return collect(Tukang::POSITIONS)
            ->map(fn (string $label, string $value) => compact('value', 'label'))
            ->values()
            ->all();
    }

    private function authorizeAction(string $action): void
    {
        abort_unless(
            auth()->user()?->can("tukang.{$action}"),
            403,
            'Anda tidak memiliki akses untuk mengelola daftar tukang.'
        );
    }
}
