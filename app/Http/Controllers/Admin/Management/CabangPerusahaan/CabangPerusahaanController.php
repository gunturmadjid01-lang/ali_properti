<?php

namespace App\Http\Controllers\Admin\Management\CabangPerusahaan;

use App\Http\Controllers\Admin\Management\CabangPerusahaan\Logic\CabangPerusahaanPayload;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\CabangPerusahaan\StoreCabangPerusahaanRequest;
use App\Http\Requests\Admin\CabangPerusahaan\UpdateCabangPerusahaanRequest;
use App\Http\Controllers\Controller;
use App\Models\CabangPerusahaan;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CabangPerusahaanController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $searchableColumns = ['kode_cabang', 'nama_cabang', 'phone', 'emaiil', 'manager_name', 'status'];

        $columns = [
            ['key' => 'kode_cabang', 'label' => 'Kode'],
            ['key' => 'nama_cabang', 'label' => 'Nama Cabang'],
            ['key' => 'phone', 'label' => 'Telepon'],
            ['key' => 'emaiil', 'label' => 'Email'],
            ['key' => 'manager_name', 'label' => 'Manager'],
            ['key' => 'record_status', 'label' => 'Lock'],
            ['key' => 'status', 'label' => 'Status'],
        ];

        $fields = [
            ['name' => 'nama_cabang', 'label' => 'Nama Cabang', 'type' => 'text'],
            ['name' => 'emaiil', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
            ['name' => 'manager_name', 'label' => 'Nama Manager', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
            ['name' => 'type', 'label' => 'Tipe', 'type' => 'select', 'optionsKey' => 'branchTypes'],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'text'],
            ['name' => 'longtitude', 'label' => 'Longitude', 'type' => 'text'],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'image'],
            ['name' => 'image', 'label' => 'Foto Kantor Cabang', 'type' => 'image'],
            ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'full' => true],
            ['name' => 'deskripsi', 'label' => 'Deskripsi', 'type' => 'textarea', 'full' => true],
        ];

        $options = [
            'status' => [
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
            'branchTypes' => [
                ['value' => 'pusat', 'label' => 'Pusat'],
                ['value' => 'cabang', 'label' => 'Cabang'],
            ],
        ];

        $rows = CabangPerusahaan::query()
            ->when($search !== '', function (Builder $query) use ($search, $searchableColumns) {
                $query->where(function (Builder $query) use ($search, $searchableColumns) {
                    foreach ($searchableColumns as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CabangPerusahaan $row) => $row->toArray());

        return Inertia::render('Admin/Management/CabangPerusahaan/Index', [
            'title' => 'Management Cabang Perusahaan',
            'description' => 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.',
            'baseUrl' => route('admin.management.cabang-perusahaan.index', absolute: false),
            'routeName' => 'admin.management.cabang-perusahaan',
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => $columns,
            'fields' => $fields,
            'options' => $options,
        ]);
    }

    public function store(StoreCabangPerusahaanRequest $request): RedirectResponse
    {
        $payload = app(CabangPerusahaanPayload::class)->fromRequest($request);
        $payload['kode_cabang'] = CodeGenerator::next(CabangPerusahaan::class, 'kode_cabang', 'CBG');

        CabangPerusahaan::create($payload);

        return back()->with('success', 'Management Cabang Perusahaan berhasil ditambahkan.');
    }

    public function update(UpdateCabangPerusahaanRequest $request, string $id): RedirectResponse
    {
        $row = CabangPerusahaan::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update(app(CabangPerusahaanPayload::class)->fromRequest($request));

        return back()->with('success', 'Management Cabang Perusahaan berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = CabangPerusahaan::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Management Cabang Perusahaan berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return CabangPerusahaan::class;
    }
}
