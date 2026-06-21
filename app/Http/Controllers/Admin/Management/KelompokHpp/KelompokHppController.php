<?php

namespace App\Http\Controllers\Admin\Management\KelompokHpp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\KelompokHpp\StoreKelompokHppRequest;
use App\Http\Requests\Admin\KelompokHpp\UpdateKelompokHppRequest;
use App\Models\KelompokHpp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KelompokHppController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = KelompokHpp::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->orWhere('nama_hpp', 'like', "%{$search}%")
                        ->orWhere('kategori', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('kategori')
            ->orderBy('nama_hpp')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (KelompokHpp $row) => [
                ...$row->toArray(),
                'kategori_label' => $row->kategori_label,
            ]);

        return Inertia::render('Admin/Management/KelompokHpp/Index', [
            'title' => 'Management HPP',
            'description' => 'Kelola daftar kelompok HPP standar untuk perumahan, rumah, logistik, dan realisasi biaya.',
            'baseUrl' => route('admin.management.kelompok-hpp.index', absolute: false),
            'routeName' => 'admin.management.kelompok-hpp',
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => [
                ['key' => 'nama_hpp', 'label' => 'Nama HPP'],
                ['key' => 'kategori_label', 'label' => 'Kategori'],
                ['key' => 'record_status', 'label' => 'Lock'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'fields' => [
                ['name' => 'nama_hpp', 'label' => 'Nama HPP', 'type' => 'text'],
                ['name' => 'kategori', 'label' => 'Kategori', 'type' => 'select', 'optionsKey' => 'categories'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
            ],
            'options' => [
                'categories' => $this->categoryOptions(),
                'status' => [
                    ['value' => 'aktif', 'label' => 'Aktif'],
                    ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                ],
            ],
        ]);
    }

    public function store(StoreKelompokHppRequest $request): RedirectResponse
    {
        KelompokHpp::create($request->validated());

        return back()->with('success', 'Management HPP berhasil ditambahkan.');
    }

    public function update(UpdateKelompokHppRequest $request, string $id): RedirectResponse
    {
        $row = KelompokHpp::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($request->validated());

        return back()->with('success', 'Management HPP berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = KelompokHpp::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Management HPP berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return KelompokHpp::class;
    }

    protected function categoryOptions(): array
    {
        return [
            ['value' => 'tanah', 'label' => 'Tanah'],
            ['value' => 'legalitas', 'label' => 'Perizinan & Persuratan'],
            ['value' => 'bangunan', 'label' => 'Konstruksi'],
            ['value' => 'tenaga_kerja', 'label' => 'Konstruksi - Tenaga Kerja'],
            ['value' => 'material', 'label' => 'Logistik'],
            ['value' => 'infrastruktur', 'label' => 'Utilitas'],
            ['value' => 'marketing', 'label' => 'Pemasaran'],
            ['value' => 'operasional', 'label' => 'Operasional'],
            ['value' => 'keuangan', 'label' => 'Keuangan'],
            ['value' => 'cadangan', 'label' => 'Cadangan'],
        ];
    }
}
