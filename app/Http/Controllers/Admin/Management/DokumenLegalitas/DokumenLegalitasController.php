<?php

namespace App\Http\Controllers\Admin\Management\DokumenLegalitas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\DokumenLegalitas\StoreDokumenLegalitasRequest;
use App\Http\Requests\Admin\DokumenLegalitas\UpdateDokumenLegalitasRequest;
use App\Models\DokumenLegalitas;
use App\Models\Perumahan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenLegalitasController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = DokumenLegalitas::query()
            ->with($this->relations())
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    foreach ($this->searchableColumns() as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (DokumenLegalitas $row) => $this->formatRow($row));

        return Inertia::render($this->component(), [
            'title' => $this->title(),
            'description' => $this->description(),
            'baseUrl' => route($this->routeName().'.index', absolute: false),
            'routeName' => $this->routeName(),
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreDokumenLegalitasRequest $request): RedirectResponse
    {
        DokumenLegalitas::create($request->validated());

        return back()->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateDokumenLegalitasRequest $request, string $id): RedirectResponse
    {
        $row = DokumenLegalitas::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($request->validated());

        return back()->with('success', $this->title().' berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = DokumenLegalitas::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', $this->title().' berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return DokumenLegalitas::class;
    }

    protected function component(): string
    {
        return 'Admin/Management/DokumenLegalitas/Index';
    }

    protected function routeName(): string
    {
        return 'admin.management.dokumen-legalitas';
    }

    protected function title(): string
    {
        return 'Management Dokumen Legalitas';
    }

    protected function relations(): array
    {
        return ['perumahan'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'perumahan_nama', 'label' => 'Perumahan'],
            ['key' => 'nama_dokument', 'label' => 'Nama Dokumen'],
            ['key' => 'nomor_dokument', 'label' => 'Nomor Dokumen'],
            ['key' => 'tanggal_terbit', 'label' => 'Terbit'],
            ['key' => 'tanggal_berakhir', 'label' => 'Berakhir'],
            ['key' => 'record_status', 'label' => 'Lock'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahan'],
            ['name' => 'nama_dokument', 'label' => 'Nama Dokumen', 'type' => 'text'],
            ['name' => 'nomor_dokument', 'label' => 'Nomor Dokumen', 'type' => 'text'],
            ['name' => 'tanggal_terbit', 'label' => 'Tanggal Terbit', 'type' => 'date'],
            ['name' => 'tanggal_berakhir', 'label' => 'Tanggal Berakhir', 'type' => 'date'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
            ['name' => 'file', 'label' => 'File', 'type' => 'text', 'full' => true],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['nama_dokument', 'nomor_dokument', 'status'];
    }

    protected function formatRow(Model $row): array
    {
        return array_merge($row->toArray(), [
            'perumahan_nama' => $row->perumahan?->nama_perusahaan,
            'tanggal_terbit' => optional($row->tanggal_terbit)->format('Y-m-d'),
            'tanggal_berakhir' => optional($row->tanggal_berakhir)->format('Y-m-d'),
        ]);
    }

    protected function options(): array
    {
        return [
            'perumahan' => Perumahan::query()
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan'])
                ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                ->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'expired', 'label' => 'Expired'], ['value' => 'proses', 'label' => 'Proses']],
        ];
    }

    protected function description(): string
    {
        return 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.';
    }
}
