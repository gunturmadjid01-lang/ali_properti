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
        $this->syncRolePermissions($role, $payload['permission_ids'] ?? []);

        return back()->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateRolePermissionRequest $request, string $id): RedirectResponse
    {
        $payload = $this->payload($request);
        $role = Role::query()->findOrFail($id);
        $this->abortIfLocked($role);
        $role->update(collect($payload)->except('permission_ids')->toArray());
        $this->syncRolePermissions($role, $payload['permission_ids'] ?? []);

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

    protected function syncRolePermissions(Role $role, array $permissionIds): void
    {
        $permissions = Permission::query()
            ->whereIn('id', collect($permissionIds)->map(fn ($id) => (int) $id)->filter()->values())
            ->get();

        $role->syncPermissions($permissions);
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
        $matrix = $this->permissionMatrix();
        $roleOrder = [
            'super_admin' => 1,
            'owner' => 2,
            'manajer_pimpro' => 3,
            'admin' => 4,
            'keuangan' => 5,
            'pengawas' => 6,
            'marketing' => 7,
        ];
        $roleLabels = [
            'super_admin' => 'Super Admin',
            'owner' => 'Owner',
            'manajer_pimpro' => 'Pimpro',
            'admin' => 'Admin',
            'keuangan' => 'Keuangan',
            'pengawas' => 'Pengawas',
            'marketing' => 'Marketing',
        ];
        collect($matrix)
            ->flatMap(fn (array $group) => $group['modules'])
            ->flatMap(fn (array $module) => $module['permissions'])
            ->each(fn (array $permission) => Permission::findOrCreate($permission['name'], 'web'));

        return [
            'roles' => Role::query()
                ->with('permissions:id,name')
                ->get()
                ->sortBy(fn (Role $role) => sprintf('%03d-%s', $roleOrder[$role->name] ?? 999, $role->name))
                ->map(fn (Role $role) => [
                    'value' => (string) $role->id,
                    'label' => $roleLabels[$role->name] ?? $role->name,
                    'id' => $role->id,
                    'name' => $role->name,
                    'record_status' => $role->record_status ?? 'draft',
                    'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                ])
                ->values(),
            'permissions' => Permission::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Permission $permission) => ['value' => (string) $permission->id, 'label' => $permission->name])
                ->values(),
            'permissionMatrix' => $this->permissionMatrixWithIds($matrix),
        ];
    }

    protected function permissionMatrixWithIds(array $matrix): array
    {
        $permissionIds = Permission::query()
            ->whereIn(
                'name',
                collect($matrix)
                    ->flatMap(fn (array $group) => $group['modules'])
                    ->flatMap(fn (array $module) => $module['permissions'])
                    ->pluck('name')
                    ->all(),
            )
            ->pluck('id', 'name');

        return collect($matrix)->map(function (array $group) use ($permissionIds) {
            $group['modules'] = collect($group['modules'])->map(function (array $module) use ($permissionIds) {
                $module['permissions'] = collect($module['permissions'])->map(fn (array $permission) => [
                    ...$permission,
                    'id' => (string) $permissionIds[$permission['name']],
                ])->values()->all();

                return $module;
            })->values()->all();

            return $group;
        })->values()->all();
    }

    protected function permissionMatrix(): array
    {
        $actions = [
            ['key' => 'view', 'label' => 'Buka'],
            ['key' => 'create', 'label' => 'Tambah'],
            ['key' => 'update', 'label' => 'Edit'],
            ['key' => 'delete', 'label' => 'Hapus'],
            ['key' => 'manage', 'label' => 'Manage'],
            ['key' => 'unlock', 'label' => 'Unlock'],
        ];

        $module = fn (string $key, string $label, array $allowed = []) => [
            'key' => $key,
            'label' => $label,
            'permissions' => collect($actions)
                ->filter(fn (array $action) => empty($allowed) || in_array($action['key'], $allowed, true))
                ->map(fn (array $action) => [
                    'action' => $action['key'],
                    'label' => $action['label'],
                    'name' => $key.'.'.$action['key'],
                ])
                ->values()
                ->all(),
        ];

        return [
            [
                'key' => 'master',
                'label' => 'Master Data',
                'modules' => [
                    $module('dashboard', 'Dashboard', ['view']),
                    $module('users', 'Users'),
                    $module('roles', 'Role Permission', ['view', 'create', 'update', 'delete', 'unlock']),
                    $module('cabang', 'Cabang Perusahaan'),
                    $module('perumahan', 'Perumahan'),
                    $module('detail-rumah', 'Kapling / Unit'),
                    $module('dokumen-legalitas', 'Dokumen Legalitas'),
                    $module('dokumen-customer', 'Dokumen Customer'),
                    $module('master-bank', 'Master Bank', ['view', 'create', 'update', 'delete', 'unlock']),
                    $module('tipe-post', 'Tipe Post', ['view', 'create', 'update', 'delete', 'unlock']),
                    $module('kelompok-hpp', 'Kelompok HPP', ['view', 'create', 'update', 'delete', 'unlock']),
                ],
            ],
            [
                'key' => 'approval',
                'label' => 'Approval',
                'modules' => [
                    [
                        'key' => 'approval-dashboard',
                        'label' => 'Daftar Approval',
                        'permissions' => [
                            ['action' => 'view', 'label' => 'Buka', 'name' => 'approval.view'],
                        ],
                    ],
                    [
                        'key' => 'approval-settings',
                        'label' => 'Setting Approval',
                        'permissions' => [
                            ['action' => 'manage', 'label' => 'Manage', 'name' => 'approval.settings'],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'project',
                'label' => 'Proyek',
                'modules' => [
                    $module('progress', 'Progress Pembangunan'),
                    $module('site-schedule', 'Jadwal Lapangan'),
                    $module('site-report', 'Laporan Lapangan'),
                    $module('quality-inspection', 'Kontrol Kualitas'),
                    $module('field-supervision', 'Pengawasan Lapangan'),
                ],
            ],
            [
                'key' => 'project-finance',
                'label' => 'Keuangan Proyek',
                'modules' => [
                    $module('spk-kontraktor', 'SPK Kontraktor'),
                    [
                        'key' => 'spk-payment',
                        'label' => 'Pembayaran SPK',
                        'permissions' => [
                            ['action' => 'view', 'label' => 'Buka Halaman', 'name' => 'spk-payment.view'],
                            ['action' => 'create', 'label' => 'Ajukan Pembayaran', 'name' => 'spk-payment.create'],
                            ['action' => 'update', 'label' => 'Catat Pembayaran', 'name' => 'spk-payment.update'],
                        ],
                    ],
                    $module('kpr', 'KPR'),
                    $module('kpr-akad', 'Akad KPR'),
                    $module('handover-customer', 'Serah Terima'),
                    $module('refund-spr', 'Refund SPR'),
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Penjualan',
                'modules' => [
                    $module('customer', 'Data Customer'),
                    $module('customer-follow-up', 'Follow Up Customer'),
                    $module('booking', 'SPR'),
                    $module('spr-payment', 'Pembayaran SPR'),
                    $module('kpr', 'KPR'),
                    $module('kpr-akad', 'Akad KPR'),
                    $module('handover-customer', 'Serah Terima'),
                    $module('unit-stock', 'Unit Available', ['view']),
                    $module('pricelist', 'Pricelist Aktif', ['view']),
                    $module('payment-simulation', 'Simulasi Pembayaran', ['view']),
                    $module('refund-spr', 'Refund SPR'),
                ],
            ],
            [
                'key' => 'warehouse',
                'label' => 'Gudang & Logistik',
                'modules' => [
                    $module('master-material', 'Master Material'),
                    $module('site-material-stock', 'Stok Material', ['view']),
                    $module('material-request', 'Permintaan Material'),
                    $module('material-purchase', 'Permintaan Pembelian Gudang'),
                    $module('material-usage', 'Pemakaian Material'),
                    $module('material-return', 'Pengembalian Material'),
                    $module('asset-inventory', 'Inventaris Aset', ['view', 'create', 'update', 'delete', 'unlock']),
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Keuangan & Akuntansi',
                'modules' => [
                    $module('bank-account-ledger', 'Mutasi & Saldo Rekening', ['view']),
                    $module('buku-besar', 'Buku Besar', ['view']),
                    $module('neraca-saldo', 'Neraca Saldo', ['view']),
                    $module('laba-rugi', 'Laba Rugi', ['view']),
                    $module('neraca', 'Neraca', ['view']),
                    $module('arus-kas', 'Arus Kas', ['view']),
                    $module('piutang', 'Piutang Customer', ['view']),
                    $module('hutang', 'Hutang Supplier & Kontraktor', ['view']),
                ],
            ],
            [
                'key' => 'marketing',
                'label' => 'Marketing',
                'modules' => [
                    $module('marketing.lead-source', 'Sumber Lead', ['manage']),
                    $module('marketing.lead-report', 'Laporan Lead', ['view']),
                    $module('marketing.pipeline', 'Pipeline Semua Marketing', ['view']),
                    $module('marketing.pipeline-report', 'Laporan Pipeline', ['view']),
                    $module('marketing.campaign', 'Campaign & Promosi', ['manage']),
                    $module('marketing.reminder', 'Reminder Follow Up', ['manage']),
                    $module('marketing.document-review', 'Validasi Berkas', ['manage']),
                    $module('marketing.lead-distribution', 'Distribusi Lead', ['manage']),
                    $module('marketing.activity', 'Monitoring Aktivitas', ['view']),
                    $module('marketing.target-commission', 'Target KPI & Komisi', ['manage']),
                    $module('marketing.leaderboard', 'Leaderboard Sales', ['view']),
                    $module('marketing.receivable', 'Tagihan & Kwitansi', ['view']),
                    $module('marketing.template', 'Template Komunikasi', ['manage']),
                    $module('marketing.performance', 'Dashboard Performa', ['view']),
                ],
            ],
        ];
    }

    protected function description(): string
    {
        return 'Kelola data, cari cepat, edit, dan hapus dari satu halaman.';
    }
}
