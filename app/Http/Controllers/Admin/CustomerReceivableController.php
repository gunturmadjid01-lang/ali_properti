<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\CustomerReceipt;
use App\Models\HousingReservation;
use App\Models\MasterBank;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\ReceivableSetting;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class CustomerReceivableController extends Controller
{
    private function allow(Request $r, string $permission): void
    {
        abort_unless($r->user()?->can($permission) || $r->user()?->hasRole('super_admin'), 403);
    }

    public function receivables(Request $request): Response
    {
        $this->allow($request, 'receivables.view');
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $period = in_array($request->query('period'), ['daily', 'monthly', 'yearly'], true) ? $request->query('period') : 'monthly';
        $year = min(2100, max(2000, (int) $request->query('year', today()->year)));
        $month = min(12, max(1, (int) $request->query('month', today()->month)));
        $rows = PaymentSchedule::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'housingReservation.customer', 'housingReservation.unit.perumahan'])
            ->where('record_status', 'locked')->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('invoice_no', 'like', "%{$search}%")->orWhereHas('salesTransaction.customer', fn (Builder $q) => $q->where('nama', 'like', "%{$search}%"))->orWhereHas('salesTransaction', fn (Builder $q) => $q->where('transaction_no', 'like', "%{$search}%"))))
            ->when($status === 'overdue', fn (Builder $q) => $q->whereDate('due_date', '<', today())->whereColumn('paid_amount', '<', 'amount'))
            ->orderBy('due_date')->paginate(15)->withQueryString()->through(fn (PaymentSchedule $row) => $this->scheduleRow($row));
        $base = PaymentSchedule::query()->where('record_status', 'locked');
        // Portable for SQLite (local/testing) and MySQL/PostgreSQL (production).
        $remaining = 'CASE WHEN amount > paid_amount THEN amount - paid_amount ELSE 0 END';

        return Inertia::render('Admin/Receivables/Index', ['title' => 'Daftar Piutang Customer', 'rows' => $rows, 'filters' => compact('search', 'status'), 'summary' => ['bill' => (float) (clone $base)->sum('amount'), 'paid' => (float) (clone $base)->sum('paid_amount'), 'remaining' => (float) (clone $base)->selectRaw("SUM({$remaining}) total")->value('total'), 'overdue' => (float) (clone $base)->whereDate('due_date', '<', today())->selectRaw("SUM({$remaining}) total")->value('total')], 'statistics' => $this->receivableStatistics($period, $year, $month), 'canCreateReceipt' => $request->user()?->can('customer-receipts.create') || $request->user()?->hasRole('super_admin')]);
    }

    private function receivableStatistics(string $period, int $year, int $month): array
    {
        $selected = CarbonImmutable::create($year, $month, 1);
        [$start, $end] = match ($period) {
            'daily' => [$selected->startOfMonth(), $selected->endOfMonth()],
            'yearly' => [$selected->subYears(4)->startOfYear(), $selected->endOfYear()],
            default => [$selected->startOfYear(), $selected->endOfYear()],
        };
        $keyFormat = match ($period) {
            'daily' => 'Y-m-d',
            'yearly' => 'Y',
            default => 'Y-m',
        };
        $values = PaymentSchedule::query()->where('record_status', 'locked')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get(['due_date', 'amount', 'paid_amount'])
            ->groupBy(fn (PaymentSchedule $row) => $row->due_date->format($keyFormat))
            ->map(fn ($rows) => [
                'bill' => (float) $rows->sum('amount'),
                'paid' => (float) $rows->sum('paid_amount'),
                'remaining' => (float) $rows->sum(fn ($row) => max(0, (float) $row->amount - (float) $row->paid_amount)),
            ]);
        $cursor = $start;
        $buckets = [];
        while ($cursor->lte($end)) {
            $key = $cursor->format($keyFormat);
            $label = match ($period) {
                'daily' => $cursor->locale('id')->translatedFormat('l, d F Y'),
                'yearly' => $cursor->format('Y'),
                default => $cursor->locale('id')->translatedFormat('F Y'),
            };
            $buckets[] = ['key' => $key, 'label' => $label, ...($values->get($key) ?? ['bill' => 0.0, 'paid' => 0.0, 'remaining' => 0.0])];
            $cursor = match ($period) {
                'daily' => $cursor->addDay(),
                'yearly' => $cursor->addYear(),
                default => $cursor->addMonth(),
            };
        }

        return ['period' => $period, 'year' => $year, 'month' => $month, 'buckets' => $buckets];
    }

    public function dueMonitor(Request $request): Response
    {
        $this->allow($request, 'receivables.view');
        $setting = ReceivableSetting::query()->whereNull('perumahan_id')->whereNull('payment_method')->first()
            ?? new ReceivableSetting(['warning_days' => 14, 'urgent_days' => 3, 'issue_days_before_due' => 14, 'grace_period_days' => 0, 'is_active' => true]);
        $filters = $request->validate([
            'search' => 'nullable|string|max:150', 'payment_method' => 'nullable|in:cash_bertahap,kpr_developer',
            'perumahan_id' => 'nullable|integer|exists:perumahans,id', 'marketing_id' => 'nullable|integer|exists:users,id',
            'urgency' => 'nullable|in:overdue,urgent,warning,upcoming', 'payment_status' => 'nullable|in:belum_dibayar,sebagian',
            'due_from' => 'nullable|date', 'due_to' => 'nullable|date|after_or_equal:due_from',
            'amount_min' => 'nullable|numeric|min:0', 'amount_max' => 'nullable|numeric|min:0', 'has_remaining' => 'nullable|boolean',
        ]);
        $warningDays = (int) $setting->warning_days;
        $urgentDays = (int) $setting->urgent_days;
        $query = PaymentSchedule::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'salesTransaction.marketing'])
            ->where('record_status', 'locked')->whereColumn('paid_amount', '<', 'amount')
            ->whereHas('salesTransaction', fn (Builder $query) => $query->whereIn('payment_method', ['cash_bertahap', 'kpr_developer']))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn (Builder $query) => $query->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('salesTransaction', fn (Builder $query) => $query->where('transaction_no', 'like', "%{$search}%"))
                    ->orWhereHas('salesTransaction.customer', fn (Builder $query) => $query->where('nama', 'like', "%{$search}%")->orWhere('telepon', 'like', "%{$search}%")));
            })
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $value) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('payment_method', $value)))
            ->when($filters['perumahan_id'] ?? null, fn (Builder $query, int $value) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('perumahan_id', $value)))
            ->when($filters['marketing_id'] ?? null, fn (Builder $query, int $value) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('marketing_user_id', $value)))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('due_date', '>=', $value))
            ->when($filters['due_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('due_date', '<=', $value))
            ->when($filters['amount_min'] ?? null, fn (Builder $query, $value) => $query->whereRaw('(amount-paid_amount) >= ?', [$value]))
            ->when($filters['amount_max'] ?? null, fn (Builder $query, $value) => $query->whereRaw('(amount-paid_amount) <= ?', [$value]))
            ->when($filters['urgency'] ?? null, fn (Builder $query, string $value) => match ($value) {
                'overdue' => $query->whereDate('due_date', '<', today()),
                'urgent' => $query->whereBetween('due_date', [today(), today()->addDays($urgentDays)]),
                'warning' => $query->whereBetween('due_date', [today()->addDays($urgentDays + 1), today()->addDays($warningDays)]),
                'upcoming' => $query->whereDate('due_date', '>', today()->addDays($warningDays)),
            });

        $summaryQuery = clone $query;
        $rows = $query->orderBy('due_date')->paginate(20)->withQueryString()->through(fn (PaymentSchedule $row) => [
            ...$this->scheduleRow($row),
            'method' => $row->salesTransaction?->payment_method,
            'method_label' => $row->salesTransaction?->payment_method === 'kpr_developer' ? 'KPR Developer' : 'Cash Bertahap',
            'marketing' => $row->salesTransaction?->marketing?->name,
            'phone' => $row->salesTransaction?->customer?->telepon,
            'transaction_id' => $row->sales_transaction_id,
            'receipt_url' => route('admin.customer-receipts.create', ['transaction' => $row->sales_transaction_id], false),
        ]);
        $all = $summaryQuery->get(['amount', 'paid_amount', 'due_date']);
        $remaining = fn ($row) => max(0, (float) $row->amount - (float) $row->paid_amount);

        return Inertia::render('Admin/Receivables/DueMonitor', [
            'title' => 'Monitoring Jatuh Tempo Pembayaran', 'rows' => $rows, 'filters' => $filters,
            'summary' => ['count' => $all->count(), 'remaining' => $all->sum($remaining), 'overdue_count' => $all->filter(fn ($row) => $row->due_date->isBefore(today()))->count(), 'overdue_amount' => $all->filter(fn ($row) => $row->due_date->isBefore(today()))->sum($remaining), 'due_7_days' => $all->filter(fn ($row) => $row->due_date->between(today(), today()->addDays(7)))->count()],
            'setting' => ['warning_days' => $warningDays, 'urgent_days' => $urgentDays],
            'options' => ['perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]), 'marketings' => User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['marketing', 'area_marketing']))->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name])],
            'canManageSetting' => $request->user()?->can('receivables.settings') || $request->user()?->hasAnyRole(['owner', 'super_admin']),
            'canCreateReceipt' => $request->user()?->can('customer-receipts.create') || $request->user()?->hasRole('super_admin'),
        ]);
    }

    public function updateDueSetting(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('receivables.settings') || $request->user()?->hasAnyRole(['owner', 'super_admin']), 403);
        $data = $request->validate(['warning_days' => 'required|integer|min:1|max:90', 'urgent_days' => 'required|integer|min:0|max:30|lte:warning_days']);
        $setting = ReceivableSetting::query()->whereNull('perumahan_id')->whereNull('payment_method')->first() ?? new ReceivableSetting;
        $setting->fill([...$data, 'perumahan_id' => null, 'payment_method' => null, 'is_active' => true])->save();

        return back()->with('success', 'Pengaturan peringatan jatuh tempo berhasil disimpan.');
    }

    public function receipts(Request $request): Response
    {
        $this->allow($request, 'customer-receipts.view');
        $filters = $request->validate([
            'search' => 'nullable|string|max:150', 'status' => 'nullable|in:draft,pending_approval,posted,rejected',
            'purpose' => 'nullable|in:booking_fee,down_payment,invoice_payment,accelerated_payment,overpayment,other',
            'method' => 'nullable|in:transfer,cash,giro,virtual_account,lainnya',
            'perumahan_id' => 'nullable|integer|exists:perumahans,id', 'creator_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from',
            'amount_min' => 'nullable|numeric|min:0', 'amount_max' => 'nullable|numeric|min:0',
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = CustomerReceipt::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'housingReservation.customer', 'housingReservation.unit.perumahan', 'bankAccount', 'creator', 'allocations.schedule', 'journal'])
            ->where(fn (Builder $q) => $q->where('record_status', 'locked')->orWhere('created_by', $request->user()?->id))
            ->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('receipt_no', 'like', "%{$search}%")
                ->orWhere('bank_reference', 'like', "%{$search}%")->orWhere('sender_name', 'like', "%{$search}%")
                ->orWhereHas('salesTransaction', fn (Builder $q) => $q->where('transaction_no', 'like', "%{$search}%"))
                ->orWhereHas('salesTransaction.customer', fn (Builder $q) => $q->where('nama', 'like', "%{$search}%")->orWhere('telepon', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($filters['purpose'] ?? null, fn (Builder $q, string $value) => $q->where('receipt_purpose', $value))
            ->when($filters['method'] ?? null, fn (Builder $q, string $value) => $q->where('payment_method', $value))
            ->when($filters['perumahan_id'] ?? null, fn (Builder $q, int $value) => $q->whereHas('salesTransaction', fn (Builder $q) => $q->where('perumahan_id', $value)))
            ->when($filters['creator_id'] ?? null, fn (Builder $q, int $value) => $q->where('created_by', $value))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $value) => $q->whereDate('payment_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $value) => $q->whereDate('payment_date', '<=', $value))
            ->when($filters['amount_min'] ?? null, fn (Builder $q, $value) => $q->where('amount', '>=', $value))
            ->when($filters['amount_max'] ?? null, fn (Builder $q, $value) => $q->where('amount', '<=', $value));
        $summaryQuery = clone $query;
        $rows = $query->latest('payment_date')->latest('id')->paginate(12)->withQueryString()->through(fn (CustomerReceipt $r) => $this->receiptRow($r) + [
            'housing' => $r->salesTransaction?->housingProject?->nama_perusahaan ?? $r->housingReservation?->unit?->perumahan?->nama_perusahaan, 'purpose' => $r->receipt_purpose,
            'sender_name' => $r->sender_name, 'sender_bank' => $r->sender_bank, 'bank_reference' => $r->bank_reference,
            'creator' => $r->creator?->name, 'allocated' => (float) $r->allocations->whereNotNull('payment_schedule_id')->sum('amount'),
            'deposit' => (float) $r->allocations->whereNull('payment_schedule_id')->sum('amount'), 'journal_no' => $r->journal?->nomor_jurnal,
            'has_proof' => (bool) $r->proof_path, 'proof_url' => $r->proof_path ? route('admin.customer-receipts.proof', $r, absolute: false) : null,
        ]);
        $reservationApprovals = HousingReservation::query()
            ->with(['customer', 'unit.perumahan', 'creator', 'fundBank', 'pettyCashAccount', 'latestApproval'])
            ->where('payment_method', '!=', 'cash')
            ->whereHas('latestApproval', fn (Builder $q) => $q
                ->where('status', ApprovalRequest::STATUS_PENDING))
            ->latest('locked_at')
            ->get()
            ->map(function (HousingReservation $row) {
                $workflow = app(ApprovalWorkflowService::class);
                $reviewerRoleIds = $row->latestApproval
                    ? $workflow->reviewerRoleIds($row->latestApproval)
                    : collect();

                return [
                'id' => $row->id,
                'approval_id' => $row->latestApproval?->id,
                'reservation_no' => $row->reservation_no,
                'invoice_no' => $row->invoice_no,
                'customer' => $row->customer?->nama,
                'housing' => $row->unit?->perumahan?->nama_perusahaan,
                'unit' => trim(($row->unit?->kode_nlok ?? '').' / '.($row->unit?->nomor_rumah ?? '')),
                'date' => optional($row->payment_submitted_at)->format('Y-m-d'),
                'amount' => (float) $row->booking_fee,
                'method' => $row->payment_channel,
                'sender_name' => $row->payment_sender_name,
                'destination' => $row->payment_channel === 'cash'
                    ? trim(($row->pettyCashAccount?->code ?? '').' - '.($row->pettyCashAccount?->name ?? ''), ' -')
                    : trim(($row->fundBank?->nama_bank ?? '').' - '.($row->fundBank?->nomor_rekening ?? ''), ' -'),
                'creator' => $row->creator?->name,
                'approval_step' => $row->latestApproval?->current_step,
                'approval_total' => $row->latestApproval?->total_steps,
                'can_review' => $row->latestApproval ? $workflow->canReview($row->latestApproval) : false,
                'reviewer_roles' => Role::query()->whereIn('id', $reviewerRoleIds)->orderBy('name')->pluck('name')->values(),
                'requires_finance_verification' => $row->latestApproval?->current_step === 1,
                'review_url' => route('admin.customer-receipts.reservation-review', $row, absolute: false),
                'approve_url' => route('admin.approval.requests.approve', $row->latestApproval, absolute: false),
                'reject_url' => route('admin.approval.requests.reject', $row->latestApproval, absolute: false),
                ];
            });

        return Inertia::render('Admin/CustomerReceipts/Index', [
            'title' => 'Penerimaan Pelanggan', 'rows' => $rows, 'filters' => $filters,
            'reservationApprovals' => $reservationApprovals,
            'summary' => ['count' => (clone $summaryQuery)->count(), 'total' => (float) (clone $summaryQuery)->sum('amount'), 'posted' => (float) (clone $summaryQuery)->where('status', 'posted')->sum('amount'), 'pending' => (float) (clone $summaryQuery)->whereIn('status', ['draft', 'pending_approval'])->sum('amount')],
            'options' => ['perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]), 'creators' => User::query()->whereIn('id', CustomerReceipt::query()->whereNotNull('created_by')->select('created_by'))->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name])],
            'canCreate' => $request->user()?->can('customer-receipts.create') || $request->user()?->hasRole('super_admin'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->allow($request, 'customer-receipts.create');
        $transactions = SalesTransaction::query()->finalized()->with(['customer', 'housingProject', 'housingUnit', 'paymentSchedules'])->where('status', 'active')->latest()->limit(500)->get()->map(fn (SalesTransaction $t) => ['value' => (string) $t->id, 'label' => $t->transaction_no.' — '.$t->customer?->nama.' — '.$t->housingProject?->nama_perusahaan.' / '.$t->housingUnit?->nomor_rumah, 'customer' => $t->customer?->nama, 'unit' => $t->housingUnit?->nomor_rumah, 'schedules' => $t->paymentSchedules->where('record_status', 'locked')->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->invoice_no.' — '.$s->description.' — '.optional($s->due_date)->format('d/m/Y'), 'remaining' => max(0, (float) $s->amount - (float) $s->paid_amount)])->values()->all()]);
        $banks = MasterBank::query()->finalized()->with('perumahan')->where('status', 'aktif')->get()->map(fn ($b) => ['value' => (string) $b->id, 'label' => $b->nama_bank.' — '.$b->nomor_rekening.' — '.$b->nama_rekening.' ('.$b->perumahan?->nama_perusahaan.')']);

        return Inertia::render('Admin/CustomerReceipts/Form', ['title' => 'Input Penerimaan Customer', 'transactions' => $transactions, 'banks' => $banks, 'defaults' => ['transaction' => (string) $request->query('transaction', ''), 'purpose' => (string) $request->query('purpose', 'invoice_payment')], 'storeUrl' => route('admin.customer-receipts.store', absolute: false)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'customer-receipts.create');
        $data = $request->validate(['sales_transaction_id' => 'required|exists:sales_transactions,id', 'master_bank_id' => 'nullable|exists:master_banks,id', 'payment_date' => 'required|date', 'amount' => 'required|numeric|min:1', 'payment_method' => ['required', Rule::in(['transfer', 'cash', 'giro', 'virtual_account', 'lainnya'])], 'receipt_purpose' => ['required', Rule::in(['booking_fee', 'down_payment', 'invoice_payment', 'accelerated_payment', 'overpayment', 'other'])], 'bank_reference' => 'nullable|string|max:100', 'sender_bank' => 'nullable|string|max:100', 'sender_name' => 'nullable|string|max:150', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 'notes' => 'nullable|string', 'allocations' => 'nullable|array', 'allocations.*.payment_schedule_id' => 'nullable|exists:payment_schedules,id', 'allocations.*.amount' => 'required|numeric|min:0.01']);
        if (in_array($data['payment_method'], ['transfer', 'virtual_account'], true) && ! $request->hasFile('proof')) {
            return back()->withErrors(['proof' => 'Bukti transfer wajib diunggah.'])->withInput();
        }
        $receipt = DB::transaction(function () use ($request, $data) {
            $alloc = collect($data['allocations'] ?? [])->filter(fn ($a) => (float) $a['amount'] > 0);
            abort_if($alloc->sum('amount') > (float) $data['amount'], 422, 'Total alokasi melebihi penerimaan.');
            foreach ($alloc as $a) {
                abort_unless(PaymentSchedule::whereKey($a['payment_schedule_id'])->where('sales_transaction_id', $data['sales_transaction_id'])->exists(), 422, 'Tagihan bukan milik transaksi.');
            }unset($data['allocations'],$data['proof']);
            $path = $request->file('proof')?->store('customer-receipts/'.now()->format('Y/m'), 'public');
            $r = CustomerReceipt::create([...$data, 'receipt_no' => 'RCV/'.now()->format('Y').'/'.str_pad((string) (CustomerReceipt::withTrashed()->count() + 1), 7, '0', STR_PAD_LEFT), 'proof_path' => $path, 'proof_original_name' => $request->file('proof')?->getClientOriginalName(), 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
            foreach ($alloc as $a) {
                $r->allocations()->create([...$a, 'allocation_type' => 'invoice']);
            }$unallocated = (float) $r->amount - (float) $alloc->sum('amount');
            if ($unallocated > 0) {
                $r->allocations()->create(['amount' => $unallocated, 'allocation_type' => 'deposit', 'notes' => 'Saldo customer belum teralokasi']);
            }

            return $r;
        });

        return to_route('admin.customer-receipts.index')->with('success', 'Penerimaan disimpan sebagai draf. Periksa lalu lock untuk approval.');
    }

    public function lock(Request $request, CustomerReceipt $receipt, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'customer-receipts.lock');
        abort_unless($receipt->created_by === $request->user()?->id, 403);
        abort_unless($receipt->record_status === 'draft', 422);
        abort_if($receipt->allocations()->sum('amount') != (float) $receipt->amount, 422, 'Seluruh nominal harus dialokasikan ke tagihan atau deposit.');
        $receipt->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()?->id]);
        $approval = $workflow->submitLocked($receipt, 'customer-receipt');

        return back()->with('success', $approval->status === 'approved' ? 'Penerimaan otomatis disetujui dan diposting.' : "Penerimaan masuk approval tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function unlock(Request $request, CustomerReceipt $receipt, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'customer-receipts.unlock');
        abort_if($receipt->status === 'posted', 422, 'Penerimaan sudah diposting; gunakan reversal.');
        $workflow->cancelPendingLock($receipt);
        $receipt->update(['record_status' => 'draft', 'status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Penerimaan kembali menjadi draf milik pembuat.');
    }

    public function review(Request $request, CustomerReceipt $receipt, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::query()->where(['model_type' => CustomerReceipt::class, 'model_id' => $receipt->id, 'status' => 'pending'])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);

        return back()->with('success', $decision === 'approve' ? 'Tahap approval disetujui.' : 'Penerimaan ditolak.');
    }

    public function invoice(Request $request, PaymentSchedule $schedule): Response
    {
        $this->allow($request, 'receivables.print');
        $schedule->load(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'allocations.receipt']);

        return Inertia::render('Admin/Receivables/Invoice', ['title' => 'Invoice '.$schedule->invoice_no, 'invoice' => $this->scheduleRow($schedule) + ['transaction' => $schedule->salesTransaction->transaction_no, 'customer' => $schedule->salesTransaction->customer?->nama, 'housing' => $schedule->salesTransaction->housingProject?->nama_perusahaan, 'unit' => $schedule->salesTransaction->housingUnit?->nomor_rumah, 'description' => $schedule->description, 'payments' => $schedule->allocations->filter(fn ($allocation) => $allocation->receipt)->sortBy(fn ($allocation) => $allocation->receipt->payment_date)->values()->map(fn ($allocation) => ['receipt_no' => $allocation->receipt->receipt_no, 'date' => optional($allocation->receipt->payment_date)->format('Y-m-d'), 'amount' => (float) $allocation->amount, 'method' => $allocation->receipt->payment_method, 'status' => $allocation->receipt->status, 'url' => route('admin.customer-receipts.preview', $allocation->receipt, absolute: false)])->all()]]);
    }

    public function receiptPreview(Request $request, CustomerReceipt $receipt): Response
    {
        $this->allow($request, 'customer-receipts.print');
        $receipt->load(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'housingReservation.customer', 'housingReservation.unit.perumahan', 'allocations.schedule', 'bankAccount']);

        $transaction = $receipt->salesTransaction;
        $reservation = $receipt->housingReservation;
        $payload = $this->receiptRow($receipt) + [
            'transaction' => $transaction?->transaction_no ?? $reservation?->reservation_no,
            'customer' => $transaction?->customer?->nama ?? $reservation?->customer?->nama,
            'housing' => $transaction?->housingProject?->nama_perusahaan ?? $reservation?->unit?->perumahan?->nama_perusahaan,
            'unit' => $transaction?->housingUnit?->nomor_rumah ?? trim(($reservation?->unit?->kode_nlok ?? '').' / '.($reservation?->unit?->nomor_rumah ?? '')),
            'bank' => $receipt->bankAccount
                ? $receipt->bankAccount->nama_bank.' - '.$receipt->bankAccount->nomor_rekening
                : ($receipt->payment_method === 'cash' ? 'Kas Kecil Marketing' : 'Rekening tujuan tidak tercatat'),
            'allocations' => $receipt->allocations->map(fn ($allocation) => [
                'label' => $allocation->schedule
                    ? trim(($allocation->schedule->invoice_no ?? '').' - '.($allocation->schedule->description ?? ''), ' -')
                    : 'Deposit belum teralokasi',
                'amount' => (float) $allocation->amount,
            ])->all(),
        ];

        return Inertia::render('Admin/CustomerReceipts/Preview', ['title' => 'Penerimaan '.$receipt->receipt_no, 'receipt' => $payload]);
    }

    public function receiptProof(Request $request, CustomerReceipt $receipt)
    {
        $this->allow($request, 'customer-receipts.view');
        abort_unless($receipt->proof_path && Storage::disk('public')->exists($receipt->proof_path), 404);

        return Storage::disk('public')->response($receipt->proof_path, $receipt->proof_original_name);
    }

    private function scheduleRow(PaymentSchedule $r): array
    {
        $setting = ReceivableSetting::query()->where(fn ($q) => $q->whereNull('perumahan_id')->orWhere('perumahan_id', $r->salesTransaction?->perumahan_id))->orderByDesc('perumahan_id')->first();
        $days = today()->diffInDays($r->due_date, false);
        $remaining = max(0, (float) $r->amount - (float) $r->paid_amount);
        $urgency = $remaining <= 0 ? 'paid' : ($days < 0 ? 'overdue' : ($days <= (int) ($setting?->urgent_days ?? 3) ? 'urgent' : ($days <= (int) ($setting?->warning_days ?? 14) ? 'warning' : 'safe')));

        return ['id' => $r->id, 'invoice_no' => $r->invoice_no, 'reference' => $r->salesTransaction?->transaction_no ?? $r->housingReservation?->reservation_no, 'customer' => $r->salesTransaction?->customer?->nama ?? $r->housingReservation?->customer?->nama, 'housing' => $r->salesTransaction?->housingProject?->nama_perusahaan ?? $r->housingReservation?->unit?->perumahan?->nama_perusahaan, 'unit' => $r->salesTransaction?->housingUnit?->nomor_rumah ?? $r->housingReservation?->unit?->nomor_rumah, 'type' => $r->description, 'issued_at' => optional($r->issued_at)->format('Y-m-d'), 'due_date' => optional($r->due_date)->format('Y-m-d'), 'bill' => (float) $r->amount, 'paid' => (float) $r->paid_amount, 'remaining' => $remaining, 'status' => $r->status, 'urgency' => $urgency, 'days' => $days, 'invoice_url' => $r->sales_transaction_id ? route('admin.receivables.invoice', $r, absolute: false) : null];
    }

    private function receiptRow(CustomerReceipt $r): array
    {
        $approval = ApprovalRequest::query()->where(['model_type' => CustomerReceipt::class, 'model_id' => $r->id])->latest()->first();

        return ['id' => $r->id, 'receipt_no' => $r->receipt_no, 'transaction' => $r->salesTransaction?->transaction_no ?? $r->housingReservation?->reservation_no, 'customer' => $r->salesTransaction?->customer?->nama ?? $r->housingReservation?->customer?->nama, 'unit' => $r->salesTransaction?->housingUnit?->nomor_rumah ?? $r->housingReservation?->unit?->nomor_rumah, 'date' => optional($r->payment_date)->format('Y-m-d'), 'amount' => (float) $r->amount, 'method' => $r->payment_method, 'bank' => $r->bankAccount ? $r->bankAccount->nama_bank.' - '.$r->bankAccount->nomor_rekening : ($r->payment_method === 'cash' ? 'Kas Kecil Marketing' : null), 'status' => $r->status, 'record_status' => $r->record_status, 'approval_step' => $approval?->current_step, 'approval_total' => $approval?->total_steps, 'approval_status' => $approval?->status, 'can_review' => $approval ? app(ApprovalWorkflowService::class)->canReview($approval) : false, 'can_lock' => $r->record_status === 'draft' && $r->created_by === auth()->id(), 'can_unlock' => $r->record_status === 'locked' && $r->status !== 'posted' && (auth()->user()?->can('customer-receipts.unlock') || auth()->user()?->hasRole('super_admin')), 'preview_url' => route('admin.customer-receipts.preview', $r, absolute: false)];
    }
}
