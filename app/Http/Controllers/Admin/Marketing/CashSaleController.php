<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\CashSale;
use App\Models\CashSalePayment;
use App\Models\ChartOfAccount;
use App\Models\DetailRumah;
use App\Models\Spr;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Services\Marketing\MarketingLeadStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashSaleController extends Controller
{
    use HandlesCrudLock;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = CashSale::query()
            ->with([
                'spr.costumer:id,nama,no_identitas,telepon',
                'spr.detailRumah.perumahan:id,nama_perusahaan,cabang_id',
                'handler:id,name',
                'payments.creator:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_cash', 'like', "%{$search}%")
                        ->orWhere('status_pembayaran', 'like', "%{$search}%")
                        ->orWhereHas('spr.costumer', function (Builder $query) use ($search) {
                            $query->where('nama', 'like', "%{$search}%")
                                ->orWhere('no_identitas', 'like', "%{$search}%");
                        })
                        ->orWhereHas('spr.detailRumah.perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CashSale $sale) => $this->row($sale));

        return Inertia::render('Admin/Marketing/Cash/Index', [
            'title' => 'Transaksi Cash',
            'description' => 'Buat transaksi cash dari SPR yang sudah disetujui, lalu catat pembayaran dan sinkron ke kas.',
            'baseUrl' => route('admin.marketing.transaksi-pembelian.cash.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'options' => [
                'sprOptions' => $this->sprOptions(),
                'paymentMethods' => $this->paymentMethodOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        ]);
    }

    public function store(Request $request, MarketingLeadStatusService $leadStatus): RedirectResponse
    {
        $validated = $request->validate([
            'spr_id' => ['required', 'exists:sprs,id', Rule::unique('cash_sales', 'spr_id')],
            'tanggal_transaksi' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);

        $spr = Spr::query()
            ->with(['costumer', 'detailRumah.perumahan'])
            ->findOrFail($validated['spr_id']);

        abort_unless($spr->metode_pembayaran === 'cash', 422, 'SPR ini bukan transaksi cash.');
        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui sebelum dibuat transaksi cash.');
        abort_unless(! $spr->cashSale, 422, 'SPR ini sudah memiliki transaksi cash.');

        $dibayarAwal = (float) ($spr->booking_fee ?? 0) + (float) ($spr->uang_muka ?? 0);
        $hargaRumah = (float) ($spr->harga_jual ?? 0);
        $totalTagihan = $hargaRumah;
        $sisaTagihan = max(0, $totalTagihan - $dibayarAwal);

        CashSale::create([
            'kode_cash' => $this->nextCode(),
            'spr_id' => $spr->id,
            'costumer_id' => $spr->costumer_id,
            'detail_rumah_id' => $spr->detail_rumah_id,
            'handled_by' => $request->user()?->id,
            'tanggal_transaksi' => $validated['tanggal_transaksi'],
            'harga_rumah' => $hargaRumah,
            'total_tagihan' => $totalTagihan,
            'total_dibayar' => $dibayarAwal,
            'sisa_tagihan' => $sisaTagihan,
            'status_pembayaran' => $dibayarAwal > 0
                ? ($sisaTagihan <= 0 ? CashSale::STATUS_LUNAS : CashSale::STATUS_DP_DIBAYAR)
                : CashSale::STATUS_MENUNGGU_PEMBAYARAN,
            'catatan' => $validated['catatan'] ?? null,
        ]);

        $spr->detailRumah?->update(['status_penjualan' => 'proses_penjualan']);
        $leadStatus->markSpr($spr, MarketingLeadStatusService::CLOSING);

        return back()->with('success', 'Transaksi cash berhasil dibuat dari SPR.');
    }

    public function storePayment(Request $request, string $id): RedirectResponse
    {
        $sale = CashSale::query()->with(['spr.detailRumah.perumahan'])->findOrFail($id);
        $this->abortIfLocked($sale);

        $validated = $request->validate([
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'metode_pembayaran' => ['required', Rule::in(array_column($this->paymentMethodOptions(), 'value'))],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        DB::transaction(function () use ($sale, $request, $validated) {
            abort_unless($sale->spr?->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat kas.');
            $bukti = $request->file('bukti_pembayaran')?->store('cash-sale-payments', 'public');
            $payment = CashSalePayment::create([
                'cash_sale_id' => $sale->id,
                'created_by' => $request->user()?->id,
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => $validated['nominal'],
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'keterangan' => $validated['keterangan'] ?? null,
                'bukti_pembayaran' => $bukti,
            ]);

            $tipePost = $this->resolveTipePost($sale, (float) $validated['nominal']);
            $transaksiKeuangan = TransaksiKeuangan::create([
                'cabang_id' => $sale->spr?->detailRumah?->perumahan?->cabang_id,
                'tipe_post_id' => $tipePost?->id,
                'tanggal' => $validated['tanggal_pembayaran'],
                'nominal' => $validated['nominal'],
                'keterangan' => trim('Pembayaran cash '.$sale->kode_cash.' - '.($validated['keterangan'] ?? '')),
                'user_id' => $request->user()?->id,
            ]);

            $payment->update(['transaksi_keuangan_id' => $transaksiKeuangan->id]);

            $sale->refresh();
            $sale->update($this->recalculatePaymentState($sale));
        });

        return back()->with('success', 'Pembayaran cash berhasil disimpan dan masuk ke kas.');
    }

    public function handover(string $id): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $sale = CashSale::query()->findOrFail($id);
        $this->abortIfLocked($sale);

        abort_unless($sale->status_pembayaran === CashSale::STATUS_LUNAS, 422, 'Serah terima hanya bisa dilakukan setelah lunas.');

        $sale->update(['status_pembayaran' => CashSale::STATUS_SERAH_TERIMA]);
        $sale->detailRumah?->update(['status_penjualan' => 'terjual']);
        if ($sale->spr) {
            $leadStatus->markSpr($sale->spr, MarketingLeadStatusService::CLOSING);
        }

        return back()->with('success', 'Serah terima berhasil disimpan.');
    }

    public function lock(string $id): RedirectResponse
    {
        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        return $this->traitUnlock($id);
    }

    protected function row(CashSale $sale): array
    {
        return [
            'id' => $sale->id,
            'kode_cash' => $sale->kode_cash,
            'kode_spr' => $sale->spr?->kode_spr ?? '-',
            'customer' => $sale->spr?->costumer?->nama ?? '-',
            'no_identitas' => $sale->spr?->costumer?->no_identitas ?? '-',
            'unit' => $sale->spr?->detailRumah ? trim(($sale->spr->detailRumah->kode_nlok ?? '').' '.($sale->spr->detailRumah->nomor_rumah ?? '')) : '-',
            'perumahan' => $sale->spr?->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'tanggal_transaksi' => optional($sale->tanggal_transaksi)->format('Y-m-d'),
            'harga_rumah' => $sale->harga_rumah,
            'total_tagihan' => $sale->total_tagihan,
            'total_dibayar' => $sale->total_dibayar,
            'sisa_tagihan' => $sale->sisa_tagihan,
            'status_pembayaran' => $sale->status_pembayaran,
            'status_label' => $this->statusLabel($sale->status_pembayaran),
            'payments_count' => $sale->payments->count(),
            'last_payment' => $sale->payments->sortByDesc('tanggal_pembayaran')->first()?->nominal ?? 0,
            'catatan' => $sale->catatan,
            'created_by' => $sale->handler?->name ?? '-',
            'record_status' => $sale->record_status ?? 'draft',
            'record_status_label' => ($sale->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'can_handover' => $sale->status_pembayaran === CashSale::STATUS_LUNAS,
            'payments' => $sale->payments->sortByDesc('tanggal_pembayaran')->values()->map(fn (CashSalePayment $payment) => [
                'id' => $payment->id,
                'tanggal_pembayaran' => optional($payment->tanggal_pembayaran)->format('Y-m-d'),
                'nominal' => $payment->nominal,
                'metode_pembayaran' => $payment->metode_pembayaran,
                'keterangan' => $payment->keterangan,
                'bukti_url' => $payment->bukti_pembayaran ? route('media', ['path' => $payment->bukti_pembayaran], false) : null,
                'created_by' => $payment->creator?->name ?? '-',
            ])->all(),
        ];
    }

    protected function sprOptions(): array
    {
        return Spr::query()
            ->with(['costumer:id,nama,no_identitas', 'detailRumah.perumahan:id,nama_perusahaan'])
            ->where('metode_pembayaran', 'cash')
            ->where('status', Spr::STATUS_DISETUJUI)
            ->whereDoesntHave('cashSale')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn (Spr $spr) => [
                'value' => (string) $spr->id,
                'label' => $spr->kode_spr.' - '.$spr->costumer?->nama.' - '.trim(($spr->detailRumah?->kode_nlok ?? '').' '.($spr->detailRumah?->nomor_rumah ?? '')),
                'customer' => $spr->costumer?->nama,
                'no_identitas' => $spr->costumer?->no_identitas,
                'unit' => $spr->detailRumah ? trim(($spr->detailRumah->kode_nlok ?? '').' '.($spr->detailRumah->nomor_rumah ?? '')) : '-',
                'perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan ?? '-',
                'harga_jual' => (float) ($spr->harga_jual ?? 0),
                'booking_fee' => (float) ($spr->booking_fee ?? 0),
                'uang_muka' => (float) ($spr->uang_muka ?? 0),
                'sisa_sementara' => max(0, (float) ($spr->harga_jual ?? 0) - ((float) ($spr->booking_fee ?? 0) + (float) ($spr->uang_muka ?? 0))),
            ])
            ->values()
            ->all();
    }

    protected function paymentMethodOptions(): array
    {
        return [
            ['value' => 'transfer', 'label' => 'Transfer'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'cek', 'label' => 'Cek / Giro'],
            ['value' => 'lainnya', 'label' => 'Lainnya'],
        ];
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => CashSale::STATUS_MENUNGGU_PEMBAYARAN, 'label' => 'Menunggu Pembayaran'],
            ['value' => CashSale::STATUS_DP_DIBAYAR, 'label' => 'DP Dibayar'],
            ['value' => CashSale::STATUS_CICILAN_TERMIN, 'label' => 'Cicilan Termin'],
            ['value' => CashSale::STATUS_LUNAS, 'label' => 'Lunas'],
            ['value' => CashSale::STATUS_SERAH_TERIMA, 'label' => 'Serah Terima'],
        ];
    }

    protected function statusLabel(?string $status): string
    {
        foreach ($this->statusOptions() as $option) {
            if ($option['value'] === $status) {
                return $option['label'];
            }
        }

        return $status ?? '-';
    }

    protected function nextCode(): string
    {
        $next = (int) (CashSale::withTrashed()->max('id') ?? 0) + 1;

        return 'CASH-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function resolveTipePost(CashSale $sale, float $nominal): ?TipePost
    {
        $initialPaid = (float) ($sale->spr?->booking_fee ?? 0) + (float) ($sale->spr?->uang_muka ?? 0);
        $existingPayments = (float) $sale->payments()->sum('nominal') + $initialPaid;
        $newTotal = $existingPayments + $nominal;
        $status = $sale->total_tagihan > 0 && $newTotal >= $sale->total_tagihan
            ? 'Pelunasan Cash'
            : ($existingPayments > 0 ? 'Cicilan Cash' : 'DP Cash');

        return TipePost::query()->firstOrCreate(
            ['nama_post' => $status],
            [
                'jenis' => 'pemasukan',
                'status' => 'aktif',
                'debit_account_id' => ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id'),
                'credit_account_id' => null,
                'is_system' => true,
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => auth()->id(),
            ],
        );
    }

    protected function recalculatePaymentState(CashSale $sale): array
    {
        $initialPaid = (float) ($sale->spr?->booking_fee ?? 0) + (float) ($sale->spr?->uang_muka ?? 0);
        $totalDibayar = (float) $sale->payments()->sum('nominal') + $initialPaid;
        $totalTagihan = (float) ($sale->total_tagihan ?? 0);
        $sisaTagihan = max(0, $totalTagihan - $totalDibayar);

        $status = $sale->status_pembayaran;

        if ($totalDibayar <= 0) {
            $status = CashSale::STATUS_MENUNGGU_PEMBAYARAN;
        } elseif ($sisaTagihan <= 0) {
            $status = CashSale::STATUS_LUNAS;
        } elseif ($sale->payments()->count() <= 1) {
            $status = CashSale::STATUS_DP_DIBAYAR;
        } else {
            $status = CashSale::STATUS_CICILAN_TERMIN;
        }

        return [
            'total_dibayar' => $totalDibayar,
            'sisa_tagihan' => $sisaTagihan,
            'status_pembayaran' => $status,
        ];
    }

    protected function modelClass(): string
    {
        return CashSale::class;
    }
}
