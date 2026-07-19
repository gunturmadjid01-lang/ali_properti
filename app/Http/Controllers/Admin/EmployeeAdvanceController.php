<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\EmployeeAdvance;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAdvanceController extends Controller
{
    use ScopesActivePerumahan;

    public function index(Request $r, ApprovalWorkflowService $w): Response
    {
        $this->view($r);
        $from = preg_match('/^\d{4}-\d{2}$/', (string) $r->query('from_period')) ? $r->query('from_period') : now()->subMonths(11)->format('Y-m');
        $to = preg_match('/^\d{4}-\d{2}$/', (string) $r->query('to_period')) ? $r->query('to_period') : now()->format('Y-m');
        if ($from > $to) {
            [$from,$to] = [$to, $from];
        }
        $q = EmployeeAdvance::query();
        $this->scopeToActivePerumahan($q, $r);
        $monthly = (clone $q)->where('status', 'approved')->whereBetween('deduction_period', [$from, $to])->selectRaw('deduction_period period,SUM(amount) total,COUNT(*) transactions')->groupBy('deduction_period')->get()->keyBy('period');
        $c = CarbonImmutable::createFromFormat('Y-m-d', $from.'-01');
        $e = CarbonImmutable::createFromFormat('Y-m-d', $to.'-01');
        $trend = [];
        while ($c->lte($e)) {
            $k = $c->format('Y-m');
            $trend[] = ['period' => $k, 'label' => $c->translatedFormat('M Y'), 'total' => (float) ($monthly->get($k)?->total ?? 0), 'transactions' => (int) ($monthly->get($k)?->transactions ?? 0)];
            $c = $c->addMonth();
        }$stats = ['total' => (float) (clone $q)->where('status', 'approved')->sum('amount'), 'deducted' => (float) (clone $q)->whereHas('allocation.payrollItem.batch', fn ($x) => $x->where('status', 'approved'))->sum('amount'), 'pending' => (float) (clone $q)->where('status', 'approved')->whereDoesntHave('allocation.payrollItem.batch', fn ($x) => $x->where('status', 'approved'))->sum('amount'), 'employees' => (clone $q)->where('status', 'approved')->distinct()->count('user_id')];
        $search = trim((string) $r->query('search', ''));
        $rowsQuery = EmployeeAdvance::with(['user.jobPosition', 'allocation.payrollItem.batch']);
        $this->scopeToActivePerumahan($rowsQuery, $r);
        $rows = $rowsQuery->when($search !== '', fn (Builder $q) => $q->where('advance_number', 'like', "%{$search}%")->orWhereHas('user', fn ($x) => $x->where('name', 'like', "%{$search}%")))->where(fn ($q) => $q->where('record_status', '!=', 'draft')->orWhere('created_by', $r->user()->id))->latest()->paginate(15)->withQueryString()->through(function ($x) use ($w) {
            $a = ApprovalRequest::where(['module_key' => 'employee-advance', 'model_type' => EmployeeAdvance::class, 'model_id' => $x->id])->latest()->first();

            return ['id' => $x->id, 'number' => $x->advance_number, 'employee' => $x->user?->name, 'position' => $x->user?->jobPosition?->name, 'advance_date' => $x->advance_date->format('Y-m-d'), 'deduction_period' => $x->deduction_period, 'amount' => (float) $x->amount, 'purpose' => $x->purpose, 'status' => $x->status, 'record_status' => $x->record_status, 'deducted' => $x->allocation?->payrollItem?->batch?->status === 'approved', 'can_review' => $a && $a->status === 'pending' ? $w->canReview($a) : false, 'edit_url' => route('admin.employee-advances.edit', $x, false)];
        });

        return Inertia::render('Admin/EmployeeAdvance/Index', ['title' => 'Panjar Pegawai', 'baseUrl' => route('admin.employee-advances.index', absolute: false), 'createUrl' => route('admin.employee-advances.create', absolute: false), 'filters' => ['search' => $search, 'from_period' => $from, 'to_period' => $to], 'statistics' => $stats, 'trend' => $trend, 'rows' => $rows]);
    }

    public function create(Request $r): Response
    {
        $this->manage($r);

        return $this->form();
    }

    public function edit(Request $r, EmployeeAdvance $advance): Response
    {
        $this->manage($r);
        $this->editable($advance, $r);

        return $this->form($advance);
    }

    public function store(Request $r): RedirectResponse
    {
        $this->manage($r);
        $d = $this->data($r);
        $this->ensurePerumahanAllowed($r, (int) $d['perumahan_id']);
        EmployeeAdvance::create([...$d, 'advance_number' => 'PNJ/'.now()->format('Ym').'/'.str_pad((string) (EmployeeAdvance::max('id') + 1), 5, '0', STR_PAD_LEFT), 'status' => 'draft', 'record_status' => 'draft', 'created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);

        return to_route('admin.employee-advances.index')->with('success', 'Panjar tersimpan sebagai draft.');
    }

    public function update(Request $r, EmployeeAdvance $advance): RedirectResponse
    {
        $this->manage($r);
        $this->editable($advance, $r);
        $data = $this->data($r);
        $this->ensurePerumahanAllowed($r, (int) $data['perumahan_id']);
        $advance->update([...$data, 'updated_by' => $r->user()->id]);

        return to_route('admin.employee-advances.index')->with('success', 'Draft panjar diperbarui.');
    }

    public function lock(Request $r, EmployeeAdvance $advance, ApprovalWorkflowService $w): RedirectResponse
    {
        $this->manage($r);
        $this->editable($advance, $r);
        abort_unless($advance->perumahan_id && $advance->master_bank_id, 422, 'Perumahan dan rekening sumber wajib dilengkapi sebelum finalisasi.');
        $advance->update(['record_status' => 'locked', 'status' => 'pending_approval', 'locked_at' => now(), 'locked_by' => $r->user()->id]);
        $a = $w->submitLocked($advance, 'employee-advance');
        if ($a->status === 'approved') {
            $advance->update(['status' => 'approved']);
        }

        return back()->with('success', 'Panjar diajukan.');
    }

    public function unlock(Request $r, EmployeeAdvance $advance, ApprovalWorkflowService $w): RedirectResponse
    {
        $this->manage($r);
        abort_unless($advance->status === 'pending_approval', 422);
        $w->cancelPendingLock($advance);
        $advance->update(['status' => 'draft', 'record_status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back();
    }

    public function review(Request $r, EmployeeAdvance $advance, string $decision, ApprovalWorkflowService $w): RedirectResponse
    {
        $a = ApprovalRequest::where(['module_key' => 'employee-advance', 'model_type' => EmployeeAdvance::class, 'model_id' => $advance->id, 'status' => 'pending'])->firstOrFail();
        $decision === 'approve' ? $w->approve($a) : $w->reject($a, $r->input('note'));

        return back();
    }

    private function data(Request $r): array
    {
        return $r->validate(['perumahan_id' => ['required', Rule::exists('perumahans', 'id')], 'master_bank_id' => ['required', Rule::exists('master_banks', 'id')->where(fn ($query) => $query->where('perumahan_id', $r->integer('perumahan_id'))->where('status', 'aktif')->where('record_status', 'locked'))], 'user_id' => ['required', Rule::exists('users', 'id')->whereNotNull('job_position_id')->where('employment_status', 'aktif')], 'advance_date' => ['required', 'date'], 'deduction_period' => ['required', 'date_format:Y-m'], 'amount' => ['required', 'numeric', 'min:1'], 'purpose' => ['required', 'string', 'max:1000']]);
    }

    private function form(?EmployeeAdvance $x = null): Response
    {
        $perumahanId = $x?->perumahan_id ?? ($this->shouldScopeToActivePerumahan(request()) ? $this->ensureActivePerumahan(request()) : request()->session()->get('active_perumahan_id'));
        $perumahans = Perumahan::query()->finalized()->when($this->shouldScopeToActivePerumahan(request()), fn ($query) => $query->whereIn('id', $this->assignedPerumahanIds(request())))->orderBy('nama_perusahaan')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values();
        $banks = MasterBank::query()->where('status', 'aktif')->where('record_status', 'locked')->when($this->shouldScopeToActivePerumahan(request()), fn ($query) => $query->where('perumahan_id', $perumahanId))->orderBy('nama_bank')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->nama_bank} - {$row->nomor_rekening}", 'perumahan_id' => (string) $row->perumahan_id])->values();

        return Inertia::render('Admin/EmployeeAdvance/FormPage', [
            'title' => $x ? 'Edit Panjar Pegawai' : 'Buat Panjar Pegawai',
            'baseUrl' => route('admin.employee-advances.index', absolute: false),
            'actionUrl' => $x ? route('admin.employee-advances.update', $x, false) : route('admin.employee-advances.store', absolute: false),
            'method' => $x ? 'put' : 'post',
            'perumahans' => $perumahans,
            'banks' => $banks,
            'initialData' => [
                'perumahan_id' => (string) ($perumahanId ?? ''),
                'master_bank_id' => (string) ($x?->master_bank_id ?? ''),
                'user_id' => (string) ($x?->user_id ?? ''),
                'advance_date' => $x?->advance_date?->format('Y-m-d') ?? now()->toDateString(),
                'deduction_period' => $x?->deduction_period ?? now()->format('Y-m'),
                'amount' => (float) ($x?->amount ?? 0),
                'purpose' => $x?->purpose ?? '',
            ],
            'employees' => User::with('jobPosition')->where('employment_status', 'aktif')->whereNotNull('job_position_id')->orderBy('name')->get()->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name.' - '.$u->jobPosition?->name])->values(),
        ]);

        return Inertia::render('Admin/EmployeeAdvance/FormPage', ['title' => $x ? 'Edit Panjar Pegawai' : 'Buat Panjar Pegawai', 'baseUrl' => route('admin.employee-advances.index', absolute: false), 'actionUrl' => $x ? route('admin.employee-advances.update', $x, false) : route('admin.employee-advances.store', absolute: false), 'method' => $x ? 'put' : 'post', 'initialData' => ['user_id' => (string) ($x?->user_id ?? ''), 'advance_date' => $x?->advance_date?->format('Y-m-d') ?? now()->toDateString(), 'deduction_period' => $x?->deduction_period ?? now()->format('Y-m'), 'amount' => (float) ($x?->amount ?? 0), 'purpose' => $x?->purpose ?? ''], 'employees' => User::with('jobPosition')->where('employment_status', 'aktif')->whereNotNull('job_position_id')->orderBy('name')->get()->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name.' · '.$u->jobPosition?->name])->values()]);
    }

    private function editable($x, $r): void
    {
        abort_unless($x->record_status === 'draft' && $x->created_by === $r->user()->id, 403);
    }

    private function view($r): void
    {
        abort_unless($r->user()?->can('payroll.view') || $r->user()?->hasAnyRole(['super_admin', 'owner', 'manager']), 403);
    }

    private function manage($r): void
    {
        abort_unless($r->user()?->can('payroll.manage') || $r->user()?->hasAnyRole(['super_admin', 'owner', 'manager']), 403);
    }
}
