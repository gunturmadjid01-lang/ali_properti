<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterBank;
use App\Models\TransaksiKeuangan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $bankId = $request->integer('bank_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $banks = MasterBank::query()
            ->with('perumahan:id,nama_perusahaan')
            ->where('status', 'aktif')
            ->orderBy('nama_bank')
            ->get();

        $summaries = $banks->map(function (MasterBank $bank) {
            $transactions = TransaksiKeuangan::query()
                ->where('master_bank_id', $bank->id)
                ->with('tipePost:id,jenis')
                ->get();
            $in = $transactions->where(fn ($row) => $row->tipePost?->jenis === 'pemasukan')->sum('nominal');
            $out = $transactions->where(fn ($row) => $row->tipePost?->jenis === 'pengeluaran')->sum('nominal');

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

        $selectedBankId = $bankId ?: $banks->first()?->id;
        $runningBalance = 0;
        $transactions = TransaksiKeuangan::query()
            ->with(['tipePost:id,nama_post,jenis', 'user:id,name'])
            ->when($selectedBankId, fn (Builder $query) => $query->where('master_bank_id', $selectedBankId))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->where('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('tipePost', fn (Builder $query) => $query->where('nama_post', 'like', "%{$search}%"));
            }))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->map(function (TransaksiKeuangan $row) use (&$runningBalance) {
                $isIncome = $row->tipePost?->jenis === 'pemasukan';
                $in = $isIncome ? (float) $row->nominal : 0;
                $out = $isIncome ? 0 : (float) $row->nominal;
                $runningBalance += $in - $out;

                return [
                    'id' => $row->id,
                    'tanggal' => $row->tanggal?->format('Y-m-d'),
                    'tipe_post' => $row->tipePost?->nama_post ?? '-',
                    'jenis' => $row->tipePost?->jenis ?? '-',
                    'keterangan' => $row->keterangan,
                    'pemasukan' => $in,
                    'pengeluaran' => $out,
                    'saldo' => $runningBalance,
                    'input_oleh' => $row->user?->name ?? '-',
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
