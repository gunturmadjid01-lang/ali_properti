<?php

namespace App\Http\Controllers\Admin\Management\TipePost;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\TipePost\StoreTipePostRequest;
use App\Http\Requests\Admin\TipePost\UpdateTipePostRequest;
use App\Models\ChartOfAccount;
use App\Models\TipePost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipePostController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = TipePost::query()
            ->with(['debitAccount:id,kode_akun,nama_akun', 'creditAccount:id,kode_akun,nama_akun'])
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
            ->through(fn (TipePost $row) => [
                ...$row->toArray(),
                'debit_account_label' => $row->debitAccount ? "{$row->debitAccount->kode_akun} - {$row->debitAccount->nama_akun}" : '-',
                'credit_account_label' => $row->creditAccount ? "{$row->creditAccount->kode_akun} - {$row->creditAccount->nama_akun}" : '-',
            ]);

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

    public function store(StoreTipePostRequest $request): RedirectResponse
    {
        TipePost::create($request->validated());

        return back()->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateTipePostRequest $request, string $id): RedirectResponse
    {
        $row = TipePost::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($request->validated());

        return back()->with('success', $this->title().' berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = TipePost::query()->findOrFail($id);
        $this->abortIfLocked($row);
        abort_if($row->is_system, 422, 'Tipe post bawaan sistem tidak boleh dihapus.');
        $row->delete();

        return back()->with('success', $this->title().' berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return TipePost::class;
    }

    protected function component(): string
    {
        return 'Admin/Management/TipePost/Index';
    }

    protected function routeName(): string
    {
        return 'admin.management.tipe-post';
    }

    protected function title(): string
    {
        return 'Management Tipe Post';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'nama_post', 'label' => 'Nama Post'],
            ['key' => 'jenis', 'label' => 'Jenis'],
            ['key' => 'debit_account_label', 'label' => 'Akun Debit'],
            ['key' => 'credit_account_label', 'label' => 'Akun Kredit'],
            ['key' => 'record_status', 'label' => 'Lock'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'nama_post', 'label' => 'Nama Post', 'type' => 'text'],
            ['name' => 'jenis', 'label' => 'Jenis', 'type' => 'select', 'optionsKey' => 'postTypes'],
            ['name' => 'debit_account_id', 'label' => 'Akun Debit', 'type' => 'select', 'optionsKey' => 'accounts'],
            ['name' => 'credit_account_id', 'label' => 'Akun Kredit', 'type' => 'select', 'optionsKey' => 'accounts'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['nama_post', 'jenis', 'status'];
    }

    protected function options(): array
    {
        return [
            'postTypes' => [['value' => 'pemasukan', 'label' => 'Pemasukan'], ['value' => 'pengeluaran', 'label' => 'Pengeluaran']],
            'accounts' => ChartOfAccount::query()
                ->where('status', 'aktif')
                ->orderBy('kode_akun')
                ->get(['id', 'kode_akun', 'nama_akun'])
                ->map(fn (ChartOfAccount $account) => ['value' => (string) $account->id, 'label' => "{$account->kode_akun} - {$account->nama_akun}"])
                ->values(),
            'status' => [['value' => 'aktif', 'label' => 'Aktif'], ['value' => 'nonaktif', 'label' => 'Nonaktif']],
        ];
    }

    protected function description(): string
    {
        return 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.';
    }
}
