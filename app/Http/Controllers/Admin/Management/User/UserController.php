<?php

namespace App\Http\Controllers\Admin\Management\User;

use App\Http\Controllers\Admin\Management\User\Logic\UserPayload;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
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
use Illuminate\Support\Str;
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
            'createUrl' => route($this->routeName().'.create', absolute: false),
            'routeName' => $this->routeName(),
            'filters' => ['search' => $search],
            'rows' => $rows,
            'columns' => $this->columns(),
        ]);
    }

    public function create(): Response
    {
        return $this->formResponse();
    }

    public function edit(string $id): Response
    {
        $user = User::query()->with($this->relations())->findOrFail($id);
        $this->abortIfLocked($user);

        return $this->formResponse($user);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $payload = $this->payload($request);

        DB::transaction(function () use ($payload): void {
            $payload = $this->withJobPosition($payload);
            $user = User::create(collect($payload)->except(['role_ids', 'perumahan_ids', 'gudang_ids'])->toArray());
            $this->syncUserAssignments($user, $payload);
        });

        return to_route($this->routeName().'.index')->with('success', $this->title().' berhasil ditambahkan.');
    }

    public function update(UpdateUserRequest $request, string $id): RedirectResponse
    {
        $payload = $this->payload($request);
        $user = User::query()->findOrFail($id);
        $this->abortIfLocked($user);

        DB::transaction(function () use ($user, $payload): void {
            $payload = $this->withJobPosition($payload);
            $user->update(collect($payload)->except(['role_ids', 'perumahan_ids', 'gudang_ids'])->toArray());
            $this->syncUserAssignments($user, $payload);
        });

        return to_route($this->routeName().'.index')->with('success', $this->title().' berhasil diperbarui.');
    }

    protected function formResponse(?User $user = null): Response
    {
        $fields = $this->fields();
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

        return Inertia::render('Admin/Management/User/FormPage', [
            'title' => $user ? 'Edit User' : 'Tambah User',
            'description' => $user
                ? 'Perbarui data pegawai, akses sistem, penggajian, dan penugasannya.'
                : 'Lengkapi data pegawai, akses sistem, penggajian, dan penugasannya.',
            'baseUrl' => route($this->routeName().'.index', absolute: false),
            'actionUrl' => $user
                ? route($this->routeName().'.update', $user->id, false)
                : route($this->routeName().'.store', absolute: false),
            'method' => $user ? 'put' : 'post',
            'fields' => $fields,
            'options' => $this->options(),
            'initialData' => $initialData,
        ]);
    }

    protected function syncUserAssignments(User $user, array $payload): void
    {
        $roleIds = collect($payload['role_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $roles = Role::query()->whereIn('id', $roleIds)->get();
        $roleNames = $roles->pluck('name');

        $gudangIds = collect($payload['gudang_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($roleNames->intersect(['user_area_gudang', 'admin_gudang'])->isEmpty()) {
            $gudangIds = collect();
        }

        $user->syncRoles($roles);
        $user->update(['gudang_id' => $gudangIds->first()]);
        $user->gudangs()->sync($gudangIds->all());
        $user->perumahans()->sync(
            collect($payload['perumahan_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
        );
    }

    protected function withJobPosition(array $payload): array
    {
        $name = Str::squish((string) ($payload['job_title'] ?? ''));
        $normalized = Str::lower($name);
        $position = JobPosition::withTrashed()->where('normalized_name', $normalized)->first();

        if (! $position) {
            $position = JobPosition::create(['name'=>$name, 'normalized_name'=>$normalized, 'is_active'=>true]);
        } elseif ($position->trashed()) {
            $position->restore();
            $position->update(['is_active'=>true]);
        }

        $payload['job_position_id'] = $position->id;
        $payload['job_title'] = $position->name;

        return $payload;
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
        return ['roles', 'kantorCabang', 'jobPosition', 'gudang', 'gudangs', 'perumahans'];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nama'],
            ['key' => 'employee_number', 'label' => 'NIP/Kode Pegawai'],
            ['key' => 'job_title', 'label' => 'Jabatan'],
            ['key' => 'employment_status_label', 'label' => 'Status Pegawai'],
            ['key' => 'login_access_label', 'label' => 'Akses Login'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'phone', 'label' => 'Telepon'],
            ['key' => 'kantor_cabang_nama', 'label' => 'Cabang'],
            ['key' => 'gudang_text', 'label' => 'Gudang'],
            ['key' => 'perumahan_text', 'label' => 'Properti'],
            ['key' => 'roles_text', 'label' => 'Role'],
            ['key' => 'record_status_label', 'label' => 'Lock'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'kantor_cabang_id', 'label' => 'Kantor Cabang', 'type' => 'select', 'optionsKey' => 'cabang'],
            ['name' => 'employee_number', 'label' => 'NIP / Kode Pegawai', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Nama User', 'type' => 'text', 'required' => true],
            ['name' => 'job_title', 'label' => 'Jabatan', 'type' => 'creatable-select', 'optionsKey' => 'job_positions', 'placeholder' => 'Cari atau ketik jabatan baru', 'required' => true],
            ['name' => 'join_date', 'label' => 'Tanggal Masuk', 'type' => 'date', 'required' => true],
            ['name' => 'employment_type', 'label' => 'Jenis Kepegawaian', 'type' => 'select', 'optionsKey' => 'employment_types', 'defaultValue' => 'tetap', 'required' => true],
            ['name' => 'employment_status', 'label' => 'Status Kepegawaian', 'type' => 'select', 'optionsKey' => 'employment_statuses', 'defaultValue' => 'aktif', 'required' => true],
            ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text', 'required' => true],
            ['name' => 'has_login_access', 'label' => 'Buatkan Akses Login', 'type' => 'checkbox', 'defaultValue' => true, 'full' => true],
            ['name' => 'email', 'label' => 'Email Login', 'type' => 'email', 'showWhen' => 'has_login_access', 'required' => true],
            ['name' => 'password', 'label' => 'Password Login', 'type' => 'password', 'showWhen' => 'has_login_access', 'required' => true],
            ['name' => 'tax_number', 'label' => 'NPWP', 'type' => 'text'],
            ['name' => 'bpjs_health_number', 'label' => 'Nomor BPJS Kesehatan', 'type' => 'text'],
            ['name' => 'bpjs_employment_number', 'label' => 'Nomor BPJS Ketenagakerjaan', 'type' => 'text'],
            ['name' => 'payroll_bank_name', 'label' => 'Bank Penggajian', 'type' => 'text'],
            ['name' => 'payroll_bank_account', 'label' => 'Nomor Rekening Gaji', 'type' => 'text'],
            ['name' => 'payroll_bank_holder', 'label' => 'Nama Pemilik Rekening', 'type' => 'text'],
            ['name' => 'gudang_ids', 'label' => 'Penugasan Gudang', 'type' => 'checkboxes', 'optionsKey' => 'gudang', 'full' => true],
            ['name' => 'perumahan_ids', 'label' => 'Penugasan Properti', 'type' => 'checkboxes', 'optionsKey' => 'perumahan', 'full' => true],
            ['name' => 'role_ids', 'label' => 'Role', 'type' => 'checkboxes', 'optionsKey' => 'roles', 'full' => true],
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
            'gudang_ids' => $row->gudangs->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'gudang_text' => $row->gudangs->pluck('nama_gudang')->join(', ') ?: ($row->gudang?->nama_gudang ?? '-'),
            'role_ids' => $row->roles->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'perumahan_ids' => $row->perumahans->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'perumahan_text' => $row->perumahans->pluck('nama_perusahaan')->join(', ') ?: '-',
            'roles_text' => $row->roles->pluck('name')->join(', '),
            'password' => '',
            'edit_url' => route($this->routeName().'.edit', $row->id, false),
        ]);
    }

    protected function options(): array
    {
        return [
            'job_positions' => JobPosition::query()->where('is_active', true)->orderBy('name')->get(['name'])->map(fn (JobPosition $position) => ['value'=>$position->name, 'label'=>$position->name])->values(),
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
            'gudang' => Gudang::query()
                ->orderBy('nama_gudang')
                ->get(['id', 'nama_gudang'])
                ->map(fn (Gudang $gudang) => ['value' => (string) $gudang->id, 'label' => $gudang->nama_gudang])
                ->values(),
            'perumahan' => Perumahan::query()
                ->orderBy('nama_perusahaan')
                ->get(['id', 'nama_perusahaan'])
                ->map(fn (Perumahan $perumahan) => ['value' => (string) $perumahan->id, 'label' => $perumahan->nama_perusahaan])
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

    protected function description(): string
    {
        return 'Kelola data, role, dan penugasan properti dari satu halaman.';
    }
}
