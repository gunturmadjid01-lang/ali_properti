<?php

namespace App\Http\Controllers\Admin\Management\MasterBank;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\RendersSeparatedManagementForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterBank\StoreMasterBankRequest;
use App\Http\Requests\Admin\MasterBank\UpdateMasterBankRequest;
use App\Models\MasterBank;
use App\Models\CabangPerusahaan;
use App\Models\Perumahan;
use App\Services\ApprovalWorkflowService;
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
            ->with(['cabang:id,nama_cabang', 'perumahan:id,nama_perusahaan'])
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
                'cabang_nama' => $row->cabang?->nama_cabang ?? '-',
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

    public function store(StoreMasterBankRequest $request, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $payload = [
            ...$request->validated(),
            'kode_bank' => CodeGenerator::next(MasterBank::class, 'kode_bank', 'BNK'),
        ];

        return $approvalWorkflow->create('master-bank', $payload, fn (array $data) => MasterBank::create($data));
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
            ['key' => 'cabang_nama', 'label' => 'Perusahaan / Cabang'],
            ['key' => 'perumahan_nama', 'label' => 'Alokasi Proyek'],
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
            ['name' => 'cabang_id', 'label' => 'Perusahaan / Cabang', 'type' => 'select', 'optionsKey' => 'cabang', 'required' => true],
            ['name' => 'perumahan_id', 'label' => 'Alokasi Proyek (Opsional)', 'type' => 'select', 'optionsKey' => 'perumahan'],
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
            'cabang' => CabangPerusahaan::query()->finalized()
                ->orderBy('nama_cabang')
                ->get(['id', 'nama_cabang'])
                ->map(fn (CabangPerusahaan $cabang) => ['value' => (string) $cabang->id, 'label' => $cabang->nama_cabang])
                ->values(),
            'perumahan' => Perumahan::query()->finalized()
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan'])
                ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                ->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ];
    }

    protected function description(): string
    {
        return 'Kelola rekening kas dan bank milik perusahaan/cabang. Alokasi proyek hanya diisi bila rekening memang khusus untuk perumahan tertentu.';
    }
}
