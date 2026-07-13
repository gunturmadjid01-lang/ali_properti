<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\KelompokHpp;
use App\Models\Perumahan;
use App\Models\PettyCashAccount;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFunding;
use App\Models\PettyCashLedger;
use App\Models\TahapanPembangunan;
use App\Services\PettyCashService;
use App\Support\CodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PettyCashController extends Controller
{
    public function __construct(private readonly PettyCashService $service) {}

    public function index(Request $request, string $section = 'saldo'): Response
    {
        $this->authorizeView($request);
        abort_unless(in_array($section, ['saldo', 'pengisian', 'pengeluaran', 'laporan'], true), 404);
        $accountId = $request->integer('account_id') ?: null;
        $from = $request->query('from');
        $to = $request->query('to');

        $accounts = PettyCashAccount::query()->with('branch:id,nama_cabang')->orderBy('name')->get();
        $fundings = PettyCashFunding::query()->with(['account:id,name,code', 'requester:id,name', 'approver:id,name'])
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->latest('request_date')->latest('id')->limit(100)->get();
        $expenses = PettyCashExpense::query()->with(['account:id,name,code', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'creator:id,name'])
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->latest('expense_date')->latest('id')->limit(150)->get();
        $ledgers = PettyCashLedger::query()->with('account:id,name,code')
            ->when($accountId, fn ($q) => $q->where('petty_cash_account_id', $accountId))
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->latest('transaction_date')->latest('id')->limit(200)->get();

        return Inertia::render('Admin/PettyCash/Index', [
            'title' => 'Kas Kecil',
            'section' => $section,
            'filters' => ['account_id' => $accountId ? (string) $accountId : '', 'from' => $from, 'to' => $to],
            'accounts' => $accounts->map(fn ($row) => [
                'id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'branch' => $row->branch?->nama_cabang,
                'target_amount' => (float) $row->target_amount, 'balance' => (float) $row->balance,
                'minimum_balance' => (float) $row->minimum_balance, 'status' => $row->status,
                'is_low' => (float) $row->balance <= (float) $row->minimum_balance,
            ]),
            'fundings' => $fundings->map(fn ($row) => [
                'id' => $row->id, 'account' => $row->account?->name, 'number' => $row->number, 'type' => $row->type,
                'request_date' => $row->request_date->format('Y-m-d'), 'amount' => (float) $row->amount, 'status' => $row->status,
                'requester' => $row->requester?->name, 'approver' => $row->approver?->name,
                'approved_at' => $row->approved_at?->format('Y-m-d H:i'), 'request_notes' => $row->request_notes,
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
                'can_create_account' => $this->canApprove($request),
            ],
            'hppCategories' => PettyCashService::HPP_CATEGORIES,
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        abort_unless($this->canApprove($request), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'branch_id' => ['nullable', 'exists:cabang_perusahaans,id'],
            'target_amount' => ['required', 'numeric', 'min:1'], 'minimum_balance' => ['required', 'numeric', 'min:0'],
            'request_date' => ['required', 'date'], 'request_notes' => ['nullable', 'string', 'max:1000'],
            'request_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ]);
        $path = $request->file('request_proof')->store('petty-cash/funding-requests', 'public');

        DB::transaction(function () use ($data, $path, $request): void {
            $account = PettyCashAccount::query()->create([
                'code' => CodeGenerator::next(PettyCashAccount::class, 'code', 'KK'), 'name' => $data['name'],
                'branch_id' => $data['branch_id'] ?? null, 'target_amount' => $data['target_amount'],
                'minimum_balance' => $data['minimum_balance'], 'balance' => 0,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
            ]);
            PettyCashFunding::query()->create([
                'petty_cash_account_id' => $account->id, 'number' => CodeGenerator::next(PettyCashFunding::class, 'number', 'PKK'),
                'type' => 'initial', 'request_date' => $data['request_date'], 'amount' => $data['target_amount'],
                'status' => PettyCashFunding::PENDING, 'request_notes' => $data['request_notes'] ?? null,
                'request_proof_path' => $path, 'requested_by' => $request->user()->id, 'submitted_at' => now(),
            ]);
        });

        return redirect()->route('admin.petty-cash.index', ['section' => 'pengisian'])->with('success', 'Pembentukan kas kecil diajukan untuk approval.');
    }

    public function storeFunding(Request $request): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);
        $data = $request->validate([
            'petty_cash_account_id' => ['required', 'exists:petty_cash_accounts,id'], 'request_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'], 'request_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([PettyCashFunding::DRAFT, PettyCashFunding::PENDING])],
        ]);
        PettyCashFunding::query()->create([
            ...$data, 'number' => CodeGenerator::next(PettyCashFunding::class, 'number', 'RKK'), 'type' => 'replenishment',
            'requested_by' => $request->user()->id, 'submitted_at' => $data['status'] === PettyCashFunding::PENDING ? now() : null,
        ]);
        return back()->with('success', $data['status'] === PettyCashFunding::PENDING ? 'Permohonan pengisian dikirim untuk approval.' : 'Draft pengisian berhasil disimpan.');
    }

    public function submitFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        abort_unless($this->canCreate($request) && $funding->requested_by === $request->user()->id, 403);
        abort_unless($funding->status === PettyCashFunding::DRAFT, 422);
        $funding->update(['status' => PettyCashFunding::PENDING, 'submitted_at' => now()]);
        return back()->with('success', 'Permohonan dikirim untuk approval.');
    }

    public function approveFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        abort_unless($this->canApprove($request), 403);
        $data = $request->validate([
            'approval_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $path = $request->file('approval_proof')->store('petty-cash/approval-proofs', 'public');
        $this->service->approveFunding($funding, $request->user()->id, $path, $data['approval_notes'] ?? null);
        return back()->with('success', 'Pengisian disetujui dan saldo kas kecil bertambah.');
    }

    public function rejectFunding(Request $request, PettyCashFunding $funding): RedirectResponse
    {
        abort_unless($this->canApprove($request), 403);
        $data = $request->validate(['rejection_notes' => ['required', 'string', 'max:1000']]);
        abort_unless($funding->status === PettyCashFunding::PENDING, 422);
        $funding->update(['status' => PettyCashFunding::REJECTED, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'rejection_notes' => $data['rejection_notes']]);
        return back()->with('success', 'Permohonan pengisian ditolak.');
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
        $data['proof_path'] = $request->file('proof')->store('petty-cash/expense-proofs', 'public');
        $data['number'] = CodeGenerator::next(PettyCashExpense::class, 'number', 'KKO');
        $this->service->createExpense($data, $request->user()->id);
        return back()->with('success', 'Pengeluaran tercatat, saldo berkurang, dan tujuan biaya terdeteksi otomatis.');
    }

    private function options(): array
    {
        return [
            'branches' => CabangPerusahaan::query()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_cabang]),
            'perumahans' => Perumahan::query()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_perusahaan]),
            'units' => DetailRumah::query()->orderBy('perumahan_id')->orderBy('kode_nlok')->orderBy('nomor_rumah')->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => trim($r->kode_nlok.' '.$r->nomor_rumah), 'perumahan_id' => (string) $r->perumahan_id]),
            'hppGroups' => KelompokHpp::query()->orderBy('nama_hpp')->get(['id', 'nama_hpp'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_hpp]),
            'stages' => TahapanPembangunan::query()->orderBy('urutan')->get(['id', 'nama_tahapan'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->nama_tahapan]),
        ];
    }

    private function authorizeView(Request $request): void { abort_unless($request->user()?->hasAnyRole(['super_admin', 'owner', 'manager', 'keuangan']) || $request->user()?->can('petty-cash.view'), 403); }
    private function canCreate(Request $request): bool { return (bool) ($request->user()?->hasAnyRole(['super_admin', 'owner', 'manager', 'keuangan']) || $request->user()?->can('petty-cash.create')); }
    private function canApprove(Request $request): bool { return (bool) ($request->user()?->hasAnyRole(['super_admin', 'owner', 'manager']) || $request->user()?->can('petty-cash.approve')); }
}
