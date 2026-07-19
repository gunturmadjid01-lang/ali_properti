<?php

namespace App\Http\Controllers\Admin\Management\RolePermission;

use App\Http\Controllers\Admin\Management\RolePermission\Logic\RolePermissionPayload;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermission\StoreRolePermissionRequest;
use App\Http\Requests\Admin\RolePermission\UpdateRolePermissionRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        app(PermissionRegistrar::class)->forgetCachedPermissions();
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
            ['name' => 'name', 'label' => 'Nama Role', 'type' => 'text', 'required' => true],
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
            'manager' => 3,
            'manajer_pimpro' => 4,
            'admin' => 5,
            'petugas' => 6,
            'keuangan' => 7,
            'pengawas' => 8,
            'marketing' => 9,
        ];
        $roleLabels = [
            'super_admin' => 'Super Admin',
            'owner' => 'Owner',
            'manager' => 'Manager',
            'manajer_pimpro' => 'Pimpro',
            'admin' => 'Admin',
            'petugas' => 'Petugas',
            'keuangan' => 'Keuangan',
            'pengawas' => 'Pengawas',
            'marketing' => 'Marketing',
        ];
        $permissionNames = collect($matrix)
            ->flatMap(fn (array $group) => $group['modules'])
            ->flatMap(fn (array $module) => $module['permissions'])
            ->pluck('name')
            ->unique()
            ->values();

        $now = now();
        DB::table('permissions')->insertOrIgnore(
            $permissionNames->map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
            ['key' => 'manage', 'label' => 'Kelola'],
            ['key' => 'unlock', 'label' => 'Buka Finalisasi'],
            ['key' => 'lock', 'label' => 'Finalisasi'],
            ['key' => 'export', 'label' => 'Ekspor'],
            ['key' => 'verify', 'label' => 'Verifikasi'],
            ['key' => 'approve', 'label' => 'Persetujuan'],
            ['key' => 'print', 'label' => 'Cetak'],
            ['key' => 'submit', 'label' => 'Ajukan'],
            ['key' => 'reject', 'label' => 'Tolak'],
            ['key' => 'reverse', 'label' => 'Reversal'],
            ['key' => 'settings', 'label' => 'Pengaturan'],
        ];

        $module = fn (string $key, string $label, array $allowed = []) => [
            'key' => $key,
            'label' => $label,
            'permissions' => collect($actions)
                ->filter(fn (array $action) => in_array(
                    $action['key'],
                    $allowed ?: ['view', 'create', 'update', 'delete', 'manage', 'unlock'],
                    true
                ))
                ->map(fn (array $action) => [
                    'action' => $action['key'],
                    'label' => $action['label'],
                    'name' => $key.'.'.$action['key'],
                ])
                ->values()
                ->all(),
        ];

        $integratedSalesLabels = [
            'sales' => ['transactions' => 'Daftar Transaksi Penjualan', 'transaction-detail' => 'Detail Transaksi Penjualan', 'payment-schedules' => 'Jadwal dan Tagihan', 'payments' => 'Pembayaran Pelanggan', 'handover' => 'Serah Terima Unit', 'after-sales' => 'Layanan Purnajual', 'reports' => 'Laporan Penjualan'],
            'cash-installment' => ['schemes' => 'Skema Cash Bertahap', 'scheme-detail' => 'Detail Skema Cash Bertahap', 'scheme-housing' => 'Perumahan Pengguna Skema', 'scheme-steps' => 'Tahapan Angsuran', 'scheme-fees' => 'Biaya dan Denda', 'scheme-requirements' => 'Persyaratan Pelanggan', 'scheme-documents' => 'Dokumen Wajib', 'scheme-versions' => 'Versi Skema', 'scheme-history' => 'Riwayat Perubahan Skema', 'scheme-reports' => 'Laporan Skema', 'contracts' => 'Kontrak Cash Bertahap', 'contract-detail' => 'Detail Kontrak', 'approvals' => 'Persetujuan Kontrak', 'schedules' => 'Jadwal Angsuran', 'billings' => 'Monitoring Tagihan', 'arrears' => 'Monitoring Tunggakan', 'payment-history' => 'Riwayat Pembayaran', 'settlements' => 'Pelunasan', 'restructuring' => 'Restrukturisasi', 'cancellations' => 'Pembatalan Kontrak', 'reports' => 'Laporan Cash Bertahap'],
            'developer-kpr' => ['products' => 'Produk KPR Developer', 'product-detail' => 'Detail Produk', 'product-housing' => 'Perumahan Pengguna Produk', 'financing-terms' => 'Ketentuan Pembiayaan', 'margins' => 'Margin Pembiayaan', 'fees' => 'Biaya Pembiayaan', 'requirements' => 'Persyaratan Pelanggan', 'documents' => 'Dokumen Wajib', 'risk-approval' => 'Persetujuan dan Batas Risiko', 'penalties' => 'Denda dan Masa Tenggang', 'early-settlement' => 'Pelunasan Dipercepat', 'product-versions' => 'Versi Produk', 'product-history' => 'Riwayat Produk', 'product-reports' => 'Laporan Produk', 'applications' => 'Pengajuan KPR Developer', 'application-detail' => 'Detail Pengajuan', 'affordability-analysis' => 'Analisis Kemampuan Bayar', 'document-validation' => 'Validasi Dokumen', 'internal-approval' => 'Persetujuan Internal', 'contracts' => 'Kontrak KPR Developer', 'schedules' => 'Jadwal Angsuran', 'receivables' => 'Piutang KPR Developer', 'arrears' => 'Tunggakan KPR Developer', 'payments' => 'Pembayaran KPR Developer', 'restructuring' => 'Restrukturisasi', 'cancellations' => 'Pembatalan', 'reports' => 'Laporan KPR Developer'],
            'bank-kpr' => ['applications' => 'Pengajuan KPR Bank', 'application-detail' => 'Detail Pengajuan KPR Bank', 'document-validation' => 'Validasi Dokumen Bank', 'slik' => 'Pemeriksaan SLIK', 'appraisal' => 'Penilaian Agunan', 'bank-decision' => 'Keputusan Bank', 'sp3k' => 'Surat Persetujuan Kredit', 'financing' => 'Struktur Pembiayaan', 'contract-preparation' => 'Persiapan Akad', 'contract-schedule' => 'Jadwal Akad', 'contract-execution' => 'Pelaksanaan Akad', 'disbursement' => 'Pencairan Dana', 'bank-change' => 'Perubahan Bank', 'rejections' => 'Penolakan Pengajuan', 'reports' => 'Laporan KPR Bank'],
        ];
        $processStages = [
            'cash-installment' => ['contracts', 'contract-detail', 'approvals', 'schedules', 'billings', 'arrears', 'payment-history', 'settlements', 'restructuring', 'cancellations'],
            'developer-kpr' => ['applications', 'application-detail', 'affordability-analysis', 'document-validation', 'internal-approval', 'contracts', 'schedules', 'receivables', 'arrears', 'payments', 'restructuring', 'cancellations'],
            'bank-kpr' => ['applications', 'application-detail', 'document-validation', 'slik', 'appraisal', 'bank-decision', 'sp3k', 'financing', 'contract-preparation', 'contract-schedule', 'contract-execution', 'disbursement', 'bank-change', 'rejections'],
        ];
        $processLabel = function (string $prefix, string $page) use ($integratedSalesLabels, $processStages): string {
            $label = $integratedSalesLabels[$prefix][$page];
            $position = array_search($page, $processStages[$prefix] ?? [], true);

            return $position === false ? $label : 'Tahap '.($position + 1).' — '.$label;
        };
        $integratedSalesModules = collect([
            'sales' => ['transactions', 'transaction-detail', 'payment-schedules', 'payments', 'handover', 'after-sales', 'reports'],
            'cash-installment' => ['schemes', 'scheme-detail', 'scheme-housing', 'scheme-steps', 'scheme-fees', 'scheme-requirements', 'scheme-documents', 'scheme-versions', 'scheme-history', 'scheme-reports', 'contracts', 'contract-detail', 'approvals', 'schedules', 'billings', 'arrears', 'payment-history', 'settlements', 'restructuring', 'cancellations', 'reports'],
            'developer-kpr' => ['products', 'product-detail', 'product-housing', 'financing-terms', 'margins', 'fees', 'requirements', 'documents', 'risk-approval', 'penalties', 'early-settlement', 'product-versions', 'product-history', 'product-reports', 'applications', 'application-detail', 'affordability-analysis', 'document-validation', 'internal-approval', 'contracts', 'schedules', 'receivables', 'arrears', 'payments', 'restructuring', 'cancellations', 'reports'],
            'bank-kpr' => ['applications', 'application-detail', 'document-validation', 'slik', 'appraisal', 'bank-decision', 'sp3k', 'financing', 'contract-preparation', 'contract-schedule', 'contract-execution', 'disbursement', 'bank-change', 'rejections', 'reports'],
        ])->flatMap(fn (array $pages, string $prefix) => collect($pages)->map(
            fn (string $page) => $module($prefix.'.'.$page, $processLabel($prefix, $page), ['view', 'create', 'update', 'delete', 'submit', 'approve', 'reject', 'print', 'export'])
        ))->values()->merge([
            $module('sales.transaction-detail.summary', 'Detail Transaksi - Ringkasan', ['view']),
            $module('sales.transaction-detail.schedules', 'Detail Transaksi - Jadwal & Tagihan', ['view']),
            $module('sales.transaction-detail.payments', 'Detail Transaksi - Pembayaran', ['view']),
            $module('sales.transaction-detail.construction', 'Detail Transaksi - Pembangunan', ['view']),
            $module('sales.transaction-detail.handover', 'Detail Transaksi - Serah Terima', ['view']),
            $module('sales.transaction-detail.after-sales', 'Detail Transaksi - Layanan Purnajual', ['view']),
            $module('sales.transaction-detail.history', 'Detail Transaksi - Riwayat', ['view']),
        ])->values()->all();

        return [
            [
                'key' => 'access',
                'label' => 'Pengguna & Akses',
                'modules' => [
                    $module('dashboard', 'Dashboard', ['view']),
                    $module('users', 'Users'),
                    $module('roles', 'Role Permission', ['view', 'create', 'update', 'delete', 'unlock']),
                ],
            ],
            [
                'key' => 'employees',
                'label' => 'Pegawai & Absensi',
                'modules' => [
                    $module('attendance', 'Absensi & Pengaturan Jam', ['view', 'settings']),
                    $module('payroll', 'Penggajian Pegawai', ['view', 'manage']),
                ],
            ],
            [
                'key' => 'company-property',
                'label' => 'Perusahaan & Properti',
                'modules' => [
                    $module('cabang', 'Cabang Perusahaan'),
                    $module('perumahan', 'Perumahan'),
                    $module('detail-rumah', 'Kapling / Unit'),
                    $module('unit-ownership', 'Data Pemilik Unit', ['view', 'create', 'update', 'delete', 'unlock']),
                ],
            ],
            [
                'key' => 'documents-reference',
                'label' => 'Dokumen & Referensi',
                'modules' => [
                    $module('dokumen-legalitas', 'Dokumen Legalitas'),
                    $module('dokumen-customer', 'Dokumen Customer'),
                    $module('document-template', 'Dokumen Baku Penjualan', ['view']),
                    $module('master-bank', 'Master Bank', ['view', 'create', 'update', 'delete', 'unlock']),
                    $module('tipe-post', 'Tipe Post', ['view', 'create', 'update', 'delete', 'unlock']),
                    $module('tukang', 'Daftar Tukang', ['view', 'create', 'update', 'delete']),
                ],
            ],
            [
                'key' => 'bank-credit',
                'label' => 'Master Kredit Bank',
                'modules' => [
                    $module('bank-credit-master', 'Master Bank Kredit', ['view', 'create', 'update', 'delete']),
                    $module('bank-branch', 'Cabang Bank', ['view', 'create', 'update', 'delete', 'submit', 'approve', 'reject']),
                    $module('bank-credit-product', 'Produk Kredit Bank', ['view', 'create', 'update', 'delete', 'submit', 'approve', 'reject']),
                    $module('bank-housing-partnership', 'Kerja Sama Bank dan Perumahan', ['view', 'create', 'update', 'delete', 'submit', 'approve', 'reject']),
                    $module('bank-document-requirement', 'Paket Persyaratan Dokumen', ['view', 'create', 'update', 'delete']),
                    $module('bank-partnership-history', 'Riwayat / Versi Kerja Sama', ['view']),
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
                'label' => 'Perencanaan & Pembangunan',
                'modules' => [
                    $module('rab-perumahan', 'Management RAB Perumahan', ['view', 'create', 'update', 'delete', 'manage']),
                    $module('rab-unit', 'Management RAB Unit Rumah', ['view', 'create', 'update', 'delete', 'manage']),
                    $module('material-group', 'Kelompok Material', ['view', 'create', 'update', 'delete']),
                    $module('progress', 'Progress Pembangunan'),
                    $module('site-schedule', 'Jadwal Lapangan'),
                    $module('site-report', 'Laporan Lapangan'),
                    $module('quality-inspection', 'Kontrol Kualitas'),
                    $module('field-supervision', 'Pengawasan Lapangan'),
                ],
            ],
            [
                'key' => 'contracts-spk',
                'label' => 'Kontrak & SPK',
                'modules' => [
                    $module('spk-kontraktor', 'SPK Kontraktor'),
                    $module('spk-template-perumahan', 'Template Pekerjaan SPK Perumahan'),
                    $module('spk-template-unit', 'Template Pekerjaan SPK Unit'),
                    [
                        'key' => 'spk-payment',
                        'label' => 'Pembayaran SPK',
                        'permissions' => [
                            ['action' => 'view', 'label' => 'Buka Halaman', 'name' => 'spk-payment.view'],
                            ['action' => 'create', 'label' => 'Ajukan Pembayaran', 'name' => 'spk-payment.create'],
                            ['action' => 'update', 'label' => 'Catat Pembayaran', 'name' => 'spk-payment.update'],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'sales',
                'label' => 'Prospek & Transaksi',
                'modules' => [
                    $module('customer', 'Data Customer'),
                    $module('customer-follow-up', 'Follow Up Customer'),
                    $module('housing-reservation', 'Reservasi Perumahan', ['view', 'create', 'update', 'delete', 'lock', 'print']),
                    $module('booking', 'SPR'),
                    $module('unit-stock', 'Unit Available', ['view']),
                    $module('pricelist', 'Pricelist Aktif', ['view']),
                    $module('payment-simulation', 'Simulasi Pembayaran', ['view']),
                    $module('sales-process', 'Proses Penjualan sampai Huni', ['view', 'update', 'lock', 'unlock', 'print']),
                ],
            ],
            [
                'key' => 'warehouse',
                'label' => 'Gudang & Logistik',
                'modules' => [
                    $module('master-material', 'Master Material'),
                    $module('material-opening-balance', 'Saldo Awal Material', ['view', 'create', 'update', 'delete', 'unlock']),
                    $module('supplier', 'Supplier'),
                    $module('site-material-stock', 'Stok Material', ['view']),
                    $module('material-stock-opname', 'Stock Opname Material'),
                    $module('material-request', 'Permintaan Material'),
                    $module('material-purchase', 'Pembelian Material'),
                    $module('material-usage', 'Pemakaian Material'),
                    $module('material-return', 'Pengembalian Material'),
                    $module('company-inventory.dashboard', 'Inventaris - Dashboard', ['view']),
                    $module('company-inventory.categories', 'Inventaris - Kategori Barang'),
                    $module('company-inventory.items', 'Inventaris - Data Barang'),
                    $module('company-inventory.units', 'Inventaris - Unit Aset'),
                    $module('company-inventory.locations', 'Inventaris - Lokasi'),
                    $module('company-inventory.receipts', 'Inventaris - Penerimaan/Penambahan', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('company-inventory.loans', 'Inventaris - Pengambilan/Penyerahan', ['view', 'create', 'export', 'approve', 'print']),
                    $module('company-inventory.returns', 'Inventaris - Pengembalian', ['view', 'create', 'export', 'approve', 'print']),
                    $module('company-inventory.transfers', 'Inventaris - Mutasi', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('company-inventory.damages', 'Inventaris - Barang Rusak', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('company-inventory.losses', 'Inventaris - Barang Hilang', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('company-inventory.stock-opname', 'Inventaris - Stock Opname', ['view', 'create', 'update', 'delete', 'export', 'verify', 'approve', 'print']),
                    $module('company-inventory.ledger', 'Inventaris - Kartu Pergerakan', ['view', 'export']),
                    $module('company-inventory.reports', 'Inventaris - Laporan', ['view', 'export', 'print']),
                    $module('heavy-equipment.dashboard', 'Alat Berat - Dashboard', ['view']),
                    $module('heavy-equipment.equipment', 'Alat Berat - Data Alat'),
                    $module('heavy-equipment.types', 'Alat Berat - Jenis Alat'),
                    $module('heavy-equipment.components', 'Alat Berat - Komponen'),
                    $module('heavy-equipment.replacements', 'Alat Berat - Penggantian Komponen', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('heavy-equipment.usage', 'Alat Berat - Penggunaan', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('heavy-equipment.operators', 'Alat Berat - Operator'),
                    $module('heavy-equipment.maintenance', 'Alat Berat - Perawatan', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('heavy-equipment.damages', 'Alat Berat - Kerusakan', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('heavy-equipment.fuel', 'Alat Berat - Pengisian BBM', ['view', 'create', 'update', 'delete', 'export', 'approve', 'print']),
                    $module('heavy-equipment.reports', 'Alat Berat - Laporan', ['view', 'export', 'print']),
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
                    $module('receivables', 'Daftar Piutang Terintegrasi', ['view', 'print', 'settings']),
                    $module('customer-receipts', 'Penerimaan Customer', ['view', 'create', 'update', 'lock', 'unlock', 'print']),
                    $module('customer-charges', 'Tagihan & Talangan Customer', ['view', 'create', 'update', 'lock', 'unlock', 'print', 'reverse']),
                    $module('customer-refunds', 'Refund Booking Fee & Uang Muka', ['view', 'update', 'lock', 'unlock', 'print']),
                    $module('hutang', 'Hutang Supplier & Kontraktor', ['view']),
                ],
            ],
            [
                'key' => 'reports',
                'label' => 'Laporan',
                'modules' => [
                    [
                        'key' => 'laporan',
                        'label' => 'Pusat Laporan',
                        'permissions' => [
                            ['action' => 'view', 'label' => 'Buka', 'name' => 'laporan.view'],
                            ['action' => 'export', 'label' => 'Cetak/Export', 'name' => 'laporan.export'],
                        ],
                    ],
                    $module('laporan-master-data', 'Laporan Master Data', ['view']),
                    $module('laporan-pembelian', 'Laporan Pembelian', ['view']),
                    $module('laporan-persediaan-material', 'Laporan Persediaan Material', ['view']),
                    $module('laporan-marketing', 'Laporan Marketing', ['view']),
                ],
            ],
            [
                'key' => 'integrated-sales',
                'label' => 'Transaksi Penjualan Terintegrasi',
                'modules' => collect($integratedSalesModules)->filter(fn (array $item) => str_starts_with($item['key'], 'sales.'))->values()->all(),
            ],
            [
                'key' => 'cash-installment',
                'label' => 'Tunai Bertahap',
                'modules' => collect($integratedSalesModules)->filter(fn (array $item) => str_starts_with($item['key'], 'cash-installment.'))->values()->all(),
            ],
            [
                'key' => 'developer-kpr',
                'label' => 'KPR Developer',
                'modules' => collect($integratedSalesModules)->filter(fn (array $item) => str_starts_with($item['key'], 'developer-kpr.'))->values()->all(),
            ],
            [
                'key' => 'bank-kpr',
                'label' => 'Proses KPR Bank',
                'modules' => collect($integratedSalesModules)->filter(fn (array $item) => str_starts_with($item['key'], 'bank-kpr.'))->values()->all(),
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
