<?php

namespace App\Http\Controllers\Admin\Management\User;

use App\Http\Controllers\Admin\Management\User\Logic\UserPayload;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\CabangPerusahaan;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = User::query()
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
            ->through(fn (User $row) => $this->formatRow($row));

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

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $payload = $this->payload($request);

        DB::transaction(function () use ($payload): void {
            $user = User::create(collect($payload)->except(['role_ids', 'perumahan_ids'])->toArray());
            $this->syncUserAssignments($user, $payload);
        });

        return back()->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateUserRequest $request, string $id): RedirectResponse
    {
        $payload = $this->payload($request);
        $user = User::query()->findOrFail($id);
        $this->abortIfLocked($user);

        DB::transaction(function () use ($user, $payload): void {
            $user->update(collect($payload)->except(['role_ids', 'perumahan_ids'])->toArray());
            $this->syncUserAssignments($user, $payload);
        });

        return back()->with('success', $this->title().' berhasil diperbarui.');
    }

    protected function syncUserAssignments(User $user, array $payload): void
    {
        $roleIds = collect($payload['role_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $roles = Role::query()->whereIn('id', $roleIds)->get();

        $user->syncRoles($roles);
        $user->perumahans()->sync(
            collect($payload['perumahan_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
        );
    }

    public function destroy(string $id): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $this->abortIfLocked($user);
        $user->delete();

        return back()->with('success', $this->title().' berhasil dihapus.');
    }

    protected function payload(FormRequest $request, ?Model $row = null): array
    {
        return app(UserPayload::class)->fromRequest($request);
    }

    protected function modelClass(): string
    {
        return User::class;
    }

    protected function component(): string
    {
        return 'Admin/Management/User/Index';
    }

    protected function routeName(): string
    {
        return 'admin.management.user';
    }

    protected function title(): string
    {
        return 'Management User';
    }

    protected function relations(): array
    {
        return ['roles', 'kantorCabang', 'perumahans'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Telepon'],
            ['key' => 'kantor_cabang_nama', 'label' => 'Cabang'],
            ['key' => 'perumahan_text', 'label' => 'Properti'],
            ['key' => 'roles_text', 'label' => 'Role'],
            ['key' => 'record_status_label', 'label' => 'Lock'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'kantor_cabang_id', 'label' => 'Kantor Cabang', 'type' => 'select', 'optionsKey' => 'cabang'],
            ['name' => 'name', 'label' => 'Nama User', 'type' => 'text'],
            ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'perumahan_ids', 'label' => 'Penugasan Properti', 'type' => 'checkboxes', 'optionsKey' => 'perumahan', 'full' => true],
            ['name' => 'role_ids', 'label' => 'Role', 'type' => 'checkboxes', 'optionsKey' => 'roles', 'full' => true],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['name', 'email', 'phone'];
    }

    protected function formatRow(Model $row): array
    {
        return array_merge($row->toArray(), [
            'record_status' => $row->record_status ?? 'draft',
            'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'kantor_cabang_nama' => $row->kantorCabang?->nama_cabang,
            'role_ids' => $row->roles->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'perumahan_ids' => $row->perumahans->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'perumahan_text' => $row->perumahans->pluck('nama_perusahaan')->join(', ') ?: '-',
            'roles_text' => $row->roles->pluck('name')->join(', '),
            'password' => '',
        ]);
    }

    protected function options(): array
    {
        return [
            'cabang' => CabangPerusahaan::query()
                ->orderBy('nama_cabang')
                ->get(['id', 'nama_cabang'])
                ->map(fn (CabangPerusahaan $cabang) => ['value' => (string) $cabang->id, 'label' => $cabang->nama_cabang])
                ->values(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role) => ['value' => (string) $role->id, 'label' => $role->name])
                ->values(),
            'perumahan' => Perumahan::query()
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan'])
                ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                ->values(),
        ];
    }

    protected function description(): string
    {
        return 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.';
    }
}
