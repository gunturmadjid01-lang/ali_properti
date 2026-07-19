<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeAdvancePayrollAllocation;
use App\Models\EmployeeSalary;
use App\Models\MasterBank;
use App\Models\PayrollBatch;
use App\Models\Perumahan;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmployeeSalaryController extends Controller
{
    use ScopesActivePerumahan;

    public function index(Request $request, ApprovalWorkflowService $workflow): Response
    {
        $this->authorizeView($request);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $fromPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('from_period')) ? (string) $request->query('from_period') : now()->subMonths(11)->format('Y-m');
        $toPeriod = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('to_period')) ? (string) $request->query('to_period') : now()->format('Y-m');
        if ($fromPeriod > $toPeriod) {
            [$fromPeriod, $toPeriod] = [$toPeriod, $fromPeriod];
        }
        $base = PayrollBatch::query();
        $this->scopeToActivePerumahan($base, $request);
        $statistics = [
            'transactions' => (clone $base)->count(),
            'employees_paid' => DB::table('payroll_batch_items')->join('payroll_batches', 'payroll_batches.id', '=', 'payroll_batch_items.payroll_batch_id')->where('payroll_batches.status', 'approved')->when($this->shouldScopeToActivePerumahan($request), fn ($query) => $query->where('payroll_batches.perumahan_id', $this->ensureActivePerumahan($request)))->distinct()->count('payroll_batch_items.user_id'),
            'approved_total' => (float) (clone $base)->where('status', 'approved')->sum('total_net'),
            'current_period_total' => (float) (clone $base)->where('status', 'approved')->where('period', now()->format('Y-m'))->sum('total_net'),
        ];
        $monthlyQuery = PayrollBatch::where('status', 'approved');
        $this->scopeToActivePerumahan($monthlyQuery, $request);
        $monthly = $monthlyQuery->whereBetween('period', [$fromPeriod, $toPeriod])->selectRaw('period, SUM(total_net) total, COUNT(*) transactions')->groupBy('period')->get()->keyBy('period');
        $cursor = CarbonImmutable::createFromFormat('Y-m-d', $fromPeriod.'-01');
        $end = CarbonImmutable::createFromFormat('Y-m-d', $toPeriod.'-01');
        $trend = [];
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $trend[] = ['period' => $key, 'label' => $cursor->translatedFormat('M Y'), 'total' => (float) ($monthly->get($key)?->total ?? 0), 'transactions' => (int) ($monthly->get($key)?->transactions ?? 0)];
            $cursor = $cursor->addMonth();
        }
        $rowsQuery = PayrollBatch::query()->with(['items', 'creator:id,name']);
        $this->scopeToActivePerumahan($rowsQuery, $request);
        $rows = $rowsQuery
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('batch_number', 'like', "%{$search}%")->orWhere('period', 'like', "%{$search}%")->orWhereHas('items', fn (Builder $q) => $q->where('employee_name', 'like', "%{$search}%"))))
            ->when($status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->where(fn (Builder $q) => $q->where('record_status', '!=', 'draft')->orWhere('created_by', $request->user()->id))
            ->latest('id')->paginate(15)->withQueryString()->through(fn (PayrollBatch $batch) => $this->row($batch, $workflow));

        return Inertia::render('Admin/EmployeeSalary/Index', ['title' => 'Transaksi Penggajian', 'baseUrl' => route('admin.employee-salaries.index', absolute: false), 'createUrl' => route('admin.employee-salaries.create', absolute: false), 'filters' => ['search' => $search, 'status' => $status, 'from_period' => $fromPeriod, 'to_period' => $toPeriod], 'statistics' => $statistics, 'trend' => $trend, 'rows' => $rows, 'canManage' => $request->user()->can('payroll.manage')]);
    }

    public function activeSalaries(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'perumahan_id' => ['required', 'integer', 'exists:perumahans,id'], 'user_ids' => ['nullable', 'array'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $this->ensurePerumahanAllowed($request, (int) $data['perumahan_id']);
        $date = $data['period'].'-01';
        $advances = EmployeeAdvance::where('perumahan_id', $data['perumahan_id'])->whereIn('user_id', $data['user_ids'] ?? [])->where(['status' => 'approved', 'deduction_period' => $data['period']])->whereDoesntHave('allocation')->selectRaw('user_id,SUM(amount) total')->groupBy('user_id')->pluck('total', 'user_id');
        $rows = EmployeeSalary::whereIn('user_id', $data['user_ids'] ?? [])->where('is_active', true)->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))->orderByDesc('effective_from')->get()->unique('user_id')->mapWithKeys(fn ($s) => [(string) $s->user_id => ['basic_salary' => (float) $s->basic_salary, 'fixed_allowance' => (float) $s->fixed_allowance, 'advance_deduction' => (float) ($advances[$s->user_id] ?? 0)]]);

        return response()->json(['salaries' => $rows]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManage($request);

        return $this->formResponse();
    }

    public function edit(Request $request, PayrollBatch $employeeSalary): Response
    {
        $this->authorizeManage($request);
        $this->assertEditable($employeeSalary, $request);

        return $this->formResponse($employeeSalary->load('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);
        $batch = DB::transaction(function () use ($data, $request) {
            $this->ensurePerumahanAllowed($request, (int) $data['perumahan_id']);
            $batch = PayrollBatch::create(['perumahan_id' => $data['perumahan_id'], 'master_bank_id' => $data['master_bank_id'], 'batch_number' => $this->nextNumber(), 'period' => $data['period'], 'payment_date' => $data['payment_date'], 'notes' => $data['notes'] ?? null, 'status' => 'draft', 'record_status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $this->syncItems($batch, $data['items']);

            return $batch;
        });

        return to_route('admin.employee-salaries.index')->with('success', "Transaksi {$batch->batch_number} tersimpan sebagai draft.");
    }

    public function update(Request $request, PayrollBatch $employeeSalary): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertEditable($employeeSalary, $request);
        $data = $this->validated($request);
        $this->ensurePerumahanAllowed($request, (int) $data['perumahan_id']);
        DB::transaction(function () use ($employeeSalary, $data, $request) {
            $employeeSalary->update(['perumahan_id' => $data['perumahan_id'], 'master_bank_id' => $data['master_bank_id'], 'period' => $data['period'], 'payment_date' => $data['payment_date'], 'notes' => $data['notes'] ?? null, 'updated_by' => $request->user()->id]);
            $this->syncItems($employeeSalary, $data['items']);
        });

        return to_route('admin.employee-salaries.index')->with('success', 'Draft penggajian diperbarui.');
    }

    public function lock(Request $request, PayrollBatch $employeeSalary, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->assertEditable($employeeSalary, $request);
        abort_if($employeeSalary->items()->count() === 0, 422, 'Minimal satu pegawai wajib dipilih.');
        abort_unless($employeeSalary->perumahan_id && $employeeSalary->master_bank_id, 422, 'Perumahan dan rekening pembayaran wajib dilengkapi sebelum finalisasi.');
        DB::transaction(function () use ($employeeSalary, $request, $workflow) {
            $employeeSalary->update(['record_status' => 'locked', 'status' => 'pending_approval', 'locked_at' => now(), 'locked_by' => $request->user()->id]);
            $approval = $workflow->submitLocked($employeeSalary, 'employee-payroll');
            if ($approval->status === ApprovalRequest::STATUS_APPROVED) {
                $employeeSalary->update(['status' => 'approved']);
            }
        });

        return back()->with('success', 'Transaksi penggajian difinalisasi.');
    }

    public function unlock(Request $request, PayrollBatch $employeeSalary, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_unless($employeeSalary->record_status === 'locked' && $employeeSalary->status !== 'approved', 422);
        $workflow->cancelPendingLock($employeeSalary);
        $employeeSalary->update(['record_status' => 'draft', 'status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Transaksi dikembalikan menjadi draft.');
    }

    public function review(Request $request, PayrollBatch $employeeSalary, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::where(['module_key' => 'employee-payroll', 'model_type' => PayrollBatch::class, 'model_id' => $employeeSalary->id, 'status' => ApprovalRequest::STATUS_PENDING])->firstOrFail();
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null);

        return back()->with('success', $decision === 'approve' ? 'Tahap approval disetujui.' : 'Transaksi ditolak dan kembali menjadi draft.');
    }

    public function invoice(Request $request, PayrollBatch $employeeSalary): HttpResponse
    {
        $this->authorizeView($request);
        abort_if($employeeSalary->record_status === 'draft' && $employeeSalary->created_by !== $request->user()->id, 403);
        $employeeSalary->load(['items', 'creator']);

        return response()->view('payroll.invoice', ['batch' => $employeeSalary, 'isFinal' => $employeeSalary->status === 'approved']);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['perumahan_id' => ['required', Rule::exists('perumahans', 'id')], 'master_bank_id' => ['required', Rule::exists('master_banks', 'id')->where(fn ($query) => $query->where('perumahan_id', $request->integer('perumahan_id'))->where('status', 'aktif')->where('record_status', 'locked'))], 'period' => ['required', 'date_format:Y-m'], 'payment_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1'], 'items.*.user_id' => ['required', 'distinct', Rule::exists('users', 'id')->whereNotNull('job_position_id')->where('employment_status', 'aktif')], 'items.*.basic_salary' => ['required', 'numeric', 'min:0'], 'items.*.fixed_allowance' => ['nullable', 'numeric', 'min:0'], 'items.*.other_allowance' => ['nullable', 'numeric', 'min:0'], 'items.*.deductions' => ['nullable', 'numeric', 'min:0'], 'items.*.notes' => ['nullable', 'string', 'max:500']]);
    }

    private function syncItems(PayrollBatch $batch, array $items): void
    {
        $users = User::with('jobPosition')->whereIn('id', collect($items)->pluck('user_id'))->get()->keyBy('id');
        $batch->items()->delete();
        $gross = $deductions = $net = 0;
        foreach ($items as $item) {
            $user = $users->get((int) $item['user_id']);
            abort_unless($user?->jobPosition, 422, 'Semua pegawai wajib memiliki jabatan.');
            $salary = EmployeeSalary::where('user_id', $user->id)->where('is_active', true)->whereDate('effective_from', '<=', $batch->period.'-01')->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $batch->period.'-01'))->latest('effective_from')->first();
            $basic = (float) ($salary?->basic_salary ?? $item['basic_salary']);
            $fixed = (float) ($salary?->fixed_allowance ?? ($item['fixed_allowance'] ?? 0));
            $other = (float) ($item['other_allowance'] ?? 0);
            $manual = (float) ($item['deductions'] ?? 0);
            $advances = EmployeeAdvance::where(['perumahan_id' => $batch->perumahan_id, 'user_id' => $user->id, 'status' => 'approved', 'deduction_period' => $batch->period])->whereDoesntHave('allocation')->get();
            $advance = (float) $advances->sum('amount');
            $cut = $manual + $advance;
            $take = max(0, $basic + $fixed + $other - $cut);
            $row = $batch->items()->create(['user_id' => $user->id, 'employee_number' => $user->employee_number, 'employee_name' => $user->name, 'job_position' => $user->jobPosition->name, 'basic_salary' => $basic, 'fixed_allowance' => $fixed, 'other_allowance' => $other, 'deductions' => $cut, 'advance_deduction' => $advance, 'net_salary' => $take, 'notes' => $item['notes'] ?? null]);
            foreach ($advances as $a) {
                EmployeeAdvancePayrollAllocation::create(['employee_advance_id' => $a->id, 'payroll_batch_item_id' => $row->id, 'amount' => $a->amount]);
            }$gross += $basic + $fixed + $other;
            $deductions += $cut;
            $net += $take;
        }
        $batch->updateQuietly(['total_gross' => $gross, 'total_deductions' => $deductions, 'total_net' => $net]);
    }

    private function formResponse(?PayrollBatch $batch = null): Response
    {
        $employees = User::with(['jobPosition', 'kantorCabang'])->where('employment_status', 'aktif')->whereNotNull('job_position_id')->orderBy('name')->get()->map(function (User $u) use ($batch) {
            $salary = EmployeeSalary::where('user_id', $u->id)->where('is_active', true)->whereDate('effective_from', '<=', ($batch?->period ?? now()->format('Y-m')).'-01')->latest('effective_from')->first();

            return ['value' => (string) $u->id, 'label' => $u->name, 'employee_number' => $u->employee_number, 'job_position' => $u->jobPosition?->name, 'branch' => $u->kantorCabang?->nama_cabang, 'basic_salary' => (float) ($salary?->basic_salary ?? 0), 'fixed_allowance' => (float) ($salary?->fixed_allowance ?? 0)];
        })->values();
        $perumahanId = $batch?->perumahan_id ?? ($this->shouldScopeToActivePerumahan(request()) ? $this->ensureActivePerumahan(request()) : request()->session()->get('active_perumahan_id'));
        $perumahans = Perumahan::query()->finalized()->when($this->shouldScopeToActivePerumahan(request()), fn ($query) => $query->whereIn('id', $this->assignedPerumahanIds(request())))->orderBy('nama_perusahaan')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values();
        $banks = MasterBank::query()->where('status', 'aktif')->where('record_status', 'locked')->when($this->shouldScopeToActivePerumahan(request()), fn ($query) => $query->where('perumahan_id', $perumahanId))->orderBy('nama_bank')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->nama_bank} - {$row->nomor_rekening}", 'perumahan_id' => (string) $row->perumahan_id])->values();

        return Inertia::render('Admin/EmployeeSalary/FormPage', ['title' => $batch ? 'Edit Draft Penggajian' : 'Buat Penggajian Batch', 'baseUrl' => route('admin.employee-salaries.index', absolute: false), 'actionUrl' => $batch ? route('admin.employee-salaries.update', $batch, false) : route('admin.employee-salaries.store', absolute: false), 'salaryLookupUrl' => route('admin.employee-salaries.active-salaries', absolute: false), 'method' => $batch ? 'put' : 'post', 'employees' => $employees, 'perumahans' => $perumahans, 'banks' => $banks, 'initialData' => ['perumahan_id' => (string) ($perumahanId ?? ''), 'master_bank_id' => (string) ($batch?->master_bank_id ?? ''), 'period' => $batch?->period ?? now()->format('Y-m'), 'payment_date' => $batch?->payment_date?->format('Y-m-d') ?? now()->toDateString(), 'notes' => $batch?->notes ?? '', 'items' => $batch?->items->map(fn ($i) => ['user_id' => (string) $i->user_id, 'basic_salary' => (float) $i->basic_salary, 'fixed_allowance' => (float) $i->fixed_allowance, 'other_allowance' => (float) $i->other_allowance, 'deductions' => (float) $i->deductions - (float) $i->advance_deduction, 'advance_deduction' => (float) $i->advance_deduction, 'notes' => $i->notes])->values()->all() ?? []]]);
    }

    private function row(PayrollBatch $b, ApprovalWorkflowService $workflow): array
    {
        $a = ApprovalRequest::where(['module_key' => 'employee-payroll', 'model_type' => PayrollBatch::class, 'model_id' => $b->id])->latest()->first();

        return ['id' => $b->id, 'batch_number' => $b->batch_number, 'period' => $b->period, 'payment_date' => $b->payment_date->format('Y-m-d'), 'employee_count' => $b->items->count(), 'total_gross' => (float) $b->total_gross, 'total_deductions' => (float) $b->total_deductions, 'total_net' => (float) $b->total_net, 'status' => $b->status, 'record_status' => $b->record_status, 'approval_label' => $a ? ($a->status === 'pending' ? "Tahap {$a->current_step}/{$a->total_steps}" : ucfirst($a->status)) : 'Belum diajukan', 'can_review' => $a && $a->status === 'pending' ? $workflow->canReview($a) : false, 'edit_url' => route('admin.employee-salaries.edit', $b, false), 'invoice_url' => route('admin.employee-salaries.invoice', $b, false)];
    }

    private function nextNumber(): string
    {
        return 'PAY/'.now()->format('Ym').'/'.str_pad((string) (PayrollBatch::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    private function assertEditable(PayrollBatch $b, Request $r): void
    {
        abort_unless($b->record_status === 'draft' && $b->created_by === $r->user()->id, 403, 'Hanya pembuat yang dapat mengubah draft.');
    }

    private function authorizeView(Request $r): void
    {
        abort_unless($r->user()?->can('payroll.view') || $r->user()?->hasAnyRole(['super_admin', 'owner', 'manager']), 403);
    }

    private function authorizeManage(Request $r): void
    {
        abort_unless($r->user()?->can('payroll.manage') || $r->user()?->hasAnyRole(['super_admin', 'owner', 'manager']), 403);
    }
}
