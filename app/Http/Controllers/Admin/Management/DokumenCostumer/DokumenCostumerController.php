<?php

namespace App\Http\Controllers\Admin\Management\DokumenCostumer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\DokumenCostumer\StoreDokumenCostumerRequest;
use App\Http\Requests\Admin\DokumenCostumer\UpdateDokumenCostumerRequest;
use App\Models\DokumenCostumer;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenCostumerController extends Controller
{
    use HandlesCrudLock;

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
            ]);

        return Inertia::render('Admin/Management/DokumenCostumer/Index', [
            'title' => 'Master Dokumen Customer',
            'description' => 'Kelola daftar jenis dokumen yang harus diupload customer saat proses SPR atau KPR.',
            'baseUrl' => route('admin.management.master-dokumen-customer.index', absolute: false),
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
            'fields' => [
                ['name' => 'nama_dokumen', 'label' => 'Nama Dokumen', 'type' => 'text'],
                ['name' => 'kategori_pengajuan', 'label' => 'Kategori Pengajuan', 'type' => 'select', 'optionsKey' => 'categoryOptions'],
                ['name' => 'wajib', 'label' => 'Status Upload', 'type' => 'select', 'optionsKey' => 'requiredOptions'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'statusOptions'],
                ['name' => 'keterangan', 'label' => 'Keterangan', 'type' => 'textarea', 'full' => true],
            ],
            'options' => [
                'categoryOptions' => $this->categoryOptions(),
                'requiredOptions' => [
                    ['value' => '1', 'label' => 'Wajib'],
                    ['value' => '0', 'label' => 'Opsional'],
                ],
                'statusOptions' => [
                    ['value' => 'aktif', 'label' => 'Aktif'],
                    ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                ],
            ],
        ]);
    }

    public function store(StoreDokumenCostumerRequest $request): RedirectResponse
    {
        DokumenCostumer::create([
            ...$request->validated(),
            'kode_dokumen' => CodeGenerator::next(DokumenCostumer::class, 'kode_dokumen', 'DOK'),
        ]);

        return back()->with('success', 'Master dokumen customer berhasil ditambahkan.');
    }

    public function update(UpdateDokumenCostumerRequest $request, string $id): RedirectResponse
    {
        $row = DokumenCostumer::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($request->validated());

        return back()->with('success', 'Master dokumen customer berhasil diperbarui.');
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

    protected function categoryOptions(): array
    {
        return [
            ['value' => 'umum', 'label' => 'Umum'],
            ['value' => 'spr', 'label' => 'SPR'],
            ['value' => 'kpr', 'label' => 'KPR'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bertahap', 'label' => 'Bertahap'],
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
