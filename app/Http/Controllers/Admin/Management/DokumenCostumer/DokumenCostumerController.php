<?php

namespace App\Http\Controllers\Admin\Management\DokumenCostumer;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\RendersSeparatedManagementForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DokumenCostumer\StoreDokumenCostumerRequest;
use App\Http\Requests\Admin\DokumenCostumer\UpdateDokumenCostumerRequest;
use App\Models\DokumenCostumer;
use App\Services\ApprovalWorkflowService;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenCostumerController extends Controller
{
    use HandlesCrudLock, RendersSeparatedManagementForm;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = DokumenCostumer::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_dokumen', 'like', "%{$search}%")
                        ->orWhere('nama_dokumen', 'like', "%{$search}%")
                        ->orWhere('kategori_pengajuan', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DokumenCostumer $row) => [
                'id' => $row->id,
                'kode_dokumen' => $row->kode_dokumen,
                'nama_dokumen' => $row->nama_dokumen,
                'kategori_pengajuan' => $row->kategori_pengajuan,
                'kategori_label' => $this->labelFromOptions($row->kategori_pengajuan, $this->categoryOptions()),
                'wajib' => $row->wajib ? 'Wajib' : 'Opsional',
                'wajib_value' => $row->wajib ? '1' : '0',
                'keterangan' => $row->keterangan,
                'status' => $row->status,
                'status_label' => ucfirst($row->status),
                'record_status' => $row->record_status,
                'record_status_label' => $row->record_status === 'locked' ? 'Locked' : 'Draft',
                'edit_url' => route('admin.management.master-dokumen-customer.edit', $row->id, false),
            ]);

        return Inertia::render('Admin/Management/DokumenCostumer/Index', [
            'title' => $this->title(),
            'description' => $this->description(),
            'baseUrl' => route('admin.management.master-dokumen-customer.index', absolute: false),
            'createUrl' => route('admin.management.master-dokumen-customer.create', absolute: false),
            'permissionKey' => 'dokumen-customer',
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => [
                ['key' => 'kode_dokumen', 'label' => 'Kode'],
                ['key' => 'nama_dokumen', 'label' => 'Nama Dokumen'],
                ['key' => 'kategori_label', 'label' => 'Kategori'],
                ['key' => 'wajib', 'label' => 'Upload'],
                ['key' => 'record_status_label', 'label' => 'Lock'],
                ['key' => 'status_label', 'label' => 'Status'],
            ],
        ]);
    }

    public function store(StoreDokumenCostumerRequest $request, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $payload = [
            ...$request->validated(),
            'kode_dokumen' => CodeGenerator::next(DokumenCostumer::class, 'kode_dokumen', 'DOK'),
        ];

        return $approvalWorkflow->create('dokumen-customer', $payload, fn (array $data) => DokumenCostumer::create($data));
    }

    public function update(UpdateDokumenCostumerRequest $request, string $id): RedirectResponse
    {
        $row = DokumenCostumer::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($request->validated());

        return to_route('admin.management.master-dokumen-customer.index')->with('success', 'Master dokumen customer berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = DokumenCostumer::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Master dokumen customer berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return DokumenCostumer::class;
    }

    protected function routeName(): string
    {
        return 'admin.management.master-dokumen-customer';
    }

    protected function title(): string
    {
        return 'Master Dokumen Pelanggan';
    }

    protected function description(): string
    {
        return 'Kelola dokumen dasar SPR dan dokumen tambahan untuk Cash Bertahap, KPR Bank, atau KPR Developer.';
    }

    protected function fields(): array
    {
        return [
            ['name' => 'nama_dokumen', 'label' => 'Nama Dokumen', 'type' => 'text', 'required' => true],
            ['name' => 'kategori_pengajuan', 'label' => 'Kategori Pengajuan', 'type' => 'select', 'optionsKey' => 'categoryOptions', 'required' => true],
            ['name' => 'wajib', 'label' => 'Status Upload', 'type' => 'select', 'optionsKey' => 'requiredOptions', 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'statusOptions', 'required' => true],
            ['name' => 'keterangan', 'label' => 'Keterangan', 'type' => 'textarea', 'full' => true],
        ];
    }

    protected function options(): array
    {
        return [
            'categoryOptions' => $this->categoryOptions(),
            'requiredOptions' => [['value' => '1', 'label' => 'Wajib'], ['value' => '0', 'label' => 'Opsional']],
            'statusOptions' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ];
    }

    protected function categoryOptions(): array
    {
        return [
            ['value' => 'spr', 'label' => 'SPR'],
            ['value' => 'cash_bertahap', 'label' => 'Cash Bertahap'],
            ['value' => 'kpr_bank', 'label' => 'KPR Bank'],
            ['value' => 'kpr_developer', 'label' => 'KPR Developer'],
        ];
    }

    protected function labelFromOptions(?string $value, array $options): string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '-';
    }
}
