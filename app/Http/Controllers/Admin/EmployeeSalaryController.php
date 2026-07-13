<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeSalaryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $rows = EmployeeSalary::query()
            ->with(['user.kantorCabang'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EmployeeSalary $salary) => $this->formatRow($salary));

        return Inertia::render('Admin/EmployeeSalary/Index', [
            'title' => 'Pengaturan Gaji Pegawai',
            'baseUrl' => route('admin.employee-salaries.index', absolute: false),
            'createUrl' => route('admin.employee-salaries.create', absolute: false),
            'filters' => ['search' => $search, 'status' => $status],
            'rows' => $rows,
            'employees' => [],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeAccess($request);

        return $this->formResponse();
    }

    public function edit(Request $request, EmployeeSalary $employeeSalary): Response
    {
        $this->authorizeAccess($request);

        return $this->formResponse($employeeSalary);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $validated = $this->validateSalary($request);

        DB::transaction(function () use ($validated, $request): void {
            EmployeeSalary::query()->create([
                ...$validated,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->recalculatePeriods((int) $validated['user_id']);
        });

        return to_route('admin.employee-salaries.index')->with('success', 'Periode gaji pegawai berhasil ditambahkan.');
    }

    public function update(Request $request, EmployeeSalary $employeeSalary): RedirectResponse
    {
        $this->authorizeAccess($request);
        $oldUserId = (int) $employeeSalary->user_id;
        $validated = $this->validateSalary($request, $employeeSalary);

        DB::transaction(function () use ($employeeSalary, $validated, $oldUserId, $request): void {
            $employeeSalary->update([...$validated, 'updated_by' => $request->user()->id]);
            $this->recalculatePeriods($oldUserId);
            if ($oldUserId !== (int) $validated['user_id']) {
                $this->recalculatePeriods((int) $validated['user_id']);
            }
        });

        return to_route('admin.employee-salaries.index')->with('success', 'Periode gaji pegawai berhasil diperbarui.');
    }

    public function toggle(Request $request, EmployeeSalary $employeeSalary): RedirectResponse
    {
        $this->authorizeAccess($request);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($employeeSalary, $validated, $request): void {
            $employeeSalary->update([
                'is_active' => $validated['is_active'],
                'updated_by' => $request->user()->id,
            ]);
            $this->recalculatePeriods((int) $employeeSalary->user_id);
        });

        return back()->with('success', $employeeSalary->is_active ? 'Periode gaji diaktifkan.' : 'Periode gaji dinonaktifkan.');
    }

    private function validateSalary(Request $request, ?EmployeeSalary $salary = null): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'fixed_allowance' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => [
                'required',
                'date',
                Rule::unique('employee_salaries', 'effective_from')
                    ->where(fn ($query) => $query->where('user_id', $request->input('user_id')))
                    ->ignore($salary?->id),
            ],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'effective_from.unique' => 'Pegawai sudah memiliki pengaturan gaji pada tanggal berlaku tersebut.',
        ]);
    }

    private function recalculatePeriods(int $userId): void
    {
        $activeSalaries = EmployeeSalary::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get();

        foreach ($activeSalaries as $index => $salary) {
            $next = $activeSalaries->get($index + 1);
            $salary->updateQuietly([
                'effective_until' => $next
                    ? CarbonImmutable::parse($next->effective_from)->subDay()->toDateString()
                    : null,
            ]);
        }

        EmployeeSalary::query()
            ->where('user_id', $userId)
            ->where('is_active', false)
            ->update(['effective_until' => null]);
    }

    private function formatRow(EmployeeSalary $salary): array
    {
        $today = now()->startOfDay();
        $effectiveFrom = $salary->effective_from->startOfDay();
        $effectiveUntil = $salary->effective_until?->startOfDay();

        $periodStatus = match (true) {
            ! $salary->is_active => 'Nonaktif',
            $effectiveFrom->isAfter($today) => 'Terjadwal',
            $effectiveUntil?->isBefore($today) => 'Riwayat',
            default => 'Sedang berlaku',
        };

        return [
            'id' => $salary->id,
            'user_id' => (string) $salary->user_id,
            'employee_name' => $salary->user?->name,
            'employee_number' => $salary->user?->employee_number,
            'job_title' => $salary->user?->job_title,
            'branch' => $salary->user?->kantorCabang?->nama_cabang,
            'basic_salary' => (float) $salary->basic_salary,
            'fixed_allowance' => (float) $salary->fixed_allowance,
            'total_salary' => (float) $salary->basic_salary + (float) $salary->fixed_allowance,
            'effective_from' => $salary->effective_from->format('Y-m-d'),
            'effective_until' => $salary->effective_until?->format('Y-m-d'),
            'is_active' => $salary->is_active,
            'period_status' => $periodStatus,
            'notes' => $salary->notes,
            'edit_url' => route('admin.employee-salaries.edit', $salary, false),
        ];
    }

    private function formResponse(?EmployeeSalary $salary = null): Response
    {
        return Inertia::render('Admin/EmployeeSalary/FormPage', [
            'title' => $salary ? 'Edit Periode Gaji' : 'Tambah Periode Gaji',
            'baseUrl' => route('admin.employee-salaries.index', absolute: false),
            'actionUrl' => $salary
                ? route('admin.employee-salaries.update', $salary, false)
                : route('admin.employee-salaries.store', absolute: false),
            'method' => $salary ? 'put' : 'post',
            'initialData' => [
                'user_id' => (string) ($salary?->user_id ?? ''),
                'basic_salary' => $salary ? (string) $salary->basic_salary : '',
                'fixed_allowance' => $salary ? (string) $salary->fixed_allowance : '0',
                'effective_from' => $salary?->effective_from?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'is_active' => $salary?->is_active ?? true,
                'notes' => $salary?->notes ?? '',
            ],
            'employees' => User::query()
                ->with('kantorCabang')
                ->orderByRaw("CASE WHEN employment_status = 'aktif' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'value' => (string) $user->id,
                    'label' => trim(($user->employee_number ? $user->employee_number.' - ' : '').$user->name),
                    'job_title' => $user->job_title,
                    'branch' => $user->kantorCabang?->nama_cabang,
                ])->values(),
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'owner', 'manager']), 403);
    }
}
