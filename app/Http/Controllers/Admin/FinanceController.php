<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\MasterBank;
use App\Models\MaterialPurchase;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\SpkKontraktorPayment;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Services\AccountingService;
use App\Services\ApprovalWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    /** Pemasukan yang memang tidak mempunyai workflow transaksi khusus. */
    protected const MANUAL_INCOME_POSTS = [
        'Setoran Modal Awal',
        'Investasi Investor - Penyertaan Modal',
        'Pinjaman Investor',
        'Pendapatan Lain-lain',
    ];

    protected array $sections = [
        'dashboard' => 'Dashboard Keuangan',
        'pemasukan' => 'Pemasukan Kas & Bank',
        'pengeluaran' => 'Pengeluaran Kas & Bank',
        'daftar-akun' => 'Daftar Akun',
        'jurnal-umum' => 'Jurnal Umum',
        'buku-besar' => 'Buku Besar',
        'neraca-saldo' => 'Neraca Saldo',
        'laba-rugi' => 'Laba Rugi',
        'neraca' => 'Neraca',
        'arus-kas' => 'Arus Kas',
        'aging-piutang' => 'Aging Piutang Customer',
        'hutang' => 'Hutang Supplier & Kontraktor',
    ];

    protected array $sectionPermissions = [
        'dashboard' => 'keuangan.view',
        'pemasukan' => 'keuangan.view',
        'pengeluaran' => 'keuangan.view',
        'daftar-akun' => 'keuangan.view',
        'jurnal-umum' => 'keuangan.view',
        'buku-besar' => 'buku-besar.view',
        'neraca-saldo' => 'neraca-saldo.view',
        'laba-rugi' => 'laba-rugi.view',
        'neraca' => 'neraca.view',
        'arus-kas' => 'arus-kas.view',
        'aging-piutang' => 'receivables.view',
        'hutang' => 'hutang.view',
    ];

    public function show(Request $request, string $section): Response
    {
        abort_unless(array_key_exists($section, $this->sections), 404);
        $this->authorizeFinanceView($request, $section);

        [$from, $to] = $this->period($request);
        if ($section === 'arus-kas' && ! $request->filled('date_from') && ! $request->filled('date_to')) {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfMonth()->endOfDay();
        }
        $perumahanId = $this->perumahanId($request);

        return Inertia::render('Admin/Finance/Index', [
            'title' => $this->sections[$section],
            'section' => $section,
            'baseUrl' => route('admin.finance.show', $section, absolute: false),
            'permissions' => [
                'canCreate' => (bool) $request->user()?->can('keuangan.create') || $request->user()?->can('keuangan.manage'),
                'canUpdate' => (bool) $request->user()?->can('keuangan.update') || $request->user()?->can('keuangan.manage'),
                'canDelete' => (bool) $request->user()?->can('keuangan.delete') || $request->user()?->can('keuangan.manage'),
                'canExport' => (bool) $request->user()?->can('laporan.export') || $request->user()?->hasAnyRole(['owner', 'super_admin']),
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'perumahan_id' => $perumahanId ? (string) $perumahanId : '',
                'cabang_id' => (string) ($this->cabangId($request) ?? ''),
                'account_id' => (string) $request->query('account_id', ''),
            ],
            'options' => [
                'branches' => $this->branchOptions($request),
                'perumahans' => $this->perumahanOptions($request),
                'accounts' => ChartOfAccount::query()
                    ->where('status', 'aktif')
                    ->orderBy('kode_akun')
                    ->get(['id', 'kode_akun', 'nama_akun'])
                    ->map(fn (ChartOfAccount $row) => [
                        'value' => (string) $row->id,
                        'label' => $row->kode_akun.' - '.$row->nama_akun,
                    ]),
                'banks' => $this->bankOptions($request),
                'postTypes' => TipePost::query()
                    ->with(['debitAccount:id,kode_akun,nama_akun', 'creditAccount:id,kode_akun,nama_akun'])
                    ->where('status', 'aktif')
                    ->whereNotNull('debit_account_id')
                    ->whereNotNull('credit_account_id')
                    ->when(in_array($section, ['pemasukan', 'pengeluaran'], true), fn (Builder $query) => $query->where('jenis', $section))
                    ->when($section === 'pemasukan', fn (Builder $query) => $query->whereIn('nama_post', self::MANUAL_INCOME_POSTS))
                    ->orderBy('jenis')
                    ->orderBy('nama_post')
                    ->get()
                    ->map(fn (TipePost $row) => [
                        'value' => (string) $row->id,
                        'label' => ucwords($row->jenis).' - '.$row->nama_post,
                        'jenis' => $row->jenis,
                        'debit' => $row->debitAccount?->kode_akun.' - '.$row->debitAccount?->nama_akun,
                        'credit' => $row->creditAccount?->kode_akun.' - '.$row->creditAccount?->nama_akun,
                    ]),
            ],
            'data' => $this->data($request, $section, $from, $to, $perumahanId),
        ]);
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'keterangan' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.kredit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.keterangan' => ['nullable', 'string'],
        ]);

        $this->ensurePerumahanAllowed($request, $validated['perumahan_id'] ?? null);
        $lines = collect($validated['lines'])->map(fn (array $line) => [
            ...$line,
            'debit' => (float) ($line['debit'] ?? 0),
            'kredit' => (float) ($line['kredit'] ?? 0),
        ])->filter(fn (array $line) => $line['debit'] > 0 || $line['kredit'] > 0)->values();
        $debit = round($lines->sum('debit'), 2);
        $credit = round($lines->sum('kredit'), 2);

        abort_if($debit <= 0 || $debit !== $credit, 422, 'Jurnal harus balance dan memiliki nilai debit/kredit.');

        DB::transaction(function () use ($request, $validated, $lines, $debit, $credit): void {
            $journal = Journal::query()->create([
                'nomor_jurnal' => 'DRAFT-'.Str::uuid(),
                'tanggal' => $validated['tanggal'],
                'type' => 'manual',
                'record_status' => 'draft',
                'perumahan_id' => $validated['perumahan_id'] ?: null,
                'total_debit' => $debit,
                'total_kredit' => $credit,
                'keterangan' => $validated['keterangan'],
                'created_by' => $request->user()?->id,
            ]);

            $journal->details()->createMany($lines->all());
        });

        return back()->with('success', 'Draft jurnal umum berhasil disimpan. Periksa lalu lock untuk mengajukan approval.');
    }

    public function lockJournal(Request $request, Journal $journal, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        abort_unless($journal->type === 'manual' && $journal->record_status === 'draft', 422);
        abort_unless((int) $journal->created_by === (int) $request->user()?->id, 403);
        DB::transaction(function () use ($request, $journal, $workflow): void {
            $journal->forceFill(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()?->id])->save();
            $workflow->submitLocked($journal, 'manual-journal');
        });
        return back()->with('success', 'Jurnal dikunci dan diajukan mengikuti Setting Approval.');
    }

    public function unlockJournal(Request $request, Journal $journal, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()?->can('keuangan.update') || $request->user()?->hasAnyRole(['owner', 'super_admin']), 403);
        abort_unless($journal->type === 'manual' && $journal->record_status === 'locked' && ! $journal->posted_at, 422);
        DB::transaction(function () use ($journal, $workflow): void {
            $workflow->cancelPendingLock($journal);
            $journal->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();
        });
        return back()->with('success', 'Jurnal dibuka kembali menjadi draft pembuat.');
    }

    public function storeTransaction(Request $request, AccountingService $accounting): RedirectResponse
    {
        return $this->storeTransactionForType($request, $accounting);
    }

    public function storeIncome(Request $request, AccountingService $accounting): RedirectResponse
    {
        return $this->storeTransactionForType($request, $accounting, 'pemasukan');
    }

    public function storeExpense(Request $request, AccountingService $accounting): RedirectResponse
    {
        return $this->storeTransactionForType($request, $accounting, 'pengeluaran');
    }

    protected function storeTransactionForType(Request $request, AccountingService $accounting, ?string $expectedType = null): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate([
            'cabang_id' => ['required', 'exists:cabang_perusahaans,id'],
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tipe_post_id' => ['required', 'exists:tipe_posts,id'],
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'nomor_referensi' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['required', 'string'],
        ]);
        $this->ensureCabangAllowed($request, $validated['cabang_id']);

        $bank = MasterBank::query()
            ->whereKey($validated['master_bank_id'])
            ->where('cabang_id', $validated['cabang_id'])
            ->where('status', 'aktif')
            ->firstOrFail();
        $post = TipePost::query()
            ->whereKey($validated['tipe_post_id'])
            ->when($expectedType, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->where('status', 'aktif')
            ->whereNotNull('debit_account_id')
            ->whereNotNull('credit_account_id')
            ->firstOrFail();
        abort_if(
            $post->jenis === 'pemasukan' && ! in_array($post->nama_post, self::MANUAL_INCOME_POSTS, true),
            422,
            'Jenis pemasukan ini dicatat otomatis dari modul transaksi asal dan tidak boleh diposting manual.',
        );
        DB::transaction(function () use ($request, $validated, $bank, $post): void {
            TransaksiKeuangan::query()->create([
                'cabang_id' => $validated['cabang_id'],
                'perumahan_id' => $bank->perumahan_id,
                'master_bank_id' => $bank->id,
                'tipe_post_id' => $post->id,
                'tanggal' => $validated['tanggal'],
                'nominal' => $validated['nominal'],
                'nomor_referensi' => $validated['nomor_referensi'] ?: null,
                'source_type' => 'manual_finance',
                'status' => 'draft',
                'record_status' => 'draft',
                'keterangan' => $validated['keterangan'],
                'user_id' => $request->user()?->id,
            ]);

        });

        return back()->with('success', 'Draft transaksi '.$post->jenis.' berhasil disimpan. Periksa lalu lock untuk mengajukan approval.');
    }

    public function lockTransaction(Request $request, TransaksiKeuangan $transaction, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        abort_unless($transaction->source_type === 'manual_finance', 422, 'Hanya transaksi manual yang dapat diajukan dari halaman ini.');
        abort_unless($transaction->record_status === 'draft' && (int) $transaction->user_id === (int) $request->user()?->id, 403);

        DB::transaction(function () use ($request, $transaction, $workflow): void {
            $transaction->update([
                'record_status' => 'locked',
                'status' => 'pending_approval',
                'locked_at' => now(),
                'locked_by' => $request->user()?->id,
            ]);
            $workflow->submitLocked($transaction, 'financial-transaction');
        });

        return back()->with('success', 'Transaksi dikunci dan diajukan mengikuti Setting Approval.');
    }

    public function unlockTransaction(Request $request, TransaksiKeuangan $transaction, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('keuangan.update') || $request->user()?->hasAnyRole(['owner', 'super_admin']),
            403,
        );
        abort_unless($transaction->source_type === 'manual_finance' && $transaction->record_status === 'locked' && $transaction->status !== 'posted', 422);

        DB::transaction(function () use ($transaction, $workflow): void {
            $workflow->cancelPendingLock($transaction);
            $transaction->update([
                'record_status' => 'draft',
                'status' => 'draft',
                'locked_at' => null,
                'locked_by' => null,
            ]);
        });

        return back()->with('success', 'Transaksi dibuka kembali menjadi draft pembuat.');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate($this->accountRules());
        ChartOfAccount::query()->create([...$validated, 'is_system' => false]);

        return back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function updateAccount(Request $request, string $id): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $account = ChartOfAccount::query()->findOrFail($id);
        abort_if($account->is_system, 422, 'Akun sistem tidak dapat diubah.');
        $account->update($request->validate($this->accountRules($account->id)));

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    protected function data(Request $request, string $section, Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        return match ($section) {
            'dashboard' => $this->dashboardData($from, $to, $perumahanId),
            'pemasukan' => $this->manualTransactionData($request, $from, $to, 'pemasukan'),
            'pengeluaran' => $this->manualTransactionData($request, $from, $to, 'pengeluaran'),
            'daftar-akun' => $this->accountData(),
            'jurnal-umum' => $this->journalData($from, $to, $perumahanId),
            'buku-besar' => $this->ledgerData($request, $from, $to, $perumahanId),
            'neraca-saldo' => $this->trialBalanceData($from, $to, $perumahanId),
            'laba-rugi' => $this->profitLossData($from, $to, $perumahanId),
            'neraca' => $this->balanceSheetData($to, $perumahanId),
            'arus-kas' => $this->cashFlowData($from, $to, $perumahanId),
            'aging-piutang' => $this->receivableData($perumahanId),
            'hutang' => $this->payableData($perumahanId),
        };
    }

    protected function manualTransactionData(Request $request, Carbon $from, Carbon $to, ?string $type = null): array
    {
        $allowedBranches = $this->allowedCabangIds($request);
        $cabangId = $this->cabangId($request);
        $workflow = app(ApprovalWorkflowService::class);

        return [
            'rows' => TransaksiKeuangan::query()
                ->with([
                    'cabang:id,nama_cabang',
                    'perumahan:id,nama_perusahaan',
                    'masterBank:id,nama_bank,nomor_rekening',
                    'tipePost:id,nama_post,jenis',
                    'user:id,name',
                    'latestApproval',
                ])
                ->whereBetween('tanggal', [$from, $to])
                ->when($type, fn (Builder $query, string $type) => $query->whereHas('tipePost', fn (Builder $postQuery) => $postQuery->where('jenis', $type)))
                ->when(! $request->user()?->hasAnyRole(['owner', 'super_admin']), fn (Builder $query) => $query->whereIn('cabang_id', $allowedBranches))
                ->when($cabangId, fn (Builder $query, int $id) => $query->where('cabang_id', $id))
                ->when($request->integer('perumahan_id'), fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
                ->latest('tanggal')
                ->latest('id')
                ->limit(300)
                ->get()
                ->map(function (TransaksiKeuangan $row) use ($workflow) {
                    $approval = $row->latestApproval;

                    return [
                        'id' => $row->id,
                        'date' => optional($row->tanggal)->format('Y-m-d'),
                        'reference' => $row->nomor_referensi ?: '-',
                        'company' => $row->cabang?->nama_cabang ?? '-',
                        'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                        'bank' => trim(($row->masterBank?->nama_bank ?? '-').' '.($row->masterBank?->nomor_rekening ?? '')),
                        'post' => $row->tipePost?->nama_post ?? '-',
                        'type' => $row->tipePost?->jenis ?? '-',
                        'amount' => (float) $row->nominal,
                        'description' => $row->keterangan,
                        'input_by' => $row->user?->name ?? '-',
                        'record_status' => $row->record_status ?? 'locked',
                        'status' => $row->status,
                        'approval_status' => $approval?->status,
                        'approval_step' => $approval?->current_step,
                        'approval_total' => $approval?->total_steps,
                        'can_review' => $approval ? $workflow->canReview($approval) : false,
                        'can_lock' => $row->source_type === 'manual_finance'
                            && $row->record_status === 'draft'
                            && (int) $row->user_id === (int) auth()->id(),
                        'can_unlock' => $row->source_type === 'manual_finance'
                            && $row->record_status === 'locked'
                            && $row->status !== 'posted'
                            && (auth()->user()?->can('keuangan.update') || auth()->user()?->hasAnyRole(['owner', 'super_admin'])),
                        'lock_url' => route('admin.finance.transaction.lock', $row, absolute: false),
                        'unlock_url' => route('admin.finance.transaction.unlock', $row, absolute: false),
                        'approve_url' => $approval ? route('admin.approval.requests.approve', $approval, absolute: false) : null,
                        'reject_url' => $approval ? route('admin.approval.requests.reject', $approval, absolute: false) : null,
                    ];
                }),
        ];
    }

    protected function dashboardData(Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $cash = $this->cashFlowData($from, $to, $perumahanId);
        $receivables = $this->receivableData($perumahanId);
        $payables = $this->payableData($perumahanId);
        $profit = $this->profitLossData($from, $to, $perumahanId);
        $pendingTransactionIds = TransaksiKeuangan::query()
            ->where('source_type', 'manual_finance')
            ->where('status', 'pending_approval')
            ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
            ->pluck('id');

        $cashAccountId = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id');
        $monthly = JournalDetail::query()
            ->with('journal')
            ->where('chart_of_account_id', $cashAccountId)
            ->whereHas('journal', fn (Builder $query) => $query
                ->where('record_status', 'posted')
                ->whereBetween('tanggal', [$from, $to])
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)))
            ->get()
            ->groupBy(fn (JournalDetail $row) => $row->journal?->tanggal?->format('Y-m'))
            ->map(fn ($rows, string $month) => [
                'month' => $month,
                'in' => (float) $rows->sum('debit'),
                'out' => (float) $rows->sum('kredit'),
            ])->sortKeys()->values();
        $runningBalance = $this->cashFlowData($from->copy()->startOfMonth(), $from->copy()->subDay(), $perumahanId)['ending_balance'];
        $monthly = $monthly->map(function (array $row) use (&$runningBalance) {
            $runningBalance += $row['in'] - $row['out'];

            return [...$row, 'label' => Carbon::createFromFormat('Y-m', $row['month'])->translatedFormat('M Y'), 'balance' => $runningBalance];
        });

        return [
            'stats' => [
                'cash_balance' => $cash['ending_balance'],
                'cash_in' => $cash['cash_in'],
                'cash_out' => $cash['cash_out'],
                'receivable' => $receivables['summary']['remaining'],
                'payable' => $payables['summary']['remaining'],
                'profit' => $profit['net_profit'],
            ],
            'work_queue' => [
                [
                    'label' => 'Transaksi menunggu approval',
                    'count' => ApprovalRequest::query()
                        ->where('module_key', 'financial-transaction')
                        ->where('status', ApprovalRequest::STATUS_PENDING)
                        ->whereIn('model_id', $pendingTransactionIds)
                        ->count(),
                    'href' => route('admin.finance.show', 'pemasukan', absolute: false),
                ],
                [
                    'label' => 'Piutang telah jatuh tempo',
                    'count' => collect($receivables['rows'])->filter(fn (array $row) => $row['remaining'] > 0 && $row['due_date'] && Carbon::parse($row['due_date'])->isPast())->count(),
                    'href' => route('admin.receivables.due-monitor', absolute: false),
                ],
                [
                    'label' => 'Hutang belum dibayar',
                    'count' => collect($payables['rows'])->where('remaining', '>', 0)->count(),
                    'href' => route('admin.finance.show', 'hutang', absolute: false),
                ],
            ],
            'monthly' => $monthly,
            'recent_journals' => $this->journalQuery($from, $to, $perumahanId)
                ->with('perumahan:id,nama_perusahaan')
                ->latest('tanggal')->latest('id')->limit(8)->get()
                ->map(fn (Journal $row) => $this->journalRow($row)),
        ];
    }

    protected function accountData(): array
    {
        return [
            'rows' => ChartOfAccount::query()->orderBy('kode_akun')->get()->map(fn (ChartOfAccount $row) => [
                ...$row->only(['id', 'kode_akun', 'nama_akun', 'kategori', 'posisi_normal', 'status', 'is_system']),
            ]),
            'categories' => collect([
                'aset', 'aset_kontra', 'liabilitas', 'ekuitas', 'pendapatan',
                'pendapatan_lain', 'beban_hpp', 'beban_operasional', 'beban_lain',
            ])->map(fn (string $value) => ['value' => $value, 'label' => ucwords(str_replace('_', ' ', $value))]),
        ];
    }

    protected function journalData(Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $journalQuery = Journal::query()
            ->whereBetween('tanggal', [$from, $to])
            ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
            ->where(fn (Builder $query) => $query->where('record_status', 'posted')->orWhere(fn (Builder $manual) => $manual->where('type', 'manual')->where('created_by', auth()->id())))
            ->with(['perumahan:id,nama_perusahaan', 'details.account:id,kode_akun,nama_akun', 'latestApproval'])
            ->latest('tanggal')->latest('id');
        $paginator = $journalQuery->paginate(50, ['*'], 'journal_page')->withQueryString();
        $journals = $paginator->getCollection();

        return [
            'trend' => $journals->groupBy(fn (Journal $row) => $row->tanggal?->format('Y-m-d'))
                ->map(fn ($rows, string $date) => [
                    'label' => Carbon::parse($date)->format('d M'),
                    'date' => $date,
                    'debit' => (float) $rows->sum(fn (Journal $row) => $row->details->sum('debit')),
                    'credit' => (float) $rows->sum(fn (Journal $row) => $row->details->sum('kredit')),
                ])->sortBy('date')->values()->take(-31)->values(),
            'rows' => $journals
                ->map(fn (Journal $row) => [
                    ...$this->journalRow($row),
                    'lines' => $row->details->map(fn (JournalDetail $line) => [
                        'account' => $line->account?->kode_akun.' - '.$line->account?->nama_akun,
                        'debit' => (float) $line->debit,
                        'kredit' => (float) $line->kredit,
                        'keterangan' => $line->keterangan,
                    ]),
                    'record_status' => $row->record_status,
                    'approval_status' => $row->latestApproval?->status,
                    'approval_step' => $row->latestApproval?->current_step,
                    'approval_total' => $row->latestApproval?->total_steps,
                    'can_lock' => $row->type === 'manual' && $row->record_status === 'draft' && (int) $row->created_by === (int) auth()->id(),
                    'can_unlock' => $row->type === 'manual' && $row->record_status === 'locked' && ! $row->posted_at && (auth()->user()?->can('keuangan.update') || auth()->user()?->hasAnyRole(['owner', 'super_admin'])),
                    'lock_url' => route('admin.finance.journal.lock', $row, absolute: false),
                    'unlock_url' => route('admin.finance.journal.unlock', $row, absolute: false),
                ]),
            'pagination' => [
                'links' => $paginator->linkCollection()->toArray(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    protected function ledgerData(Request $request, Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $accountId = $request->integer('account_id') ?: ChartOfAccount::query()->orderBy('kode_akun')->value('id');
        $account = ChartOfAccount::query()->find($accountId);
        $opening = $account ? $this->accountMovement($account, null, $from->copy()->subDay(), $perumahanId) : ['balance' => 0];
        $running = (float) ($opening['balance'] ?? 0);

        $rows = JournalDetail::query()
            ->with(['journal.perumahan:id,nama_perusahaan'])
            ->where('chart_of_account_id', $accountId)
            ->whereHas('journal', fn (Builder $query) => $query
                ->where('record_status', 'posted')
                ->whereBetween('tanggal', [$from, $to])
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)))
            ->get()
            ->sortBy(fn (JournalDetail $line) => ($line->journal?->tanggal?->format('Y-m-d') ?? '').'-'.str_pad((string) $line->journal_id, 10, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (JournalDetail $line) use (&$running, $account) {
                $movement = $account?->posisi_normal === 'kredit'
                    ? (float) $line->kredit - (float) $line->debit
                    : (float) $line->debit - (float) $line->kredit;
                $running += $movement;

                return [
                    'id' => $line->id,
                    'date' => optional($line->journal?->tanggal)->format('Y-m-d'),
                    'journal' => $line->journal?->nomor_jurnal,
                    'description' => $line->keterangan ?: $line->journal?->keterangan,
                    'perumahan' => $line->journal?->perumahan?->nama_perusahaan ?? '-',
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->kredit,
                    'balance' => $running,
                ];
            });

        return [
            'account' => $account?->only(['id', 'kode_akun', 'nama_akun', 'posisi_normal']),
            'opening_balance' => (float) ($opening['balance'] ?? 0),
            'ending_balance' => $running,
            'rows' => $rows,
        ];
    }

    protected function trialBalanceData(Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $rows = ChartOfAccount::query()->orderBy('kode_akun')->get()->map(function (ChartOfAccount $account) use ($from, $to, $perumahanId) {
            $opening = $this->accountMovement($account, null, $from->copy()->subDay(), $perumahanId);
            $period = $this->accountMovement($account, $from, $to, $perumahanId);
            $ending = (float) $opening['balance'] + ($account->posisi_normal === 'kredit'
                ? (float) $period['credit'] - (float) $period['debit']
                : (float) $period['debit'] - (float) $period['credit']);

            return [
                'id' => $account->id,
                'code' => $account->kode_akun,
                'name' => $account->nama_akun,
                'opening' => (float) $opening['balance'],
                'debit' => (float) $period['debit'],
                'credit' => (float) $period['credit'],
                'ending' => $ending,
                'ending_debit' => $account->posisi_normal === 'debit' ? max(0, $ending) : max(0, -$ending),
                'ending_credit' => $account->posisi_normal === 'kredit' ? max(0, $ending) : max(0, -$ending),
            ];
        })->filter(fn (array $row) => collect($row)->only(['opening', 'debit', 'credit', 'ending'])->some(fn ($value) => abs((float) $value) > 0.001))->values();

        return [
            'rows' => $rows,
            'total_debit' => $rows->sum('ending_debit'),
            'total_credit' => $rows->sum('ending_credit'),
            'balanced' => round($rows->sum('ending_debit'), 2) === round($rows->sum('ending_credit'), 2),
        ];
    }

    protected function profitLossData(Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $accounts = ChartOfAccount::query()
            ->whereIn('kategori', ['pendapatan', 'pendapatan_lain', 'beban_hpp', 'beban_operasional', 'beban_lain'])
            ->orderBy('kode_akun')->get();
        $rows = $accounts->map(function (ChartOfAccount $account) use ($from, $to, $perumahanId) {
            $movement = $this->accountMovement($account, $from, $to, $perumahanId);

            return [
                'code' => $account->kode_akun,
                'name' => $account->nama_akun,
                'category' => $account->kategori,
                'amount' => (float) $movement['balance'],
            ];
        })->filter(fn (array $row) => abs($row['amount']) > 0.001)->values();
        $revenue = $rows->whereIn('category', ['pendapatan', 'pendapatan_lain'])->sum('amount');
        $cost = $rows->where('category', 'beban_hpp')->sum('amount');
        $operating = $rows->whereIn('category', ['beban_operasional', 'beban_lain'])->sum('amount');
        $trend = JournalDetail::query()
            ->with(['journal', 'account:id,kategori'])
            ->whereIn('chart_of_account_id', $accounts->pluck('id'))
            ->whereHas('journal', fn (Builder $query) => $query
                ->where('record_status', 'posted')
                ->whereBetween('tanggal', [$from, $to])
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)))
            ->get()
            ->groupBy(fn (JournalDetail $row) => $row->journal?->tanggal?->format('Y-m'))
            ->map(function ($lines, string $month) {
                $revenue = (float) $lines->filter(fn (JournalDetail $line) => in_array($line->account?->kategori, ['pendapatan', 'pendapatan_lain'], true))
                    ->sum(fn (JournalDetail $line) => $line->kredit - $line->debit);
                $expense = (float) $lines->filter(fn (JournalDetail $line) => in_array($line->account?->kategori, ['beban_hpp', 'beban_operasional', 'beban_lain'], true))
                    ->sum(fn (JournalDetail $line) => $line->debit - $line->kredit);

                return [
                    'month' => $month,
                    'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('M Y'),
                    'revenue' => $revenue,
                    'expense' => $expense,
                    'profit' => $revenue - $expense,
                ];
            })->sortBy('month')->values();

        return [
            'rows' => $rows,
            'trend' => $trend,
            'revenue' => (float) $revenue,
            'cost_of_sales' => (float) $cost,
            'gross_profit' => (float) ($revenue - $cost),
            'operating_expense' => (float) $operating,
            'net_profit' => (float) ($revenue - $cost - $operating),
        ];
    }

    protected function balanceSheetData(Carbon $to, ?int $perumahanId): array
    {
        $rows = ChartOfAccount::query()
            ->whereIn('kategori', ['aset', 'aset_kontra', 'liabilitas', 'ekuitas'])
            ->orderBy('kode_akun')->get()
            ->map(function (ChartOfAccount $account) use ($to, $perumahanId) {
                $movement = $this->accountMovement($account, null, $to, $perumahanId);

                return [
                    'code' => $account->kode_akun,
                    'name' => $account->nama_akun,
                    'category' => $account->kategori,
                    'amount' => (float) $movement['balance'],
                ];
            })->filter(fn (array $row) => abs($row['amount']) > 0.001)->values();
        $currentProfit = $this->profitLossData(Carbon::create(2000, 1, 1), $to, $perumahanId)['net_profit'];
        $assets = $rows->where('category', 'aset')->sum('amount') - $rows->where('category', 'aset_kontra')->sum('amount');
        $liabilities = $rows->where('category', 'liabilitas')->sum('amount');
        $equity = $rows->where('category', 'ekuitas')->sum('amount') + $currentProfit;

        return [
            'rows' => $rows,
            'current_profit' => $currentProfit,
            'assets' => (float) $assets,
            'liabilities' => (float) $liabilities,
            'equity' => (float) $equity,
            'liabilities_equity' => (float) ($liabilities + $equity),
            'balanced' => round($assets, 2) === round($liabilities + $equity, 2),
        ];
    }

    protected function cashFlowData(Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $cashAccountId = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id');
        $query = JournalDetail::query()
            ->with(['journal.perumahan:id,nama_perusahaan', 'journal.source'])
            ->where('chart_of_account_id', $cashAccountId)
            ->whereHas('journal', fn (Builder $query) => $query
                ->where('record_status', 'posted')
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)));
        $openingRows = (clone $query)->whereHas('journal', fn (Builder $query) => $query->where('tanggal', '<', $from))->get();
        $periodRows = (clone $query)
            ->whereHas('journal', fn (Builder $query) => $query->whereBetween('tanggal', [$from, $to]))
            ->get()
            ->sortBy(fn (JournalDetail $row) => ($row->journal?->tanggal?->format('Y-m-d') ?? '').'-'.$row->journal_id)
            ->values();
        $opening = (float) $openingRows->sum(fn (JournalDetail $row) => $row->debit - $row->kredit);
        $cashIn = (float) $periodRows->sum('debit');
        $cashOut = (float) $periodRows->sum('kredit');
        $runningBalance = $opening;
        $annual = $from->isSameDay($from->copy()->startOfYear()) && $to->isSameDay($to->copy()->endOfYear());
        $keyFormat = $annual ? 'Y-m' : 'Y-m-d';
        $grouped = $periodRows->groupBy(fn (JournalDetail $row) => $row->journal?->tanggal?->format($keyFormat));
        $cursor = $annual ? $from->copy()->startOfMonth() : $from->copy()->startOfDay();
        $last = $annual ? $to->copy()->startOfMonth() : $to->copy()->startOfDay();
        $trend = collect();
        while ($cursor->lte($last)) {
            $key = $cursor->format($keyFormat);
            $rows = $grouped->get($key, collect());
            $cashInForBucket = (float) $rows->sum('debit');
            $cashOutForBucket = (float) $rows->sum('kredit');
            $runningBalance += $cashInForBucket - $cashOutForBucket;
            $trend->push([
                'date' => $key,
                'label' => $annual ? $cursor->locale('id')->translatedFormat('M') : $cursor->format('d M'),
                'in' => $cashInForBucket,
                'out' => $cashOutForBucket,
                'balance' => $runningBalance,
            ]);
            $cursor = $annual ? $cursor->addMonth() : $cursor->addDay();
        }

        return [
            'opening_balance' => $opening,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash_flow' => $cashIn - $cashOut,
            'ending_balance' => $opening + $cashIn - $cashOut,
            'trend' => $trend,
            'groups' => $periodRows->groupBy(fn (JournalDetail $row) => $this->cashFlowActivity($row->journal))
                ->map(fn ($rows, string $activity) => [
                    'name' => match ($activity) { 'operating' => 'Aktivitas Operasi', 'investing' => 'Aktivitas Investasi', 'financing' => 'Aktivitas Pendanaan' },
                    'type' => $activity,
                    'cash_in' => (float) $rows->sum('debit'),
                    'cash_out' => (float) $rows->sum('kredit'),
                    'amount' => (float) ($rows->sum('debit') - $rows->sum('kredit')),
                ])->values(),
            'rows' => $periodRows->map(fn (JournalDetail $row) => [
                'id' => $row->id,
                'date' => optional($row->journal?->tanggal)->format('Y-m-d'),
                'type' => $row->debit > 0 ? 'pemasukan' : 'pengeluaran',
                'post' => $row->journal?->type,
                'activity' => $this->cashFlowActivity($row->journal),
                'bank' => $row->journal?->perumahan?->nama_perusahaan ?? 'Konsolidasi',
                'description' => $row->keterangan ?: $row->journal?->keterangan,
                'amount' => (float) ($row->debit > 0 ? $row->debit : $row->kredit),
            ]),
        ];
    }

    protected function cashFlowActivity(?Journal $journal): string
    {
        $type = strtolower((string) $journal?->type);
        if (in_array($type, ['asset_purchase', 'fixed_asset_purchase', 'land_purchase', 'heavy_equipment_purchase', 'asset_sale'], true)) {
            return 'investing';
        }
        if ($journal?->source instanceof TransaksiKeuangan) {
            $name = strtolower((string) $journal->source->tipePost?->nama_post);
            if (str_contains($name, 'modal') || str_contains($name, 'investor') || str_contains($name, 'pinjaman')) {
                return 'financing';
            }
        }
        if (in_array($type, ['capital_deposit', 'loan_receipt', 'loan_payment', 'dividend_payment'], true)) {
            return 'financing';
        }
        return 'operating';
    }

    protected function receivableData(?int $perumahanId): array
    {
        $rows = PaymentSchedule::query()
            ->with(['salesTransaction.customer:id,nama', 'salesTransaction.housingProject:id,nama_perusahaan'])
            ->where('record_status', 'locked')->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('perumahan_id', $id)))
            ->orderBy('due_date')->get()
            ->map(fn (PaymentSchedule $row) => [
                'id' => $row->id,
                'reference' => $row->salesTransaction?->transaction_no,
                'customer' => $row->salesTransaction?->customer?->nama,
                'perumahan' => $row->salesTransaction?->housingProject?->nama_perusahaan ?? '-',
                'type' => $row->description,
                'due_date' => optional($row->due_date)->format('Y-m-d'),
                'bill' => (float) $row->amount,
                'paid' => (float) $row->paid_amount,
                'remaining' => max(0, (float) $row->amount - (float) $row->paid_amount),
                'status' => $row->status,
            ]);

        return [
            'rows' => $rows,
            'aging' => $this->agingBuckets($rows),
            'summary' => [
                'bill' => $rows->sum('bill'),
                'paid' => $rows->sum('paid'),
                'remaining' => $rows->sum('remaining'),
                'overdue' => $rows->filter(fn ($row) => $row['remaining'] > 0 && $row['due_date'] && Carbon::parse($row['due_date'])->isPast())->sum('remaining'),
            ],
        ];
    }

    protected function payableData(?int $perumahanId): array
    {
        $supplier = MaterialPurchase::query()
            ->with(['perumahan:id,nama_perusahaan', 'supplierInvoice'])
            ->where('metode_pembayaran', 'hutang')
            ->whereHas('supplierInvoice', fn (Builder $query) => $query->where('status', 'reconciled'))
            ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
            ->get()->map(fn (MaterialPurchase $row) => [
                'id' => 'supplier-'.$row->id,
                'source' => 'Supplier',
                'reference' => $row->kode_pembelian,
                'vendor' => $row->supplier ?: '-',
                'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                'due_date' => optional($row->supplierInvoice?->invoice_date)->format('Y-m-d'),
                'bill' => (float) $row->supplierInvoice?->payable_amount,
                'paid' => (float) $row->supplierInvoice?->paid_amount,
                'remaining' => (float) $row->supplierInvoice?->outstanding_amount,
                'status' => $row->supplierInvoice?->status,
            ]);
        $contractor = SpkKontraktorPayment::query()
            ->with(['spkKontraktor.kontraktor:id,nama_kontraktor', 'spkKontraktor.perumahan:id,nama_perusahaan'])
            ->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('spkKontraktor', fn (Builder $query) => $query->where('perumahan_id', $id)))
            ->get()->map(fn (SpkKontraktorPayment $row) => [
                'id' => 'contractor-'.$row->id,
                'source' => 'Kontraktor',
                'reference' => ($row->spkKontraktor?->nomor_spk ?? '-').' / Termin '.$row->termin_ke,
                'vendor' => $row->spkKontraktor?->kontraktor?->nama_kontraktor ?? '-',
                'perumahan' => $row->spkKontraktor?->perumahan?->nama_perusahaan ?? '-',
                'due_date' => optional($row->tanggal_jatuh_tempo ?: $row->tanggal_pembayaran)->format('Y-m-d'),
                'bill' => (float) $row->nominal,
                'paid' => $row->paid_at ? (float) $row->nominal : 0,
                'remaining' => $row->paid_at ? 0 : (float) $row->nominal,
                'status' => $row->status,
            ]);
        $rows = $supplier->concat($contractor)->values();

        return [
            'rows' => $rows,
            'aging' => $this->agingBuckets($rows),
            'summary' => [
                'bill' => $rows->sum('bill'),
                'paid' => $rows->sum('paid'),
                'remaining' => $rows->sum('remaining'),
            ],
        ];
    }

    protected function agingBuckets($rows): array
    {
        $today = now()->startOfDay();
        $buckets = collect([
            ['key' => 'current', 'label' => 'Belum jatuh tempo', 'value' => 0],
            ['key' => '1-30', 'label' => 'Lewat 1–30 hari', 'value' => 0],
            ['key' => '31-60', 'label' => 'Lewat 31–60 hari', 'value' => 0],
            ['key' => '61-90', 'label' => 'Lewat 61–90 hari', 'value' => 0],
            ['key' => '90+', 'label' => 'Lewat >90 hari', 'value' => 0],
        ])->keyBy('key');

        foreach ($rows as $row) {
            if (($row['remaining'] ?? 0) <= 0) {
                continue;
            }
            $due = filled($row['due_date'] ?? null) ? Carbon::parse($row['due_date'])->startOfDay() : $today;
            $days = $due->lt($today) ? $due->diffInDays($today) : 0;
            $key = $days === 0 ? 'current' : ($days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+')));
            $bucket = $buckets->get($key);
            $bucket['value'] += (float) $row['remaining'];
            $buckets->put($key, $bucket);
        }

        return $buckets->values()->all();
    }

    protected function accountMovement(ChartOfAccount $account, ?Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $query = JournalDetail::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journal', fn (Builder $query) => $query
                ->where('record_status', 'posted')
                ->when($from, fn (Builder $query) => $query->whereDate('tanggal', '>=', $from))
                ->whereDate('tanggal', '<=', $to)
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)));
        $debit = (float) (clone $query)->sum('debit');
        $credit = (float) (clone $query)->sum('kredit');

        return [
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $account->posisi_normal === 'kredit' ? $credit - $debit : $debit - $credit,
        ];
    }

    protected function journalQuery(Carbon $from, Carbon $to, ?int $perumahanId): Builder
    {
        return Journal::query()
            ->where('record_status', 'posted')
            ->whereBetween('tanggal', [$from, $to])
            ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id));
    }

    protected function journalRow(Journal $row): array
    {
        return [
            'id' => $row->id,
            'number' => $row->nomor_jurnal,
            'date' => optional($row->tanggal)->format('Y-m-d'),
            'type' => $row->type,
            'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
            'company' => $row->company?->nama_cabang ?? $row->perumahan?->cabang?->nama_cabang ?? '-',
            'description' => $row->keterangan,
            'debit' => (float) $row->total_debit,
            'credit' => (float) $row->total_kredit,
        ];
    }

    public function export(Request $request, string $section, string $format)
    {
        abort_unless(in_array($section, ['buku-besar', 'neraca-saldo', 'laba-rugi', 'neraca', 'arus-kas', 'aging-piutang', 'hutang'], true), 404);
        abort_unless(in_array($format, ['pdf', 'excel'], true), 404);
        $this->authorizeFinanceView($request, $section);
        abort_unless($request->user()?->can('laporan.export') || $request->user()?->hasAnyRole(['owner', 'super_admin']), 403);

        [$from, $to] = $this->period($request);
        $perumahanId = $this->perumahanId($request);
        $data = $this->data($request, $section, $from, $to, $perumahanId);
        $report = $this->exportDataset($section, $data);
        $payload = [
            ...$report,
            'title' => $this->sections[$section],
            'period' => $from->format('d/m/Y').' - '.$to->format('d/m/Y'),
            'scope' => $perumahanId
                ? Perumahan::query()->find($perumahanId)?->nama_perusahaan
                : 'Konsolidasi Semua Perumahan',
            'printedAt' => now()->format('d/m/Y H:i'),
        ];

        if ($format === 'pdf') {
            return Pdf::loadView('reports.finance', $payload)
                ->setPaper('a4', 'landscape')
                ->download('laporan-'.$section.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
        }

        return response(view('reports.finance', $payload)->render())
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="laporan-'.$section.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xls"');
    }

    protected function exportDataset(string $section, array $data): array
    {
        return match ($section) {
            'buku-besar' => [
                'columns' => ['date' => 'Tanggal', 'reference' => 'Referensi', 'description' => 'Keterangan', 'debit' => 'Debit', 'credit' => 'Kredit', 'balance' => 'Saldo'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Saldo Awal' => $data['opening_balance'] ?? 0, 'Saldo Akhir' => $data['ending_balance'] ?? 0],
            ],
            'neraca-saldo' => [
                'columns' => ['code' => 'Kode Akun', 'name' => 'Nama Akun', 'opening' => 'Saldo Awal', 'debit' => 'Debit', 'credit' => 'Kredit', 'ending_debit' => 'Saldo Debit', 'ending_credit' => 'Saldo Kredit'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Total Debit' => $data['total_debit'] ?? 0, 'Total Kredit' => $data['total_credit'] ?? 0, 'Status' => ($data['balanced'] ?? false) ? 'Balance' : 'Tidak Balance'],
            ],
            'laba-rugi' => [
                'columns' => ['code' => 'Kode Akun', 'name' => 'Nama Akun', 'category' => 'Kelompok', 'amount' => 'Nilai'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Pendapatan' => $data['revenue'] ?? 0, 'HPP' => $data['cost_of_sales'] ?? 0, 'Laba Kotor' => $data['gross_profit'] ?? 0, 'Beban Operasional' => $data['operating_expense'] ?? 0, 'Laba Bersih' => $data['net_profit'] ?? 0],
            ],
            'neraca' => [
                'columns' => ['code' => 'Kode Akun', 'name' => 'Nama Akun', 'category' => 'Kelompok', 'amount' => 'Nilai'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Aset' => $data['assets'] ?? 0, 'Liabilitas' => $data['liabilities'] ?? 0, 'Ekuitas' => $data['equity'] ?? 0, 'Liabilitas + Ekuitas' => $data['liabilities_equity'] ?? 0, 'Status' => ($data['balanced'] ?? false) ? 'Balance' : 'Tidak Balance'],
            ],
            'arus-kas' => [
                'columns' => ['date' => 'Tanggal', 'type' => 'Jenis', 'post' => 'Sumber', 'bank' => 'Perumahan', 'description' => 'Keterangan', 'amount' => 'Nilai'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Saldo Awal' => $data['opening_balance'] ?? 0, 'Kas Masuk' => $data['cash_in'] ?? 0, 'Kas Keluar' => $data['cash_out'] ?? 0, 'Arus Bersih' => $data['net_cash_flow'] ?? 0, 'Saldo Akhir' => $data['ending_balance'] ?? 0],
            ],
            'aging-piutang' => [
                'columns' => ['reference' => 'Transaksi', 'customer' => 'Customer', 'perumahan' => 'Perumahan', 'type' => 'Tagihan', 'due_date' => 'Jatuh Tempo', 'bill' => 'Tagihan', 'paid' => 'Dibayar', 'remaining' => 'Sisa', 'status' => 'Status'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Total Tagihan' => $data['summary']['bill'] ?? 0, 'Dibayar' => $data['summary']['paid'] ?? 0, 'Sisa' => $data['summary']['remaining'] ?? 0, 'Jatuh Tempo' => $data['summary']['overdue'] ?? 0],
            ],
            'hutang' => [
                'columns' => ['source' => 'Sumber', 'reference' => 'Referensi', 'vendor' => 'Supplier/Kontraktor', 'perumahan' => 'Perumahan', 'due_date' => 'Jatuh Tempo', 'bill' => 'Tagihan', 'paid' => 'Dibayar', 'remaining' => 'Sisa', 'status' => 'Status'],
                'rows' => $data['rows'] ?? [],
                'summary' => ['Total Tagihan' => $data['summary']['bill'] ?? 0, 'Dibayar' => $data['summary']['paid'] ?? 0, 'Sisa' => $data['summary']['remaining'] ?? 0],
            ],
        };
    }

    protected function period(Request $request): array
    {
        $from = Carbon::parse($request->query('date_from') ?: now()->startOfYear()->toDateString())->startOfDay();
        $to = Carbon::parse($request->query('date_to') ?: now()->toDateString())->endOfDay();
        abort_if($from->gt($to), 422, 'Tanggal awal tidak boleh melewati tanggal akhir.');

        return [$from, $to];
    }

    protected function perumahanId(Request $request): ?int
    {
        if ($request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            return $request->integer('perumahan_id') ?: null;
        }

        $assigned = $request->user()?->perumahans()->pluck('perumahans.id')->map(fn ($id) => (int) $id)->all() ?? [];
        if (empty($assigned)) {
            return $request->integer('perumahan_id') ?: null;
        }

        $requested = $request->integer('perumahan_id') ?: (int) $request->session()->get('active_perumahan_id');

        return in_array($requested, $assigned, true) ? $requested : $assigned[0];
    }

    protected function perumahanOptions(Request $request): array
    {
        $query = Perumahan::query()->orderBy('nama_perusahaan');
        if (! $request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            $query->whereIn('id', $request->user()?->perumahans()->pluck('perumahans.id') ?? []);
        }
        $rows = $query->get(['id', 'nama_perusahaan'])->map(fn (Perumahan $row) => [
            'value' => (string) $row->id,
            'label' => $row->nama_perusahaan,
        ]);

        return $request->user()?->hasAnyRole(['owner', 'super_admin'])
            ? $rows->prepend(['value' => '', 'label' => 'Konsolidasi Semua Perumahan'])->all()
            : $rows->all();
    }

    protected function bankOptions(Request $request): array
    {
        $query = MasterBank::query()
            ->with(['cabang:id,nama_cabang', 'perumahan:id,nama_perusahaan'])
            ->finalized()
            ->where('status', 'aktif')
            ->orderBy('nama_bank');

        if (! $request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            $query->whereIn('cabang_id', $this->allowedCabangIds($request));
        }

        return $query->get(['id', 'cabang_id', 'perumahan_id', 'nama_bank', 'nomor_rekening', 'nama_rekening'])
            ->map(fn (MasterBank $row) => [
                'value' => (string) $row->id,
                'cabang_id' => (string) $row->cabang_id,
                'perumahan_id' => (string) $row->perumahan_id,
                'label' => trim(($row->cabang?->nama_cabang ?? '-').' - '.$row->nama_bank.' - '.$row->nomor_rekening),
            ])->all();
    }

    protected function branchOptions(Request $request): array
    {
        return CabangPerusahaan::query()
            ->finalized()
            ->when(! $request->user()?->hasAnyRole(['owner', 'super_admin']), fn (Builder $query) => $query->whereIn('id', $this->allowedCabangIds($request)))
            ->orderBy('nama_cabang')
            ->get(['id', 'nama_cabang'])
            ->map(fn (CabangPerusahaan $row) => ['value' => (string) $row->id, 'label' => $row->nama_cabang])
            ->values()
            ->all();
    }

    protected function cabangId(Request $request): ?int
    {
        $requested = $request->integer('cabang_id');
        $allowed = $this->allowedCabangIds($request);

        if ($request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            return $requested ?: null;
        }

        return $requested && in_array($requested, $allowed, true) ? $requested : ($allowed[0] ?? null);
    }

    protected function allowedCabangIds(Request $request): array
    {
        return collect([$request->user()?->kantor_cabang_id])
            ->merge($request->user()?->perumahans()->pluck('perumahans.cabang_id') ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function ensureCabangAllowed(Request $request, mixed $cabangId): void
    {
        if ($request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            return;
        }

        abort_unless(in_array((int) $cabangId, $this->allowedCabangIds($request), true), 403);
    }

    protected function ensurePerumahanAllowed(Request $request, mixed $perumahanId): void
    {
        if (! $perumahanId || $request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            return;
        }

        abort_unless($request->user()?->perumahans()->whereKey($perumahanId)->exists(), 403);
    }

    protected function accountRules(?int $ignoreId = null): array
    {
        return [
            'kode_akun' => ['required', 'string', 'max:30', Rule::unique('chart_of_accounts', 'kode_akun')->ignore($ignoreId)],
            'nama_akun' => ['required', 'string', 'max:255'],
            'kategori' => ['required', Rule::in(['aset', 'aset_kontra', 'liabilitas', 'ekuitas', 'pendapatan', 'pendapatan_lain', 'beban_hpp', 'beban_operasional', 'beban_lain'])],
            'posisi_normal' => ['required', Rule::in(['debit', 'kredit'])],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    protected function authorizeFinanceView(Request $request, ?string $section = null): void
    {
        $permission = $section ? ($this->sectionPermissions[$section] ?? 'keuangan.view') : 'keuangan.view';

        abort_unless(
            $request->user()?->can($permission)
            || $request->user()?->hasAnyRole(['super_admin', 'owner']),
            403,
        );
    }

    protected function authorizeFinanceWrite(Request $request): void
    {
        abort_unless(
            $request->user()?->can('keuangan.create')
            || $request->user()?->can('keuangan.update')
            || $request->user()?->can('keuangan.delete')
            || $request->user()?->hasAnyRole(['super_admin', 'owner']),
            403,
        );
    }
}
