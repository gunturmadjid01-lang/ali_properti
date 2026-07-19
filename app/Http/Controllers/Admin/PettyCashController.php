<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\KelompokHpp;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\PettyCashAccount;
use App\Models\PettyCashDeposit;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFunding;
use App\Models\PettyCashLedger;
use App\Models\TahapanPembangunan;
use App\Services\ApprovalWorkflowService;
use App\Services\PettyCashService;
use App\Support\CodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PettyCashController extends Controller
{
    public function __construct(private readonly PettyCashService $service, private readonly ApprovalWorkflowService $approvalWorkflow) {}

    public function index(Request $request, string $section = 'saldo'): Response
    {
        $this->authorizeView($request);
        abort_unless(in_array($section, ['saldo', 'pengisian', 'pengeluaran', 'penyetoran', 'laporan'], true), 404);
        $accountId = $request->integer('account_id') ?: null;
        $from = $request->query('from');
        $to = $request->query('to');

        $visibleAccountIds = $this->visibleAccounts($request)->pluck('id');
        abort_if($accountId && ! $visibleAccountIds->contains($accountId), 403);

        $accounts = PettyCashAccount::query()->with(['branch:id,nama_cabang', 'assignedUser:id,name'])
            ->whereIn('id', $visibleAccountIds)->orderBy('name')->get();
        $fundings = PettyCashFunding::query()->with(['account:id,name,code', 'requester:id,name', 'approver:id,name', 'approvalRequest'])
            ->whereIn('petty_cash_account_id', $visibleAccountIds)
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->latest('request_date')->latest('id')->limit(100)->get();
        $expenses = PettyCashExpense::query()->with(['account:id,name,code', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'creator:id,name'])
            ->whereIn('petty_cash_account_id', $visibleAccountIds)
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->latest('expense_date')->latest('id')->limit(150)->get();
        $ledgers = PettyCashLedger::query()->with('account:id,name,code')
            ->whereIn('petty_cash_account_id', $visibleAccountIds)
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->latest('transaction_date')->latest('id')->limit(200)->get();
        $deposits = PettyCashDeposit::query()->with(['account:id,name,code', 'masterBank:id,nama_bank,nomor_rekening,nama_rekening', 'creator:id,name', 'approvalRequest'])
            ->whereIn('petty_cash_account_id', $visibleAccountIds)
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->latest('deposit_date')->latest('id')->limit(100)->get();

        return Inertia::render('Admin/PettyCash/Index', [
            'title' => 'Kas Kecil',
            'section' => $section,
            'filters' => ['account_id' => $accountId ? (string) $accountId : '', 'from' => $from, 'to' => $to],
            'accounts' => $accounts->map(fn ($row) => [
                'id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'branch' => $row->branch?->nama_cabang,
                'assigned_user' => $row->assignedUser?->name,
                'target_amount' => (float) $row->target_amount, 'balance' => (float) $row->balance,
                'minimum_balance' => (float) $row->minimum_balance, 'status' => $row->status,
                'is_low' => (float) $row->balance <= (float) $row->minimum_balance,
            ]),
            'fundings' => $fundings->map(fn ($row) => [
                'id' => $row->id, 'account' => $row->account?->name, 'number' => $row->number, 'type' => $row->type,
                'request_date' => $row->request_date->format('Y-m-d'), 'amount' => (float) $row->amount, 'status' => $row->status,
                'requester' => $row->requester?->name, 'approver' => $row->approver?->name,
                'can_submit' => $row->requested_by === $request->user()->id,
                'approved_at' => $row->approved_at?->format('Y-m-d H:i'), 'request_notes' => $row->request_notes,
                'record_status' => $row->record_status, 'approval_status' => $row->approvalRequest?->status,
                'approval_current_step' => $row->approvalRequest?->current_step, 'approval_total_steps' => $row->approvalRequest?->total_steps,
                'can_review' => $row->approvalRequest?->status === 'pending' && $this->approvalWorkflow->canReview($row->approvalRequest),
                'request_proof_url' => $row->request_proof_path ? route('media', ['path' => $row->request_proof_path], false) : null,
                'approval_proof_url' => $row->approval_proof_path ? route('media', ['path' => $row->approval_proof_path], false) : null,
            ]),
            'expenses' => $expenses->map(fn ($row) => [
                'id' => $row->id, 'account' => $row->account?->name, 'number' => $row->number,
                'expense_date' => $row->expense_date->format('Y-m-d'), 'category' => $row->category, 'cost_type' => $row->cost_type,
                'amount' => (float) $row->amount, 'description' => $row->description, 'creator' => $row->creator?->name,
                'perumahan' => $row->perumahan?->nama_perusahaan,
                'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : null,
                'proof_url' => route('media', ['path' => $row->proof_path], false),
            ]),
            'ledgers' => $ledgers->map(fn ($row) => [
                'id' => $row->id, 'account' => $row->account?->name, 'transaction_date' => $row->transaction_date->format('Y-m-d'),
                'direction' => $row->direction, 'amount' => (float) $row->amount, 'balance_after' => (float) $row->balance_after,
                'description' => $row->description,
            ]),
            'deposits' => $deposits->map(fn ($row) => [
                'id' => $row->id, 'account' => $row->account?->name, 'number' => $row->number,
                'deposit_date' => $row->deposit_date->format('Y-m-d'), 'amount' => (float) $row->amount,
                'status' => $row->status, 'record_status' => $row->record_status, 'notes' => $row->notes,
                'destination_bank' => $row->masterBank ? trim($row->masterBank->nama_bank.' - '.$row->masterBank->nomor_rekening.' - '.$row->masterBank->nama_rekening) : null,
                'creator' => $row->creator?->name, 'deposited_at' => $row->deposited_at?->format('Y-m-d H:i'),
                'approval_status' => $row->approvalRequest?->status,
                'approval_current_step' => $row->approvalRequest?->current_step,
                'approval_total_steps' => $row->approvalRequest?->total_steps,
                'can_submit' => $row->created_by === $request->user()->id && $row->record_status === 'draft',
                'can_review' => $row->approvalRequest?->status === 'pending' && $this->approvalWorkflow->canReview($row->approvalRequest),
                'proof_url' => route('media', ['path' => $row->proof_path], false),
            ]),
            'reportSummary' => [
                'balance' => (float) $accounts
                    ->when($accountId, fn ($rows) => $rows->where('id', $accountId))
                    ->sum(fn ($row) => (float) $row->balance),
                'cash_in' => (float) $ledgers->where('direction', 'in')->sum(fn ($row) => (float) $row->amount),
                'cash_out' => (float) $ledgers->where('direction', 'out')->sum(fn ($row) => (float) $row->amount),
                'operational' => (float) $expenses->where('cost_type', 'operational')->sum(fn ($row) => (float) $row->amount),
                'project_hpp' => (float) $expenses->where('cost_type', 'project_hpp')->sum(fn ($row) => (float) $row->amount),
                'unit_hpp' => (float) $expenses->where('cost_type', 'unit_hpp')->sum(fn ($row) => (float) $row->amount),
            ],
            'options' => $this->options(),
            'permissions' => [
                'can_create' => $this->canCreate($request),
                'can_approve' => $this->canApprove($request),
                'can_disburse' => $this->canDisburse($request),
                'can_unlock' => $this->canUnlock($request),
            ],
            'hppCategories' => PettyCashService::HPP_CATEGORIES,
        ]);
    }

    public function storeFunding(Request $request): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);
        $data = $request->validate([
            'petty_cash_account_id' => ['required', 'exists:petty_cash_accounts,id'], 'request_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'], 'request_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([PettyCashFunding::DRAFT, PettyCashFunding::PENDING])],
        ]);
        $this->authorizeAccountAccess($request, (int) $data['petty_cash_account_id']);
        $funding = PettyCashFunding::query()->create([
            ...$data, 'number' => CodeGenerator::next(PettyCashFunding::class, 'number', 'RKK'), 'type' => 'replenishment',
            'record_status' => $data['status'] === PettyCashFunding::PENDING ? 'locked' : 'draft',
            'requested_by' => $request->user()->id, 'submitted_at' => $data['status'] === PettyCashFunding::PENDING ? now() : null,
            'locked_at' => $data['status'] === PettyCashFunding::PENDING ? now() : null, 'locked_by' => $data['status'] === PettyCashFunding::PENDING ? $request->user()->id : null,
        ]);
        if ($data['status'] === PettyCashFunding::PENDING) {
            $this->approvalWorkflow->submitLocked($funding, 'petty-cash-funding');
        }

        return back()->with('success', $data['status'] === PettyCashFunding::PENDING ? 'Permohonan pengisian dikirim untuk approval.' : 'Draft pengisian berhasil disimpan.');
    }

    public function submitFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        $this->authorizeAccountAccess($request, $funding->petty_cash_account_id);
        abort_unless($this->canCreate($request) && $funding->requested_by === $request->user()->id, 403);
        abort_unless(in_array($funding->status, [PettyCashFunding::DRAFT, PettyCashFunding::REJECTED], true) && $funding->record_status === 'draft', 422);
        $funding->update(['status' => PettyCashFunding::PENDING, 'record_status' => 'locked', 'submitted_at' => now(), 'locked_at' => now(), 'locked_by' => $request->user()->id]);
        $this->approvalWorkflow->submitLocked($funding, 'petty-cash-funding');

        return back()->with('success', 'Permohonan dikirim untuk approval.');
    }

    public function unlockFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        abort_unless($this->canUnlock($request), 403);
        abort_unless($funding->record_status === 'locked' && $funding->status === PettyCashFunding::PENDING, 422);
        $this->approvalWorkflow->cancelPendingLock($funding);
        $funding->update(['status' => PettyCashFunding::DRAFT, 'record_status' => 'draft', 'locked_at' => null, 'locked_by' => null, 'submitted_at' => null]);

        return back()->with('success', 'Pengajuan dibuka kembali menjadi draft milik pemohon.');
    }

    public function approveFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        $approval = $funding->approvalRequest()->where('status', 'pending')->firstOrFail();
        $this->approvalWorkflow->approve($approval);

        return back()->with('success', $approval->fresh()->status === 'approved' ? 'Approval final selesai. Menunggu pencairan bagian keuangan.' : 'Tahap approval disetujui.');
    }

    public function rejectFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        $data = $request->validate(['rejection_notes' => ['required', 'string', 'max:1000']]);
        $approval = $funding->approvalRequest()->where('status', 'pending')->firstOrFail();
        $this->approvalWorkflow->reject($approval, $data['rejection_notes']);

        return back()->with('success', 'Permohonan pengisian ditolak.');
    }

    public function disburseFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        abort_unless($this->canDisburse($request), 403);
        $data = $request->validate([
            'approval_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $path = $request->file('approval_proof')->store('petty-cash/approval-proofs', 'public');
        $this->service->approveFunding($funding, $request->user()->id, $path, $data['approval_notes'] ?? null);

        return back()->with('success', 'Dana dicairkan oleh keuangan dan saldo kas kecil telah bertambah.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);
        $data = $request->validate([
            'petty_cash_account_id' => ['required', 'exists:petty_cash_accounts,id'], 'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(['material', 'upah_tukang', 'perbaikan_unit', 'pekerjaan_proyek', 'atk', 'transport', 'konsumsi', 'utilitas', 'pemeliharaan_kantor', 'lainnya'])],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'kelompok_hpp_id' => ['nullable', 'exists:kelompok_hpps,id'], 'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'amount' => ['required', 'numeric', 'min:1'], 'description' => ['required', 'string', 'max:1500'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);
        $this->authorizeAccountAccess($request, (int) $data['petty_cash_account_id']);
        $data['proof_path'] = $request->file('proof')->store('petty-cash/expense-proofs', 'public');
        $data['number'] = CodeGenerator::next(PettyCashExpense::class, 'number', 'KKO');
        $this->service->createExpense($data, $request->user()->id);

        return back()->with('success', 'Pengeluaran tercatat, saldo berkurang, dan tujuan biaya terdeteksi otomatis.');
    }

    public function storeDeposit(Request $request): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);
        $data = $request->validate([
            'petty_cash_account_id' => ['required', 'exists:petty_cash_accounts,id'],
            'master_bank_id' => ['required', Rule::exists('master_banks', 'id')->where(fn ($query) => $query->where('status', 'aktif')->where('record_status', 'locked'))],
            'deposit_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'pending'])],
        ]);
        $this->authorizeAccountAccess($request, (int) $data['petty_cash_account_id']);
        $account = PettyCashAccount::query()->findOrFail($data['petty_cash_account_id']);
        $bank = MasterBank::query()->findOrFail($data['master_bank_id']);
        if (! $request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            abort_unless($request->user()?->perumahans()->whereKey($bank->perumahan_id)->exists(), 403);
        }
        abort_if((float) $data['amount'] > (float) $account->balance, 422, 'Nominal setoran melebihi saldo Kas Kecil.');
        $pending = $data['status'] === 'pending';
        $deposit = PettyCashDeposit::query()->create([
            'petty_cash_account_id' => $account->id,
            'master_bank_id' => $bank->id,
            'number' => CodeGenerator::next(PettyCashDeposit::class, 'number', 'SKK'),
            'deposit_date' => $data['deposit_date'], 'amount' => $data['amount'],
            'proof_path' => $request->file('proof')->store('petty-cash/deposit-proofs', 'public'),
            'notes' => $data['notes'] ?? null, 'status' => $pending ? 'pending' : 'draft',
            'record_status' => $pending ? 'locked' : 'draft',
            'locked_at' => $pending ? now() : null, 'locked_by' => $pending ? $request->user()->id : null,
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]);
        if ($pending) $this->approvalWorkflow->submitLocked($deposit, 'petty-cash-deposit');

        return back()->with('success', $pending ? 'Penyetoran dikirim ke Keuangan untuk approval.' : 'Draft penyetoran disimpan.');
    }

    public function submitDeposit(Request $request, PettyCashDeposit $deposit): RedirectResponse
    {
        $this->authorizeAccountAccess($request, $deposit->petty_cash_account_id);
        abort_unless($deposit->created_by === $request->user()->id && $deposit->record_status === 'draft', 403);
        $deposit->update(['status' => 'pending', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->approvalWorkflow->submitLocked($deposit, 'petty-cash-deposit');
        return back()->with('success', 'Penyetoran dikirim ke Keuangan.');
    }

    public function unlockDeposit(Request $request, PettyCashDeposit $deposit): RedirectResponse
    {
        abort_unless($this->canUnlock($request), 403);
        abort_unless($deposit->record_status === 'locked' && $deposit->status === 'pending', 422);
        $this->approvalWorkflow->cancelPendingLock($deposit);
        $deposit->update(['status' => 'draft', 'record_status' => 'draft', 'locked_at' => null, 'locked_by' => null, 'updated_by' => $request->user()->id]);
        return back()->with('success', 'Penyetoran dibuka kembali menjadi draft.');
    }

    public function approveDeposit(Request $request, PettyCashDeposit $deposit): RedirectResponse
    {
        $approval = $deposit->approvalRequest()->where('status', 'pending')->firstOrFail();
        abort_unless($this->approvalWorkflow->canReview($approval), 403);
        $this->approvalWorkflow->approve($approval);
        return back()->with('success', 'Penyetoran disetujui. Saldo Kas Kecil berkurang dan Kas Perusahaan bertambah.');
    }

    public function rejectDeposit(Request $request, PettyCashDeposit $deposit): RedirectResponse
    {
        $data = $request->validate(['rejection_notes' => ['required', 'string', 'max:1000']]);
        $approval = $deposit->approvalRequest()->where('status', 'pending')->firstOrFail();
        abort_unless($this->approvalWorkflow->canReview($approval), 403);
        $this->approvalWorkflow->reject($approval, $data['rejection_notes']);
        return back()->with('success', 'Penyetoran ditolak.');
    }

    private function options(): array
    {
        return [
            'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_perusahaan]),
            'units' => DetailRumah::query()->finalized()->orderBy('perumahan_id')->orderBy('kode_nlok')->orderBy('nomor_rumah')->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => trim($r->kode_nlok.' '.$r->nomor_rumah), 'perumahan_id' => (string) $r->perumahan_id]),
            'hppGroups' => KelompokHpp::query()->finalized()->orderBy('nama_hpp')->get(['id', 'nama_hpp'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_hpp]),
            'stages' => TahapanPembangunan::query()->orderBy('urutan')->get(['id', 'nama_tahapan'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_tahapan]),
            'bankAccounts' => MasterBank::query()->finalized()->where('status', 'aktif')
                ->when(! auth()->user()?->hasAnyRole(['owner', 'super_admin']), fn ($query) => $query->whereIn('perumahan_id', auth()->user()?->perumahans()->pluck('perumahans.id') ?? []))
                ->orderBy('nama_bank')->get(['id', 'nama_bank', 'nomor_rekening', 'nama_rekening'])
                ->map(fn ($bank) => ['value' => (string) $bank->id, 'label' => trim($bank->nama_bank.' - '.$bank->nomor_rekening.' - '.$bank->nama_rekening)]),
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($this->canSeeAllAccounts($request) || $this->visibleAccounts($request)->exists(), 403);
    }

    private function canCreate(Request $request): bool
    {
        return $this->canSeeAllAccounts($request) || $this->visibleAccounts($request)->exists();
    }

    private function canApprove(Request $request): bool
    {
        return (bool) ($request->user()?->hasAnyRole(['super_admin', 'owner', 'manager']) || $request->user()?->can('petty-cash.approve'));
    }

    private function canDisburse(Request $request): bool
    {
        return (bool) ($request->user()?->hasRole('super_admin') || $request->user()?->can('petty-cash.disburse'));
    }

    private function canUnlock(Request $request): bool
    {
        return (bool) $request->user()?->hasAnyRole(['super_admin', 'owner']);
    }

    private function canSeeAllAccounts(Request $request): bool
    {
        return (bool) $request->user()?->hasAnyRole(['super_admin', 'owner']);
    }

    private function visibleAccounts(Request $request)
    {
        return PettyCashAccount::query()->when(! $this->canSeeAllAccounts($request), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('assigned_user_id', $request->user()->id)
                    ->orWhere(function ($query) use ($request) {
                        $query->whereNull('assigned_user_id')->where('created_by', $request->user()->id);
                    });
            });
        });
    }

    private function authorizeAccountAccess(Request $request, int $accountId): void
    {
        abort_unless($this->visibleAccounts($request)->whereKey($accountId)->exists(), 403, 'Kas kecil ini ditugaskan kepada user lain.');
    }
}
