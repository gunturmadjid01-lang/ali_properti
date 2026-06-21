<?php

namespace App\Http\Controllers\Admin\Management\RolePermission;

use App\Http\Controllers\Admin\Management\RolePermission\Logic\RolePermissionPayload;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Requests\Admin\RolePermission\StoreRolePermissionRequest;
use App\Http\Requests\Admin\RolePermission\UpdateRolePermissionRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = Role::query()
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
            ->through(fn (Role $row) => $this->formatRow($row));

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

    public function store(StoreRolePermissionRequest $request): RedirectResponse
    {
        $payload = $this->payload($request);
        $role = Role::create(collect($payload)->except('permission_ids')->toArray());
        $role->syncPermissions($payload['permission_ids'] ?? []);

        return back()->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateRolePermissionRequest $request, string $id): RedirectResponse
    {
        $payload = $this->payload($request);
        $role = Role::query()->findOrFail($id);
        $this->abortIfLocked($role);
        $role->update(collect($payload)->except('permission_ids')->toArray());
        $role->syncPermissions($payload['permission_ids'] ?? []);

        return back()->with('success', $this->title().' berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $role = Role::query()->findOrFail($id);
        $this->abortIfLocked($role);
        $role->delete();

        return back()->with('success', $this->title().' berhasil dihapus.');
    }

    protected function payload(FormRequest $request, ?Model $row = null): array
    {
        return app(RolePermissionPayload::class)->fromRequest($request);
    }

    protected function modelClass(): string
    {
        return Role::class;
    }

    protected function component(): string
    {
        return 'Admin/Management/RolePermission/Index';
    }

    protected function routeName(): string
    {
        return 'admin.management.role-permission';
    }

    protected function title(): string
    {
        return 'Management Role & Permission';
    }

    protected function relations(): array
    {
        return ['permissions'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Role'],
            ['key' => 'guard_name', 'label' => 'Guard'],
            ['key' => 'permissions_count', 'label' => 'Jumlah Permission'],
            ['key' => 'permissions_text', 'label' => 'Permission'],
            ['key' => 'record_status_label', 'label' => 'Lock'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama Role', 'type' => 'text'],
            ['name' => 'permission_ids', 'label' => 'Permission', 'type' => 'checkboxes', 'optionsKey' => 'permissions', 'full' => true],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['name', 'guard_name'];
    }

    protected function formatRow(Model $row): array
    {
        return array_merge($row->toArray(), [
            'record_status' => $row->record_status ?? 'draft',
            'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'permission_ids' => $row->permissions->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'permissions_count' => $row->permissions->count(),
            'permissions_text' => $row->permissions->pluck('name')->join(', '),
        ]);
    }

    protected function options(): array
    {
        return [
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Permission $permission) => ['value' => (string) $permission->id, 'label' => $permission->name])
                ->values(),
        ];
    }

    protected function description(): string
    {
        return 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.';
    }
}
