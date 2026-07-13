<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $this->authorizeSupplier('view');

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        return Inertia::render('Admin/Logistik/Supplier', [
            'title' => 'Kelola Supplier',
            'baseUrl' => route('admin.supplier.index', absolute: false),
            'rows' => Supplier::query()
                ->withCount('purchases')
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_supplier', 'like', "%{$search}%")
                        ->orWhere('nama_supplier', 'like', "%{$search}%")
                        ->orWhere('pic', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (Supplier $row) => [
                    'id' => $row->id,
                    'kode_supplier' => $row->kode_supplier,
                    'nama_supplier' => $row->nama_supplier,
                    'pic' => $row->pic,
                    'phone' => $row->phone,
                    'email' => $row->email,
                    'alamat' => $row->alamat,
                    'nama_bank' => $row->nama_bank,
                    'nomor_rekening' => $row->nomor_rekening,
                    'nama_rekening' => $row->nama_rekening,
                    'npwp' => $row->npwp,
                    'catatan' => $row->catatan,
                    'status' => $row->status,
                    'purchases_count' => $row->purchases_count,
                    'record_status' => $row->record_status ?? 'draft',
                    'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'options' => [
                'status' => [
                    ['value' => '', 'label' => 'Semua Status'],
                    ['value' => 'aktif', 'label' => 'Aktif'],
                    ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                ],
            ],
            'permissions' => [
                'canCreate' => $this->canSupplier('create'),
                'canUpdate' => $this->canSupplier('update'),
                'canDelete' => $this->canSupplier('delete'),
                'canLock' => auth()->check(),
                'canUnlock' => $this->canSupplier('unlock'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSupplier('create');

        Supplier::query()->create([
            ...$this->payload($request),
            'kode_supplier' => $this->nextCode(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeSupplier('update');

        $supplier = Supplier::query()->findOrFail($id);
        $this->abortIfLocked($supplier);
        $supplier->update([
            ...$this->payload($request),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeSupplier('delete');

        $supplier = Supplier::query()->findOrFail($id);
        $this->abortIfLocked($supplier);
        $supplier->delete();

        return back()->with('success', 'Supplier berhasil dihapus.');
    }

    protected function payload(Request $request): array
    {
        return $request->validate([
            'nama_supplier' => ['required', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'nama_bank' => ['nullable', 'string', 'max:255'],
            'nomor_rekening' => ['nullable', 'string', 'max:255'],
            'nama_rekening' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }

    protected function nextCode(): string
    {
        return 'SUP-'.str_pad((string) (Supplier::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function canSupplier(string $action): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->can("supplier.{$action}")
            || $user?->can('supplier.manage')
        );
    }

    protected function authorizeSupplier(string $action): void
    {
        abort_unless($this->canSupplier($action), 403, 'Anda tidak memiliki permission supplier.');
    }

    protected function currentUserCanManageLockedRecords(): bool
    {
        return $this->canSupplier('unlock');
    }

    protected function modelClass(): string
    {
        return Supplier::class;
    }
}
