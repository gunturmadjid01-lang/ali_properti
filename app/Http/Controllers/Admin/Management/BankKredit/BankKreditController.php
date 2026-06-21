<?php

namespace App\Http\Controllers\Admin\Management\BankKredit;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\BankKredit;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankKreditController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = BankKredit::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where('kode_bank', 'like', "%{$search}%")
                    ->orWhere('nama_bank', 'like', "%{$search}%")
                    ->orWhere('nama_pic', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Management/BankKredit/Index', [
            'title' => 'Management Bank Kredit',
            'description' => 'Kelola bank yang bekerja sama untuk proses kredit atau KPR customer.',
            'baseUrl' => route('admin.management.bank-kredit.index', absolute: false),
            'routeName' => 'admin.management.bank-kredit',
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => [
                ['key' => 'kode_bank', 'label' => 'Kode Bank'],
                ['key' => 'nama_bank', 'label' => 'Nama Bank'],
                ['key' => 'nama_pic', 'label' => 'PIC'],
                ['key' => 'telepon_pic', 'label' => 'Telepon PIC'],
                ['key' => 'record_status', 'label' => 'Lock'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'fields' => [
                ['name' => 'nama_bank', 'label' => 'Nama Bank', 'type' => 'text'],
                ['name' => 'nama_pic', 'label' => 'Nama PIC', 'type' => 'text'],
                ['name' => 'telepon_pic', 'label' => 'Telepon PIC', 'type' => 'text'],
                ['name' => 'email_pic', 'label' => 'Email PIC', 'type' => 'email'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
            ],
            'options' => [
                'status' => [
                    ['value' => 'aktif', 'label' => 'Aktif'],
                    ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BankKredit::create([
            ...$this->validated($request),
            'kode_bank' => CodeGenerator::next(BankKredit::class, 'kode_bank', 'KRD'),
        ]);

        return back()->with('success', 'Bank kredit berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $row = BankKredit::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update($this->validated($request));

        return back()->with('success', 'Bank kredit berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $row = BankKredit::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Bank kredit berhasil dihapus.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'nama_bank' => ['required', 'string', 'max:255'],
            'nama_pic' => ['nullable', 'string', 'max:255'],
            'telepon_pic' => ['nullable', 'string', 'max:255'],
            'email_pic' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);
    }

    protected function modelClass(): string
    {
        return BankKredit::class;
    }
}
