<?php

namespace App\Http\Controllers\Admin\Management\User;

use App\Http\Controllers\Admin\Management\User\Logic\UserPayload;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\CabangPerusahaan;
use App\Models\Gudang;
use App\Models\JobPosition;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use HandlesCrudLock;

    private const SECTIONS = [
        'marketing' => ['label' => 'Marketing', 'roles' => ['marketing']],
        'admin_sales' => ['label' => 'Admin Sales', 'roles' => ['admin_sales']],
        'manager' => ['label' => 'Manager', 'roles' => ['manager']],
        'manajer_pimpro' => ['label' => 'Manajer Pimpro', 'roles' => ['manajer_pimpro']],
        'pengawas' => ['label' => 'Pengawas', 'roles' => ['pengawas']],
        'keuangan' => ['label' => 'Keuangan', 'roles' => ['keuangan']],
        'gudang' => ['label' => 'Gudang', 'roles' => ['user_area_gudang']],
        'admin' => ['label' => 'Admin', 'roles' => ['admin']],
        'owner' => ['label' => 'Owner', 'roles' => ['owner']],
        'petugas' => ['label' => 'Petugas', 'roles' => ['petugas']],
        'super_admin' => ['label' => 'Super Admin', 'roles' => ['super_admin']],
        'pegawai' => ['label' => 'Daftar Pegawai', 'roles' => []],
    ];

    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'view');
        $search = trim((string) $request->query('search', ''));
        $section = $this->section($request);

        $rows = User::query()
            ->with($this->relations())
            ->when($section === 'pegawai', fn(Builder $query) => $query->whereNotNull('employee_number'))
            ->when($section !== 'pegawai', fn(Builder $query) => $query->where('has_login_access', true)->whereHas('roles', fn(Builder $roles) => $roles->whereIn('name', self::SECTIONS[$section]['roles'])))
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
            ->through(function (User $row) use ($section): array {
                $formatted = $this->formatRow($row);
                $formatted['edit_url'] = route($this->routeName() . '.edit', ['id' => $row->id, 'section' => $section], false);

                return $formatted;
            });

        return Inertia::render($this->component(), [
            'title' => $this->title(),
            'description' => $this->description(),
            'baseUrl' => route($this->routeName() . '.index', absolute: false),
            'createUrl' => route($this->routeName() . '.create', ['section' => $section], false),
            'routeName' => $this->routeName(),
            'filters' => ['search' => $search, 'section' => $section],
            'rows' => $rows,
            'columns' => $this->columns($section),
            'section' => $section,
            'tabs' => $this->tabs(),
            'statistics' => $this->statistics($section),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeAction($request, 'create');

        return $this->formResponse(null, $this->section($request));
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizeAction($request, 'update');
        $user = User::query()->with($this->relations())->findOrFail($id);
        $this->abortIfLocked($user);

        return $this->formResponse($user, $this->section($request, $this->sectionForUser($user)));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeAction($request, 'create');
        $payload = $this->payload($request);
        $section = $this->section($request);
        $payload = $this->normalizeSectionPayload($payload, $section);

        DB::transaction(function () use ($payload): void {
            $payload = $this->withJobPosition($payload);
            $user = User::create(collect($payload)->except(['role_ids', 'perumahan_ids', 'gudang_ids', 'create_employee_profile'])->toArray());
            $this->syncUserAssignments($user, $payload);
        });

        return to_route($this->routeName() . '.index', ['section' => $section])->with('success', $section === 'pegawai' ? 'Data pegawai berhasil ditambahkan.' : 'Akun ' . self::SECTIONS[$section]['label'] . ' berhasil ditambahkan.');
    }

    public function update(UpdateUserRequest $request, string $id): RedirectResponse
    {
        $this->authorizeAction($request, 'update');
        $payload = $this->payload($request);
        $section = $this->section($request);
        $payload = $this->normalizeSectionPayload($payload, $section);
        $user = User::query()->findOrFail($id);
        $this->abortIfLocked($user);

        DB::transaction(function () use ($user, $payload): void {
            $payload = $this->withJobPosition($payload);
            $user->update(collect($payload)->except(['role_ids', 'perumahan_ids', 'gudang_ids', 'create_employee_profile'])->toArray());
            $this->syncUserAssignments($user, $payload);
        });

        return to_route($this->routeName() . '.index', ['section' => $section])->with('success', $section === 'pegawai' ? 'Data pegawai berhasil diperbarui.' : 'Akun ' . self::SECTIONS[$section]['label'] . ' berhasil diperbarui.');
    }

    protected function formResponse(?User $user = null, string $section = 'marketing'): Response
    {
        $fields = $this->fields($section);
        $draftBranchCount = CabangPerusahaan::query()->where('record_status', 'draft')->count();
        $draftHousingCount = Perumahan::query()->where('record_status', 'draft')->count();
        $initialData = $user
            ? $this->formatRow($user)
            : collect($fields)->mapWithKeys(function (array $field): array {
                $value = match ($field['type'] ?? 'text') {
                    'checkboxes' => [],
                    'checkbox' => (bool) ($field['defaultValue'] ?? false),
                    default => $field['defaultValue'] ?? '',
                };

                return [$field['name'] => $value];
            })->all();
        $initialData['profile_section'] = $section;
        $initialData['create_employee_profile'] = $section === 'pegawai' || filled($user?->employee_number);
        if ($section !== 'pegawai') {
            $initialData['has_login_access'] = true;
            $initialData['employment_type'] = $initialData['employment_type'] ?? 'tetap';
            $initialData['employment_status'] = $initialData['employment_status'] ?? 'aktif';
        }

        return Inertia::render('Admin/Management/User/FormPage', [
            'title' => $user ? 'Edit ' . self::SECTIONS[$section]['label'] : 'Tambah ' . self::SECTIONS[$section]['label'],
            'description' => $section === 'pegawai'
                ? 'Kelola identitas, status kerja, BPJS, pajak, dan rekening gaji secara terpisah dari akun aplikasi.'
                : 'Kelola kredensial login dan penugasan ' . self::SECTIONS[$section]['label'] . '; role ditetapkan otomatis oleh panel ini.',
            'baseUrl' => route($this->routeName() . '.index', ['section' => $section], false),
            'actionUrl' => $user
                ? route($this->routeName() . '.update', $user->id, false)
                : route($this->routeName() . '.store', absolute: false),
            'method' => $user ? 'put' : 'post',
            'fields' => $fields,
            'options' => $this->options(),
            'optionNotices' => [
                'branches' => $draftBranchCount > 0 ? "{$draftBranchCount} cabang/perusahaan masih draft dan belum dapat dipilih." : null,
                'housings' => $draftHousingCount > 0 ? "{$draftHousingCount} perumahan/properti masih draft dan belum dapat dipilih." : null,
            ],
            'initialData' => $initialData,
            'section' => $section,
            'tabs' => $this->tabs(),
        ]);
    }

    protected function syncUserAssignments(User $user, array $payload): void
    {
        $assignmentCacheKey = 'assigned-perumahans:' . $user->id . ':' . (int) ($user->updated_at?->timestamp ?? 0);
        $roleIds = collect($payload['role_ids'] ?? [])->map(fn($id) => (int) $id)->filter()->values();
        $roles = Role::query()->whereIn('id', $roleIds)->get();
        $roleNames = $roles->pluck('name');

        $gudangIds = collect($payload['gudang_ids'] ?? [])->map(fn($id) => (int) $id)->filter()->values();
        if ($roleNames->intersect(['user_area_gudang', 'admin_gudang'])->isEmpty()) {
            $gudangIds = collect();
        }

        $user->syncRoles($roles);
        $user->update(['gudang_id' => $gudangIds->first()]);
        $user->gudangs()->sync($gudangIds->all());
        $user->perumahans()->sync(
            collect($payload['perumahan_ids'] ?? [])->map(fn($id) => (int) $id)->filter()->values()->all(),
        );
        $user->touch();
        Cache::forget($assignmentCacheKey);
    }

    protected function withJobPosition(array $payload): array
    {
        $name = Str::squish((string) ($payload['job_title'] ?? ''));
        if ($name === '') {
            return $payload;
        }
        $normalized = Str::lower($name);
        $position = JobPosition::withTrashed()->where('normalized_name', $normalized)->first();

        if (! $position) {
            $position = JobPosition::create(['name' => $name, 'normalized_name' => $normalized, 'is_active' => true]);
        } elseif ($position->trashed()) {
            $position->restore();
            $position->update(['is_active' => true]);
        }

        $payload['job_position_id'] = $position->id;
        $payload['job_title'] = $position->name;

        return $payload;
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAction($request, 'delete');
        $user = User::query()->findOrFail($id);
        $this->abortIfLocked($user);
        $user->delete();

        return back()->with('success', $this->title() . ' berhasil dihapus.');
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

    protected function permissionKey(): string
    {
        return 'users';
    }

    protected function defaultSection(): string
    {
        return 'marketing';
    }

    protected function title(): string
    {
        return 'Management User';
    }

    protected function relations(): array
    {
        return ['roles', 'kantorCabang', 'jobPosition', 'gudang', 'gudangs', 'perumahans'];
    }

    protected function columns(string $section = 'marketing'): array
    {
        if ($section !== 'pegawai') {
            return [
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'email', 'label' => 'Email Login'],
                ['key' => 'phone', 'label' => 'Telepon'],
                ['key' => 'kantor_cabang_nama', 'label' => 'Cabang'],
                ['key' => $section === 'gudang' ? 'gudang_text' : 'perumahan_text', 'label' => $section === 'gudang' ? 'Penugasan Gudang' : 'Penugasan Properti'],
                ['key' => 'roles_text', 'label' => 'Role'],
                ['key' => 'login_access_label', 'label' => 'Akses'],
            ];
        }

        return [
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'employee_number', 'label' => 'NIP/Kode Pegawai'],
            ['key' => 'job_title', 'label' => 'Jabatan'],
            ['key' => 'employment_status_label', 'label' => 'Status Pegawai'],
            ['key' => 'login_access_label', 'label' => 'Akses Login'],
            ['key' => 'phone', 'label' => 'Telepon'],
            ['key' => 'kantor_cabang_nama', 'label' => 'Cabang'],
            ['key' => 'join_date', 'label' => 'Tanggal Masuk'],
        ];
    }

    protected function fields(string $section = 'marketing'): array
    {
        if ($section !== 'pegawai') {
            $fields = [
                ['name' => 'kantor_cabang_id', 'label' => 'Perusahaan / Kantor Cabang', 'type' => 'select', 'optionsKey' => 'cabang'],
                ['name' => 'name', 'label' => 'Nama User', 'type' => 'text', 'required' => true],
                ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email Login', 'type' => 'email', 'required' => true],
                ['name' => 'password', 'label' => 'Password Login', 'type' => 'password', 'required' => true],
                ['name' => 'create_employee_profile', 'label' => 'Sekaligus buat data pegawai', 'type' => 'checkbox', 'defaultValue' => false],
                ['name' => 'employee_number', 'label' => 'NIP / Kode Pegawai', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
                ['name' => 'attendance_pin', 'label' => 'PIN Absensi (8 digit)', 'type' => 'password', 'showWhen' => 'create_employee_profile'],
                ['name' => 'job_title', 'label' => 'Jabatan', 'type' => 'creatable-select', 'optionsKey' => 'job_positions', 'showWhen' => 'create_employee_profile'],
                ['name' => 'join_date', 'label' => 'Tanggal Masuk', 'type' => 'date', 'showWhen' => 'create_employee_profile'],
                ['name' => 'employment_type', 'label' => 'Jenis Kepegawaian', 'type' => 'select', 'optionsKey' => 'employment_types', 'defaultValue' => 'tetap', 'showWhen' => 'create_employee_profile'],
                ['name' => 'employment_status', 'label' => 'Status Kepegawaian', 'type' => 'select', 'optionsKey' => 'employment_statuses', 'defaultValue' => 'aktif', 'showWhen' => 'create_employee_profile'],
                ['name' => 'tax_number', 'label' => 'NPWP', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
                ['name' => 'bpjs_health_number', 'label' => 'Nomor BPJS Kesehatan', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
                ['name' => 'bpjs_employment_number', 'label' => 'Nomor BPJS Ketenagakerjaan', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
                ['name' => 'payroll_bank_name', 'label' => 'Bank Penggajian', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
                ['name' => 'payroll_bank_account', 'label' => 'Nomor Rekening Gaji', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
                ['name' => 'payroll_bank_holder', 'label' => 'Nama Pemilik Rekening', 'type' => 'text', 'showWhen' => 'create_employee_profile'],
            ];
            if ($section === 'gudang') {
                $fields[] = ['name' => 'gudang_ids', 'label' => 'Penugasan Gudang (Multi-Select)', 'type' => 'checkboxes', 'optionsKey' => 'gudang', 'required' => true, 'full' => true];
            }
            $fields[] = ['name' => 'perumahan_ids', 'label' => 'Penugasan Multi-Perumahan / Properti', 'type' => 'checkboxes', 'optionsKey' => 'perumahan', 'full' => true];

            return $fields;
        }

        return [
            ['name' => 'kantor_cabang_id', 'label' => 'Perusahaan / Kantor Cabang', 'type' => 'select', 'optionsKey' => 'cabang'],
            ['name' => 'employee_number', 'label' => 'NIP / Kode Pegawai', 'type' => 'text'],
            ['name' => 'attendance_pin', 'label' => 'PIN Absensi (8 digit)', 'type' => 'password'],
            ['name' => 'name', 'label' => 'Nama User', 'type' => 'text', 'required' => true],
            ['name' => 'job_title', 'label' => 'Jabatan', 'type' => 'creatable-select', 'optionsKey' => 'job_positions', 'placeholder' => 'Cari atau ketik jabatan baru', 'required' => true],
            ['name' => 'join_date', 'label' => 'Tanggal Masuk', 'type' => 'date', 'required' => true],
            ['name' => 'employment_type', 'label' => 'Jenis Kepegawaian', 'type' => 'select', 'optionsKey' => 'employment_types', 'defaultValue' => 'tetap', 'required' => true],
            ['name' => 'employment_status', 'label' => 'Status Kepegawaian', 'type' => 'select', 'optionsKey' => 'employment_statuses', 'defaultValue' => 'aktif', 'required' => true],
            ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text', 'required' => true],
            ['name' => 'tax_number', 'label' => 'NPWP', 'type' => 'text'],
            ['name' => 'bpjs_health_number', 'label' => 'Nomor BPJS Kesehatan', 'type' => 'text'],
            ['name' => 'bpjs_employment_number', 'label' => 'Nomor BPJS Ketenagakerjaan', 'type' => 'text'],
            ['name' => 'payroll_bank_name', 'label' => 'Bank Penggajian', 'type' => 'text'],
            ['name' => 'payroll_bank_account', 'label' => 'Nomor Rekening Gaji', 'type' => 'text'],
            ['name' => 'payroll_bank_holder', 'label' => 'Nama Pemilik Rekening', 'type' => 'text'],
            ['name' => 'perumahan_ids', 'label' => 'Penugasan Perumahan / Properti', 'type' => 'checkboxes', 'optionsKey' => 'perumahan', 'full' => true],
        ];
    }

    protected function searchableColumns(): array
    {
        return ['name', 'employee_number', 'job_title', 'email', 'phone'];
    }

    protected function formatRow(Model $row): array
    {
        return array_merge($row->toArray(), [
            'record_status' => $row->record_status ?? 'draft',
            'record_status_label' => ($row->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'join_date' => $row->join_date?->format('Y-m-d'),
            'job_title' => $row->jobPosition?->name ?? $row->job_title,
            'employment_status_label' => ucfirst($row->employment_status ?? 'aktif'),
            'login_access_label' => $row->has_login_access ? 'Aktif' : 'Tanpa login',
            'kantor_cabang_nama' => $row->kantorCabang?->nama_cabang,
            'gudang_id' => (string) ($row->gudang_id ?? ''),
            'gudang_nama' => $row->gudang?->nama_gudang ?? '-',
            'gudang_ids' => $row->gudangs->pluck('id')->map(fn($id) => (string) $id)->all(),
            'gudang_text' => $row->gudangs->pluck('nama_gudang')->join(', ') ?: ($row->gudang?->nama_gudang ?? '-'),
            'role_ids' => $row->roles->pluck('id')->map(fn($id) => (string) $id)->all(),
            'perumahan_ids' => $row->perumahans->pluck('id')->map(fn($id) => (string) $id)->all(),
            'perumahan_text' => $row->perumahans->pluck('nama_perusahaan')->join(', ') ?: '-',
            'roles_text' => $row->roles->pluck('name')->join(', '),
            'password' => '',
            'attendance_pin' => '',
            'edit_url' => route($this->routeName() . '.edit', $row->id, false),
        ]);
    }

    protected function options(): array
    {
        return [
            'job_positions' => JobPosition::query()->where('is_active', true)->orderBy('name')->get(['name'])->map(fn(JobPosition $position) => ['value' => $position->name, 'label' => $position->name])->values(),
            'cabang' => CabangPerusahaan::query()->finalized()
                ->orderBy('nama_cabang')
                ->get(['id', 'nama_cabang'])
                ->map(fn(CabangPerusahaan $cabang) => ['value' => (string) $cabang->id, 'label' => $cabang->nama_cabang])
                ->values(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn(Role $role) => ['value' => (string) $role->id, 'label' => $role->name])
                ->values(),
            'gudang' => Gudang::query()
                ->where('status', 'aktif')
                ->orderBy('nama_gudang')
                ->get(['id', 'nama_gudang'])
                ->map(fn(Gudang $gudang) => ['value' => (string) $gudang->id, 'label' => $gudang->nama_gudang])
                ->values(),
            'perumahan' => Perumahan::query()->finalized()->with('cabang:id,nama_cabang')
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan', 'cabang_id'])
                ->map(fn(Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan . ($perumahan->cabang ? ' — ' . $perumahan->cabang->nama_cabang : '')])
                ->values(),
            'employment_types' => [
                ['value' => 'tetap', 'label' => 'Pegawai Tetap'],
                ['value' => 'kontrak', 'label' => 'Pegawai Kontrak'],
                ['value' => 'harian', 'label' => 'Harian Lepas'],
                ['value' => 'magang', 'label' => 'Magang'],
            ],
            'employment_statuses' => [
                ['value' => 'aktif', 'label' => 'Aktif'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
                ['value' => 'resign', 'label' => 'Resign'],
            ],
        ];
    }

    private function section(Request $request, string $fallback = 'marketing'): string
    {
        if ($this->defaultSection() === 'pegawai') {
            return 'pegawai';
        }

        $section = (string) $request->input('profile_section', $request->query('section', $fallback));

        if ($section === 'pegawai') {
            return $this->defaultSection();
        }

        return array_key_exists($section, self::SECTIONS) ? $section : $fallback;
    }

    private function sectionForUser(User $user): string
    {
        if (! $user->has_login_access) {
            return 'pegawai';
        }

        foreach (array_keys(self::SECTIONS) as $section) {
            if ($section === 'pegawai') {
                continue;
            }
            if ($user->hasAnyRole(self::SECTIONS[$section]['roles'])) {
                return $section;
            }
        }

        return filled($user->employee_number) ? 'pegawai' : 'marketing';
    }

    private function normalizeSectionPayload(array $payload, string $section): array
    {
        if ($section === 'pegawai') {
            return [
                ...$payload,
                'has_login_access' => false,
                'email' => null,
                'password' => null,
                'role_ids' => [],
                'perumahan_ids' => $payload['perumahan_ids'] ?? [],
                'gudang_ids' => [],
            ];
        }

        $roleName = self::SECTIONS[$section]['roles'][0];
        $role = Role::findOrCreate($roleName, 'web');

        return [
            ...$payload,
            'has_login_access' => true,
            'role_ids' => [(string) $role->id],
            'gudang_ids' => $section === 'gudang' ? ($payload['gudang_ids'] ?? []) : [],
            'perumahan_ids' => $payload['perumahan_ids'] ?? [],
        ];
    }

    protected function tabs(): array
    {
        return collect(self::SECTIONS)->except('pegawai')->map(fn(array $config, string $key) => [
            'key' => $key,
            'label' => $config['label'],
            'url' => route($this->routeName() . '.index', ['section' => $key], false),
        ])->values()->all();
    }

    private function statistics(string $section): array
    {
        if ($section === 'pegawai') {
            $employees = User::query()->whereNotNull('employee_number');

            return [
                ['label' => 'Total Pegawai', 'value' => (clone $employees)->count(), 'tone' => 'blue'],
                ['label' => 'Pegawai Aktif', 'value' => (clone $employees)->where('employment_status', 'aktif')->count(), 'tone' => 'green'],
                ['label' => 'Pegawai Tetap', 'value' => (clone $employees)->where('employment_type', 'tetap')->count(), 'tone' => 'violet'],
                ['label' => 'Kontrak / Harian', 'value' => (clone $employees)->whereIn('employment_type', ['kontrak', 'harian'])->count(), 'tone' => 'amber'],
            ];
        }

        $roleCount = User::query()->where('has_login_access', true)->whereHas('roles', fn(Builder $query) => $query->whereIn('name', self::SECTIONS[$section]['roles']))->count();
        $allRoleNames = collect(self::SECTIONS)->except('pegawai')->pluck('roles')->flatten()->all();
        $totalAccounts = User::query()->where('has_login_access', true)
            ->whereHas('roles', fn(Builder $query) => $query->whereIn('name', $allRoleNames))
            ->count();

        return [
            ['label' => 'Total Akun Role', 'value' => $totalAccounts, 'tone' => 'blue'],
            ['label' => self::SECTIONS[$section]['label'], 'value' => $roleCount, 'tone' => 'green'],
            ['label' => 'Login Aktif', 'value' => User::query()->where('has_login_access', true)->count(), 'tone' => 'violet'],
            ['label' => 'Punya Cabang', 'value' => User::query()->whereNotNull('kantor_cabang_id')->count(), 'tone' => 'amber'],
            ['label' => 'Total Pegawai', 'value' => User::query()->whereNotNull('employee_number')->count(), 'tone' => 'slate'],
        ];
    }

    protected function description(): string
    {
        return 'Kelola data, role, dan penugasan properti dari satu halaman.';
    }

    protected function authorizeAction(Request $request, string $action): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can($this->permissionKey().'.'.$action)
                || $user?->can($this->permissionKey().'.manage'),
            403,
        );
    }
}
