<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\MaterialPurchase;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\SpkKontraktorPayment;
use App\Models\SprBillingSchedule;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
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
        'piutang' => 'Piutang Customer',
        'hutang' => 'Hutang Supplier & Kontraktor',
    ];

    public function show(Request $request, string $section): Response
    {
        $this->authorizeFinanceView($request);
        abort_unless(array_key_exists($section, $this->sections), 404);

        [$from, $to] = $this->period($request);
        $perumahanId = $this->perumahanId($request);

        return Inertia::render('Admin/Finance/Index', [
            'title' => $this->sections[$section],
            'section' => $section,
            'baseUrl' => route('admin.finance.show', $section, absolute: false),
            'permissions' => [
                'canCreate' => (bool) $request->user()?->can('keuangan.create') || $request->user()?->can('keuangan.manage'),
                'canUpdate' => (bool) $request->user()?->can('keuangan.update') || $request->user()?->can('keuangan.manage'),
                'canDelete' => (bool) $request->user()?->can('keuangan.delete') || $request->user()?->can('keuangan.manage'),
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'perumahan_id' => $perumahanId ? (string) $perumahanId : '',
                'account_id' => (string) $request->query('account_id', ''),
            ],
            'options' => [
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
                'nomor_jurnal' => 'JRN-MANUAL-'.now()->format('YmdHis').'-'.str_pad((string) (Journal::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
                'tanggal' => $validated['tanggal'],
                'type' => 'manual',
                'perumahan_id' => $validated['perumahan_id'] ?: null,
                'total_debit' => $debit,
                'total_kredit' => $credit,
                'keterangan' => $validated['keterangan'],
                'created_by' => $request->user()?->id,
            ]);

            $journal->details()->createMany($lines->all());
        });

        return back()->with('success', 'Jurnal umum berhasil diposting.');
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
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tipe_post_id' => ['required', 'exists:tipe_posts,id'],
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'nomor_referensi' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['required', 'string'],
        ]);
        $this->ensurePerumahanAllowed($request, $validated['perumahan_id']);

        $bank = MasterBank::query()
            ->whereKey($validated['master_bank_id'])
            ->where('perumahan_id', $validated['perumahan_id'])
            ->where('status', 'aktif')
            ->firstOrFail();
        $post = TipePost::query()
            ->whereKey($validated['tipe_post_id'])
            ->when($expectedType, fn (Builder $query, string $type) => $query->where('jenis', $type))
            ->where('status', 'aktif')
            ->whereNotNull('debit_account_id')
            ->whereNotNull('credit_account_id')
            ->firstOrFail();
        $property = Perumahan::query()->findOrFail($validated['perumahan_id']);

        DB::transaction(function () use ($request, $validated, $bank, $post, $property, $accounting): void {
            $transaction = TransaksiKeuangan::query()->create([
                'cabang_id' => $property->cabang_id,
                'perumahan_id' => $property->id,
                'master_bank_id' => $bank->id,
                'tipe_post_id' => $post->id,
                'tanggal' => $validated['tanggal'],
                'nominal' => $validated['nominal'],
                'nomor_referensi' => $validated['nomor_referensi'] ?: null,
                'status' => 'posted',
                'keterangan' => $validated['keterangan'],
                'user_id' => $request->user()?->id,
            ]);

            $accounting->recordFinancialTransaction($transaction);
        });

        return back()->with('success', 'Transaksi '.$post->jenis.' berhasil diposting ke kas/bank dan jurnal.');
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
            'pemasukan' => $this->manualTransactionData($from, $to, $perumahanId, 'pemasukan'),
            'pengeluaran' => $this->manualTransactionData($from, $to, $perumahanId, 'pengeluaran'),
            'daftar-akun' => $this->accountData(),
            'jurnal-umum' => $this->journalData($from, $to, $perumahanId),
            'buku-besar' => $this->ledgerData($request, $from, $to, $perumahanId),
            'neraca-saldo' => $this->trialBalanceData($from, $to, $perumahanId),
            'laba-rugi' => $this->profitLossData($from, $to, $perumahanId),
            'neraca' => $this->balanceSheetData($to, $perumahanId),
            'arus-kas' => $this->cashFlowData($from, $to, $perumahanId),
            'piutang' => $this->receivableData($perumahanId),
            'hutang' => $this->payableData($perumahanId),
        };
    }

    protected function manualTransactionData(Carbon $from, Carbon $to, ?int $perumahanId, ?string $type = null): array
    {
        return [
            'rows' => TransaksiKeuangan::query()
                ->with([
                    'perumahan:id,nama_perusahaan',
                    'masterBank:id,nama_bank,nomor_rekening',
                    'tipePost:id,nama_post,jenis',
                    'user:id,name',
                ])
                ->whereBetween('tanggal', [$from, $to])
                ->when($type, fn (Builder $query, string $type) => $query->whereHas('tipePost', fn (Builder $postQuery) => $postQuery->where('jenis', $type)))
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
                ->latest('tanggal')
                ->latest('id')
                ->limit(300)
                ->get()
                ->map(fn (TransaksiKeuangan $row) => [
                    'id' => $row->id,
                    'date' => optional($row->tanggal)->format('Y-m-d'),
                    'reference' => $row->nomor_referensi ?: '-',
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'bank' => trim(($row->masterBank?->nama_bank ?? '-').' '.($row->masterBank?->nomor_rekening ?? '')),
                    'post' => $row->tipePost?->nama_post ?? '-',
                    'type' => $row->tipePost?->jenis ?? '-',
                    'amount' => (float) $row->nominal,
                    'description' => $row->keterangan,
                    'input_by' => $row->user?->name ?? '-',
                ]),
        ];
    }

    protected function dashboardData(Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $cash = $this->cashFlowData($from, $to, $perumahanId);
        $receivables = $this->receivableData($perumahanId);
        $payables = $this->payableData($perumahanId);
        $profit = $this->profitLossData($from, $to, $perumahanId);

        $cashAccountId = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id');
        $monthly = JournalDetail::query()
            ->with('journal')
            ->where('chart_of_account_id', $cashAccountId)
            ->whereHas('journal', fn (Builder $query) => $query
                ->whereBetween('tanggal', [$from, $to])
                ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)))
            ->get()
            ->groupBy(fn (JournalDetail $row) => $row->journal?->tanggal?->format('Y-m'))
            ->map(fn ($rows, string $month) => [
                'month' => $month,
                'in' => (float) $rows->sum('debit'),
                'out' => (float) $rows->sum('kredit'),
            ])->sortKeys()->values();

        return [
            'stats' => [
                'cash_balance' => $cash['ending_balance'],
                'cash_in' => $cash['cash_in'],
                'cash_out' => $cash['cash_out'],
                'receivable' => $receivables['summary']['remaining'],
                'payable' => $payables['summary']['remaining'],
                'profit' => $profit['net_profit'],
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
        return [
            'rows' => $this->journalQuery($from, $to, $perumahanId)
                ->with(['perumahan:id,nama_perusahaan', 'details.account:id,kode_akun,nama_akun'])
                ->latest('tanggal')->latest('id')->limit(300)->get()
                ->map(fn (Journal $row) => [
                    ...$this->journalRow($row),
                    'lines' => $row->details->map(fn (JournalDetail $line) => [
                        'account' => $line->account?->kode_akun.' - '.$line->account?->nama_akun,
                        'debit' => (float) $line->debit,
                        'kredit' => (float) $line->kredit,
                        'keterangan' => $line->keterangan,
                    ]),
                ]),
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

        return [
            'rows' => $rows,
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
            ->with(['journal.perumahan:id,nama_perusahaan'])
            ->where('chart_of_account_id', $cashAccountId)
            ->whereHas('journal', fn (Builder $query) => $query
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

        return [
            'opening_balance' => $opening,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash_flow' => $cashIn - $cashOut,
            'ending_balance' => $opening + $cashIn - $cashOut,
            'groups' => $periodRows->groupBy(fn (JournalDetail $row) => $row->journal?->type ?? 'Lainnya')
                ->map(fn ($rows, string $name) => [
                    'name' => $name,
                    'type' => $rows->sum('debit') >= $rows->sum('kredit') ? 'pemasukan' : 'pengeluaran',
                    'amount' => (float) abs($rows->sum('debit') - $rows->sum('kredit')),
                ])->values(),
            'rows' => $periodRows->map(fn (JournalDetail $row) => [
                'id' => $row->id,
                'date' => optional($row->journal?->tanggal)->format('Y-m-d'),
                'type' => $row->debit > 0 ? 'pemasukan' : 'pengeluaran',
                'post' => $row->journal?->type,
                'bank' => $row->journal?->perumahan?->nama_perusahaan ?? 'Konsolidasi',
                'description' => $row->keterangan ?: $row->journal?->keterangan,
                'amount' => (float) ($row->debit > 0 ? $row->debit : $row->kredit),
            ]),
        ];
    }

    protected function receivableData(?int $perumahanId): array
    {
        $rows = SprBillingSchedule::query()
            ->with(['spr.costumer:id,nama', 'spr.detailRumah.perumahan:id,nama_perusahaan'])
            ->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $query->where('perumahan_id', $id)))
            ->orderBy('tanggal_jatuh_tempo')->get()
            ->map(fn (SprBillingSchedule $row) => [
                'id' => $row->id,
                'reference' => $row->spr?->kode_spr,
                'customer' => $row->spr?->costumer?->nama,
                'perumahan' => $row->spr?->detailRumah?->perumahan?->nama_perusahaan ?? '-',
                'type' => $row->jenis_tagihan,
                'due_date' => optional($row->tanggal_jatuh_tempo)->format('Y-m-d'),
                'bill' => (float) $row->nominal_tagihan,
                'paid' => (float) $row->nominal_dibayar,
                'remaining' => max(0, (float) $row->nominal_tagihan - (float) $row->nominal_dibayar),
                'status' => $row->status,
            ]);

        return [
            'rows' => $rows,
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
            ->with('perumahan:id,nama_perusahaan')
            ->where('metode_pembayaran', 'hutang')
            ->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
            ->get()->map(fn (MaterialPurchase $row) => [
                'id' => 'supplier-'.$row->id,
                'source' => 'Supplier',
                'reference' => $row->kode_pembelian,
                'vendor' => $row->supplier ?: '-',
                'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                'due_date' => optional($row->tanggal)->format('Y-m-d'),
                'bill' => (float) $row->total_nominal,
                'paid' => $row->fund_released_at ? (float) $row->total_nominal : 0,
                'remaining' => $row->fund_released_at ? 0 : (float) $row->total_nominal,
                'status' => $row->status,
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
            'summary' => [
                'bill' => $rows->sum('bill'),
                'paid' => $rows->sum('paid'),
                'remaining' => $rows->sum('remaining'),
            ],
        ];
    }

    protected function accountMovement(ChartOfAccount $account, ?Carbon $from, Carbon $to, ?int $perumahanId): array
    {
        $query = JournalDetail::query()
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journal', fn (Builder $query) => $query
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
            'description' => $row->keterangan,
            'debit' => (float) $row->total_debit,
            'credit' => (float) $row->total_kredit,
        ];
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
            ->with('perumahan:id,nama_perusahaan')
            ->where('status', 'aktif')
            ->orderBy('nama_bank');

        if (! $request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            $query->whereIn('perumahan_id', $request->user()?->perumahans()->pluck('perumahans.id') ?? []);
        }

        return $query->get(['id', 'perumahan_id', 'nama_bank', 'nomor_rekening', 'nama_rekening'])
            ->map(fn (MasterBank $row) => [
                'value' => (string) $row->id,
                'perumahan_id' => (string) $row->perumahan_id,
                'label' => trim(($row->perumahan?->nama_perusahaan ?? '-').' - '.$row->nama_bank.' - '.$row->nomor_rekening),
            ])->all();
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

    protected function authorizeFinanceView(Request $request): void
    {
        abort_unless(
            $request->user()?->can('keuangan.view')
            || $request->user()?->can('laporan.view')
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
