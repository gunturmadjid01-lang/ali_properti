<?php

namespace App\Http\Controllers\Admin\Management\Overview;

use App\Http\Controllers\Controller;
use App\Models\CabangPerusahaan;
use App\Models\DokumenLegalitas;
use App\Models\DokumenLegalitasRumah;
use App\Models\KelompokHpp;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\TipePost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ManagementOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        $sections = [];

        foreach ($this->sections() as $key => $section) {
            $sections[] = $this->buildSection($request, $key, $section);
        }

        return Inertia::render('Admin/Management/Overview/Index', [
            'title' => 'Management Master Data',
            'description' => 'Kelola seluruh data master perusahaan dari satu halaman. Setiap section memiliki tabel server-side, search, pagination, dan form create/edit di bawahnya.',
            'overviewUrl' => route('admin.management.overview', absolute: false),
            'sections' => $sections,
        ]);
    }

    protected function buildSection(Request $request, string $key, array $section): array
    {
        $searchKey = $section['searchKey'] ?? "{$key}_search";
        $pageKey = $section['pageKey'] ?? "{$key}_page";
        $search = trim((string) $request->query($searchKey, ''));

        $query = ($section['model'])::query();

        $relations = $section['relations'] ?? [];
        if (! empty($relations)) {
            $query->with($relations);
        }

        $this->applySearch($query, $search, $section['searchableColumns'] ?? []);

        $rows = $query
            ->latest('id')
            ->paginate(10, ['*'], $pageKey)
            ->withQueryString()
            ->through(fn (Model $row) => $this->formatRowForSection($row, $section));

        $options = [];
        if (isset($section['options']) && is_callable($section['options'])) {
            $options = ($section['options'])();
        }

        return [
            'key' => $key,
            'title' => $section['title'],
            'description' => $section['description'] ?? '',
            'baseUrl' => route($section['routeName'].'.index', absolute: false),
            'searchKey' => $searchKey,
            'pageKey' => $pageKey,
            'defaultOpen' => $section['defaultOpen'] ?? false,
            'filters' => ['search' => $search],
            'columns' => $section['columns'],
            'fields' => $section['fields'],
            'options' => $options,
            'rows' => $rows,
        ];
    }

    protected function applySearch(Builder $query, string $search, array $columns): void
    {
        if ($search === '' || empty($columns)) {
            return;
        }

        $query->where(function (Builder $query) use ($columns, $search) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    protected function formatRowForSection(Model $row, array $section): array
    {
        if (isset($section['formatRow']) && is_callable($section['formatRow'])) {
            return ($section['formatRow'])($row);
        }

        return $row->toArray();
    }

    protected function sections(): array
    {
        return [
            'cabang-perusahaan' => [
                'title' => 'Management Cabang Perusahaan',
                'description' => 'Data cabang, kontak, status, dan identitas lokasi.',
                'defaultOpen' => true,
                'routeName' => 'admin.management.cabang-perusahaan',
                'model' => CabangPerusahaan::class,
                'columns' => [
                    ['key' => 'kode_cabang', 'label' => 'Kode'],
                    ['key' => 'nama_cabang', 'label' => 'Nama Cabang'],
                    ['key' => 'phone', 'label' => 'Telepon'],
                    ['key' => 'emaiil', 'label' => 'Email'],
                    ['key' => 'manager_name', 'label' => 'Manager'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'nama_cabang', 'label' => 'Nama Cabang', 'type' => 'text'],
                    ['name' => 'emaiil', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
                    ['name' => 'manager_name', 'label' => 'Nama Manager', 'type' => 'text'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                    ['name' => 'type', 'label' => 'Tipe', 'type' => 'select', 'optionsKey' => 'branchTypes'],
                    ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'text'],
                    ['name' => 'longtitude', 'label' => 'Longitude', 'type' => 'text'],
                    ['name' => 'logo', 'label' => 'Logo', 'type' => 'image'],
                    ['name' => 'image', 'label' => 'Foto Kantor Cabang', 'type' => 'image'],
                    ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'full' => true],
                    ['name' => 'deskripsi', 'label' => 'Deskripsi', 'type' => 'textarea', 'full' => true],
                ],
                'searchableColumns' => ['kode_cabang', 'nama_cabang', 'phone', 'emaiil', 'manager_name', 'status'],
                'options' => fn () => [
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                    ],
                    'branchTypes' => [
                        ['value' => 'pusat', 'label' => 'Pusat'],
                        ['value' => 'cabang', 'label' => 'Cabang'],
                    ],
                ],
            ],
            'perumahan' => [
                'title' => 'Management Perumahan',
                'description' => 'Daftar proyek perumahan beserta cabang, luas lahan, dan status.',
                'routeName' => 'admin.management.perumahan',
                'model' => Perumahan::class,
                'relations' => ['cabang'],
                'columns' => [
                    ['key' => 'cabang_nama', 'label' => 'Cabang'],
                    ['key' => 'nama_perusahaan', 'label' => 'Nama Perumahan'],
                    ['key' => 'luas_lahan', 'label' => 'Luas Lahan'],
                    ['key' => 'jumlah_unit', 'label' => 'Unit'],
                    ['key' => 'tanggal_mulai', 'label' => 'Tanggal Mulai'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'cabang_id', 'label' => 'Cabang Perusahaan', 'type' => 'select', 'optionsKey' => 'cabang'],
                    ['name' => 'nama_perusahaan', 'label' => 'Nama Perumahan', 'type' => 'text'],
                    ['name' => 'luas_lahan', 'label' => 'Luas Lahan', 'type' => 'text'],
                    ['name' => 'jumlah_unit', 'label' => 'Jumlah Unit', 'type' => 'number'],
                    ['name' => 'tanggal_mulai', 'label' => 'Tanggal Mulai', 'type' => 'date'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                    ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'text'],
                    ['name' => 'longtitude', 'label' => 'Longitude', 'type' => 'text'],
                    ['name' => 'logo', 'label' => 'Logo', 'type' => 'image'],
                    ['name' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'full' => true],
                ],
                'searchableColumns' => ['nama_perusahaan', 'alamat', 'luas_lahan', 'status'],
                'formatRow' => fn (Model $row) => array_merge($row->toArray(), [
                    'cabang_nama' => $row->cabang?->nama_cabang,
                    'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                ]),
                'options' => fn () => [
                    'cabang' => CabangPerusahaan::query()
                        ->orderBy('nama_cabang')
                        ->get(['id', 'nama_cabang'])
                        ->map(fn (CabangPerusahaan $cabang) => ['value' => (string) $cabang->id, 'label' => $cabang->nama_cabang])
                        ->values(),
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                    ],
                ],
            ],
            'master-bank' => [
                'title' => 'Management Master Bank',
                'description' => 'Data rekening bank yang dipakai dalam transaksi dan laporan.',
                'routeName' => 'admin.management.master-bank',
                'model' => MasterBank::class,
                'columns' => [
                    ['key' => 'kode_bank', 'label' => 'Kode Bank'],
                    ['key' => 'perumahan_nama', 'label' => 'Perumahan'],
                    ['key' => 'nama_bank', 'label' => 'Nama Bank'],
                    ['key' => 'nomor_rekening', 'label' => 'Nomor Rekening'],
                    ['key' => 'nama_rekening', 'label' => 'Nama Rekening'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahan'],
                    ['name' => 'nama_bank', 'label' => 'Nama Bank', 'type' => 'text'],
                    ['name' => 'nomor_rekening', 'label' => 'Nomor Rekening', 'type' => 'text'],
                    ['name' => 'nama_rekening', 'label' => 'Nama Rekening', 'type' => 'text'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                ],
                'relations' => ['perumahan'],
                'searchableColumns' => ['kode_bank', 'nama_bank', 'nomor_rekening', 'nama_rekening', 'status'],
                'formatRow' => fn (Model $row) => array_merge($row->toArray(), [
                    'perumahan_nama' => $row->perumahan?->nama_perusahaan,
                ]),
                'options' => fn () => [
                    'perumahan' => Perumahan::query()
                        ->orderBy('nama_perusahaan')
                        ->get(['id', 'nama_perusahaan'])
                        ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                        ->values(),
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                    ],
                ],
            ],
            'dokumen-legalitas' => [
                'title' => 'Management Dokumen Legalitas',
                'description' => 'Dokumen legalitas yang menempel pada tiap perumahan.',
                'routeName' => 'admin.management.dokumen-legalitas',
                'model' => DokumenLegalitas::class,
                'relations' => ['perumahan'],
                'columns' => [
                    ['key' => 'perumahan_nama', 'label' => 'Perumahan'],
                    ['key' => 'nama_dokument', 'label' => 'Nama Dokumen'],
                    ['key' => 'nomor_dokument', 'label' => 'Nomor Dokumen'],
                    ['key' => 'tanggal_terbit', 'label' => 'Terbit'],
                    ['key' => 'tanggal_berakhir', 'label' => 'Berakhir'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahan'],
                    ['name' => 'nama_dokument', 'label' => 'Nama Dokumen', 'type' => 'text'],
                    ['name' => 'nomor_dokument', 'label' => 'Nomor Dokumen', 'type' => 'text'],
                    ['name' => 'tanggal_terbit', 'label' => 'Tanggal Terbit', 'type' => 'date'],
                    ['name' => 'tanggal_berakhir', 'label' => 'Tanggal Berakhir', 'type' => 'date'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                    ['name' => 'file', 'label' => 'File', 'type' => 'text', 'full' => true],
                ],
                'searchableColumns' => ['nama_dokument', 'nomor_dokument', 'status'],
                'formatRow' => fn (Model $row) => array_merge($row->toArray(), [
                    'perumahan_nama' => $row->perumahan?->nama_perusahaan,
                    'tanggal_terbit' => optional($row->tanggal_terbit)->format('Y-m-d'),
                    'tanggal_berakhir' => optional($row->tanggal_berakhir)->format('Y-m-d'),
                ]),
                'options' => fn () => [
                    'perumahan' => Perumahan::query()
                        ->orderBy('nama_perusahaan')
                        ->get(['id', 'nama_perusahaan'])
                        ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                        ->values(),
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'expired', 'label' => 'Expired'],
                        ['value' => 'proses', 'label' => 'Proses'],
                    ],
                ],
            ],
            'dokumen-legalitas-rumah' => [
                'title' => 'Management Dokumen Legalitas Rumah',
                'description' => 'Dokumen legalitas yang melekat pada unit rumah di perumahan.',
                'routeName' => 'admin.management.dokumen-legalitas-rumah',
                'model' => DokumenLegalitasRumah::class,
                'relations' => ['perumahan'],
                'columns' => [
                    ['key' => 'perumahan_nama', 'label' => 'Perumahan'],
                    ['key' => 'nama_dokumen', 'label' => 'Nama Dokumen'],
                    ['key' => 'tanggal_terbit', 'label' => 'Terbit'],
                    ['key' => 'tanggal_berakhir', 'label' => 'Berakhir'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahan'],
                    ['name' => 'nama_dokumen', 'label' => 'Nama Dokumen', 'type' => 'text'],
                    ['name' => 'tanggal_terbit', 'label' => 'Tanggal Terbit', 'type' => 'date'],
                    ['name' => 'tanggal_berakhir', 'label' => 'Tanggal Berakhir', 'type' => 'date'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                    ['name' => 'file', 'label' => 'File', 'type' => 'text', 'full' => true],
                ],
                'searchableColumns' => ['nama_dokumen', 'status'],
                'formatRow' => fn (Model $row) => array_merge($row->toArray(), [
                    'perumahan_nama' => $row->perumahan?->nama_perusahaan,
                    'tanggal_terbit' => optional($row->tanggal_terbit)->format('Y-m-d'),
                    'tanggal_berakhir' => optional($row->tanggal_berakhir)->format('Y-m-d'),
                ]),
                'options' => fn () => [
                    'perumahan' => Perumahan::query()
                        ->orderBy('nama_perusahaan')
                        ->get(['id', 'nama_perusahaan'])
                        ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
                        ->values(),
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'expired', 'label' => 'Expired'],
                        ['value' => 'proses', 'label' => 'Proses'],
                    ],
                ],
            ],
            'tipe-post' => [
                'title' => 'Management Tipe Post',
                'description' => 'Master tipe untuk transaksi pemasukan dan pengeluaran.',
                'routeName' => 'admin.management.tipe-post',
                'model' => TipePost::class,
                'columns' => [
                    ['key' => 'nama_post', 'label' => 'Nama Post'],
                    ['key' => 'jenis', 'label' => 'Jenis'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'nama_post', 'label' => 'Nama Post', 'type' => 'text'],
                    ['name' => 'jenis', 'label' => 'Jenis', 'type' => 'select', 'optionsKey' => 'postTypes'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                ],
                'searchableColumns' => ['nama_post', 'jenis', 'status'],
                'options' => fn () => [
                    'postTypes' => [
                        ['value' => 'pemasukan', 'label' => 'Pemasukan'],
                        ['value' => 'pengeluaran', 'label' => 'Pengeluaran'],
                    ],
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                    ],
                ],
            ],
            'kelompok-hpp' => [
                'title' => 'Management HPP',
                'description' => 'Master kelompok HPP standar untuk perumahan, rumah, logistik, dan realisasi biaya.',
                'routeName' => 'admin.management.kelompok-hpp',
                'model' => KelompokHpp::class,
                'columns' => [
                    ['key' => 'nama_hpp', 'label' => 'Nama HPP'],
                    ['key' => 'kategori_label', 'label' => 'Kategori'],
                    ['key' => 'record_status', 'label' => 'Lock'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'fields' => [
                    ['name' => 'nama_hpp', 'label' => 'Nama HPP', 'type' => 'text'],
                    ['name' => 'kategori', 'label' => 'Kategori', 'type' => 'select', 'optionsKey' => 'categories'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'status'],
                ],
                'searchableColumns' => ['nama_hpp', 'kategori', 'status'],
                'options' => fn () => [
                    'categories' => [
                        ['value' => 'tanah', 'label' => 'Tanah'],
                        ['value' => 'legalitas', 'label' => 'Perizinan & Persuratan'],
                        ['value' => 'bangunan', 'label' => 'Konstruksi'],
                        ['value' => 'tenaga_kerja', 'label' => 'Konstruksi - Tenaga Kerja'],
                        ['value' => 'material', 'label' => 'Logistik'],
                        ['value' => 'infrastruktur', 'label' => 'Utilitas'],
                        ['value' => 'marketing', 'label' => 'Pemasaran'],
                        ['value' => 'operasional', 'label' => 'Operasional'],
                        ['value' => 'keuangan', 'label' => 'Keuangan'],
                        ['value' => 'cadangan', 'label' => 'Cadangan'],
                    ],
                    'status' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                    ],
                ],
            ],
            'user' => [
                'title' => 'Management User',
                'description' => 'Kelola akun pengguna, cabang, dan role yang terhubung.',
                'routeName' => 'admin.management.user',
                'model' => User::class,
                'relations' => ['roles', 'kantorCabang'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'phone', 'label' => 'Telepon'],
                    ['key' => 'kantor_cabang_nama', 'label' => 'Cabang'],
                    ['key' => 'roles_text', 'label' => 'Role'],
                ],
                'fields' => [
                    ['name' => 'kantor_cabang_id', 'label' => 'Kantor Cabang', 'type' => 'select', 'optionsKey' => 'cabang'],
                    ['name' => 'name', 'label' => 'Nama User', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
                    ['name' => 'role_ids', 'label' => 'Role', 'type' => 'checkboxes', 'optionsKey' => 'roles', 'full' => true],
                ],
                'searchableColumns' => ['name', 'email', 'phone'],
                'formatRow' => fn (Model $row) => array_merge($row->toArray(), [
                    'kantor_cabang_nama' => $row->kantorCabang?->nama_cabang,
                    'role_ids' => $row->roles->pluck('id')->map(fn ($id) => (string) $id)->all(),
                    'roles_text' => $row->roles->pluck('name')->join(', '),
                    'password' => '',
                ]),
                'options' => fn () => [
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
                ],
            ],
            'role-permission' => [
                'title' => 'Management Role & Permission',
                'description' => 'Atur role pengguna sekaligus hak akses permission yang menempel.',
                'routeName' => 'admin.management.role-permission',
                'model' => Role::class,
                'relations' => ['permissions'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Role'],
                    ['key' => 'guard_name', 'label' => 'Guard'],
                    ['key' => 'permissions_count', 'label' => 'Jumlah Permission'],
                    ['key' => 'permissions_text', 'label' => 'Permission'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Role', 'type' => 'text'],
                    ['name' => 'permission_ids', 'label' => 'Permission', 'type' => 'checkboxes', 'optionsKey' => 'permissions', 'full' => true],
                ],
                'searchableColumns' => ['name', 'guard_name'],
                'formatRow' => fn (Model $row) => array_merge($row->toArray(), [
                    'permission_ids' => $row->permissions->pluck('id')->map(fn ($id) => (string) $id)->all(),
                    'permissions_count' => $row->permissions->count(),
                    'permissions_text' => $row->permissions->pluck('name')->join(', '),
                ]),
                'options' => fn () => [
                    'permissions' => Permission::query()
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn (Permission $permission) => ['value' => (string) $permission->id, 'label' => $permission->name])
                        ->values(),
                ],
            ],
        ];
    }
}
