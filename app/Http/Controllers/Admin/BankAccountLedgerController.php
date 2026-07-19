<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalDetail;
use App\Models\MasterBank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountLedgerController extends Controller
{
    use ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $bankId = $request->integer('bank_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $banks = MasterBank::query()
            ->with('perumahan:id,nama_perusahaan')
            ->where('status', 'aktif')
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->orderBy('nama_bank')
            ->get();

        $summaries = $banks->map(function (MasterBank $bank) {
            $transactions = JournalDetail::query()
                ->whereHas('account', fn (Builder $query) => $query->where('kode_akun', ChartOfAccount::KAS_BANK))
                ->whereHas('journal', fn (Builder $query) => $query->where('master_bank_id', $bank->id))
                ->get();
            $in = $transactions->sum('debit');
            $out = $transactions->sum('kredit');

            return [
                'id' => $bank->id,
                'bank' => $bank->nama_bank,
                'rekening' => $bank->nomor_rekening,
                'nama_rekening' => $bank->nama_rekening,
                'perumahan' => $bank->perumahan?->nama_perusahaan ?? '-',
                'pemasukan' => (float) $in,
                'pengeluaran' => (float) $out,
                'saldo' => (float) $in - (float) $out,
            ];
        })->values();

        $selectedBankId = $bankId && $banks->contains('id', $bankId) ? $bankId : $banks->first()?->id;
        $runningBalance = 0;
        $transactions = JournalDetail::query()
            ->with(['journal.creator:id,name', 'account:id,kode_akun'])
            ->whereHas('account', fn (Builder $query) => $query->where('kode_akun', ChartOfAccount::KAS_BANK))
            ->when($selectedBankId, fn (Builder $query) => $query->whereHas('journal', fn (Builder $journal) => $journal->where('master_bank_id', $selectedBankId)))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('journal', fn (Builder $journal) => $journal->where(function (Builder $query) use ($search) {
                $query->where('keterangan', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('nomor_jurnal', 'like', "%{$search}%");
            })))
            ->whereHas('journal')
            ->get()
            ->sortBy(fn (JournalDetail $row) => ($row->journal?->tanggal?->format('Y-m-d') ?? '').'-'.str_pad((string) $row->journal_id, 12, '0', STR_PAD_LEFT))
            ->values()
            ->map(function (JournalDetail $row) use (&$runningBalance) {
                $in = (float) $row->debit;
                $out = (float) $row->kredit;
                $runningBalance += $in - $out;

                return [
                    'id' => $row->id,
                    'tanggal' => $row->journal?->tanggal?->format('Y-m-d'),
                    'tipe_post' => str($row->journal?->type ?? '-')->replace('_', ' ')->title(),
                    'jenis' => $in > 0 ? 'pemasukan' : 'pengeluaran',
                    'keterangan' => $row->keterangan ?: $row->journal?->keterangan,
                    'pemasukan' => $in,
                    'pengeluaran' => $out,
                    'saldo' => $runningBalance,
                    'input_oleh' => $row->journal?->creator?->name ?? '-',
                ];
            })
            ->values();

        return Inertia::render('Admin/BankAccountLedger/Index', [
            'title' => 'Mutasi & Saldo Rekening',
            'baseUrl' => route('admin.bank-account-ledger.index', absolute: false),
            'bankOptions' => $banks->map(fn (MasterBank $bank) => [
                'value' => (string) $bank->id,
                'label' => trim("{$bank->nama_bank} - {$bank->nomor_rekening} - {$bank->nama_rekening}"),
            ])->values(),
            'selectedBankId' => (string) ($selectedBankId ?? ''),
            'summaries' => $summaries,
            'transactions' => $transactions,
            'filters' => ['search' => $search],
        ]);
    }

}
