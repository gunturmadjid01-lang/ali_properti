<?php

namespace App\Http\Controllers\Admin\Management\MasterBank;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\RendersSeparatedManagementForm;
use App\Http\Requests\Admin\MasterBank\StoreMasterBankRequest;
use App\Http\Requests\Admin\MasterBank\UpdateMasterBankRequest;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterBankController extends Controller
{
    use HandlesCrudLock, RendersSeparatedManagementForm;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = MasterBank::query()
            ->with('perumahan:id,nama_perusahaan')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    foreach ($this->searchableColumns() as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                })->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MasterBank $row) => [
                ...$row->toArray(),
                'perumahan_nama' => $row->perumahan?->nama_perusahaan ?? '-',
                'record_status_label' => $row->record_status === 'locked' ? 'Locked' : 'Draft',
                'edit_url' => route($this->routeName().'.edit', $row->id, false),
            ]);

        return Inertia::render($this->component(), [
            'title' => $this->title(),
            'description' => $this->description(),
            'baseUrl' => route($this->routeName().'.index', absolute: false),
            'createUrl' => route($this->routeName().'.create', absolute: false),
            'permissionKey' => 'master-bank',
            'routeName' => $this->routeName(),
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreMasterBankRequest $request): RedirectResponse
    {
        MasterBank::create([
            ...$request->validated(),
            'kode_bank' => CodeGenerator::next(MasterBank::class, 'kode_bank', 'BNK'),
        ]);

        return to_route($this->routeName().'.index')->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateMasterBankRequest $request, string $id): RedirectResponse
    {
        $row = MasterBank::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($request->validated());

        return to_route($this->routeName().'.index')->with('success', $this->title().' berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = MasterBank::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', $this->title().' berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return MasterBank::class;
    }

    protected function component(): string
    {
        return 'Admin/Management/MasterBank/Index';
    }

    protected function routeName(): string
    {
        return 'admin.management.master-bank';
    }

    protected function title(): string
    {
        return 'Master Rekening Bank';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'kode_bank', 'label' => 'Kode Bank'],
            ['key' => 'perumahan_nama', 'label' => 'Perumahan'],
            ['key' => 'nama_bank', 'label' => 'Nama Bank'],
            ['key' => 'nomor_rekening', 'label' => 'Nomor Rekening'],
            ['key' => 'nama_rekening', 'label' => 'Nama Rekening'],
            ['key' => 'record_status_label', 'label' => 'Lock'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahan', 'required' => true],
            ['name' => 'nama_bank', 'label' => 'Nama Bank', 'type' => 'text', 'required' => true],
            ['name' => 'nomor_rekening', 'label' => 'Nomor Rekening', 'type' => 'text'],
            ['name' => 'nama_rekening', 'label' => 'Nama Rekening', 'type' => 'text'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status', 'required' => true],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['kode_bank', 'nama_bank', 'nomor_rekening', 'nama_rekening', 'status'];
    }

    protected function options(): array
    {
        return [
            'perumahan' => Perumahan::query()
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan'])
                ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                ->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ];
    }

    protected function description(): string
    {
        return 'Kelola rekening bank perusahaan untuk alur keluar masuk kas perumahan.';
    }
}
