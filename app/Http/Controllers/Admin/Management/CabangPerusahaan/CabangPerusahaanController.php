<?php

namespace App\Http\Controllers\Admin\Management\CabangPerusahaan;

use App\Http\Controllers\Admin\Management\CabangPerusahaan\Logic\CabangPerusahaanPayload;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\RendersSeparatedManagementForm;
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
    use HandlesCrudLock, RendersSeparatedManagementForm;

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

        $fields = $this->fields();
        $options = $this->options();

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
            ->through(fn (CabangPerusahaan $row) => [...$row->toArray(), 'edit_url' => route('admin.management.cabang-perusahaan.edit', $row->id, false)]);

        return Inertia::render('Admin/Management/CabangPerusahaan/Index', [
            'title' => $this->title(),
            'description' => $this->description(),
            'baseUrl' => route('admin.management.cabang-perusahaan.index', absolute: false),
            'createUrl' => route('admin.management.cabang-perusahaan.create', absolute: false),
            'permissionKey' => 'cabang',
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

        return to_route('admin.management.cabang-perusahaan.index')->with('success', 'Management Cabang Perusahaan berhasil ditambahkan.');
    }

    public function update(UpdateCabangPerusahaanRequest $request, string $id): RedirectResponse
    {
        $row = CabangPerusahaan::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update(app(CabangPerusahaanPayload::class)->fromRequest($request));

        return to_route('admin.management.cabang-perusahaan.index')->with('success', 'Management Cabang Perusahaan berhasil diperbarui.');
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

    protected function fields(): array
    {
        return [
            ['name' => 'nama_cabang', 'label' => 'Nama Cabang', 'type' => 'text', 'required' => true],
            ['name' => 'emaiil', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
            ['name' => 'manager_name', 'label' => 'Nama Manager', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status', 'required' => true],
            ['name' => 'type', 'label' => 'Tipe', 'type' => 'select', 'optionsKey' => 'branchTypes', 'required' => true],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'text'],
            ['name' => 'longtitude', 'label' => 'Longitude', 'type' => 'text'],
            ['name' => 'logo', 'label' => 'Logo', 'type' => 'image'],
            ['name' => 'image', 'label' => 'Foto Kantor Cabang', 'type' => 'image'],
            ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'full' => true],
            ['name' => 'deskripsi', 'label' => 'Deskripsi', 'type' => 'textarea', 'full' => true],
        ];
    }

    protected function options(): array
    {
        return [
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
            'branchTypes' => [['value' => 'pusat', 'label' => 'Pusat'], ['value' => 'cabang', 'label' => 'Cabang']],
        ];
    }

    protected function routeName(): string { return 'admin.management.cabang-perusahaan'; }
    protected function title(): string { return 'Management Cabang Perusahaan'; }
    protected function description(): string { return 'Kelola identitas, lokasi, kontak, dan status kantor cabang perusahaan.'; }
}
