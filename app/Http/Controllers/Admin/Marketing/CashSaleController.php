<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\CashSale;
use App\Models\CashSalePayment;
use App\Models\ChartOfAccount;
use App\Models\Spr;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Services\AccountingService;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\UnitOwnershipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashSaleController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan {
        HandlesCrudLock::lock as private traitLock;
        HandlesCrudLock::unlock as private traitUnlock;
    }

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'view');
        $search = trim((string) $request->query('search', ''));

        $rows = CashSale::query()
            ->with([
                'spr.costumer:id,nama,no_identitas,telepon',
                'spr.detailRumah.perumahan:id,nama_perusahaan,cabang_id',
                'handler:id,name',
            ])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
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
            'permissions' => [
                'canCreate' => $this->can($request, 'create'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'create');

        return Inertia::render('Admin/Marketing/Cash/Form', [
            'title' => 'Buat Transaksi Cash',
            'baseUrl' => route('admin.marketing.transaksi-pembelian.cash.index', absolute: false),
            'actionUrl' => route('admin.marketing.transaksi-pembelian.cash.store', absolute: false),
            'sprOptions' => $this->sprOptions(),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'view');
        $sale = $this->findSale($request, $id, [
            'spr.costumer:id,nama,no_identitas,telepon',
            'spr.detailRumah.perumahan:id,nama_perusahaan,cabang_id',
            'handler:id,name',
            'payments.creator:id,name',
        ]);

        return Inertia::render('Admin/Marketing/Cash/Show', [
            'title' => 'Detail Transaksi '.$sale->kode_cash,
            'baseUrl' => route('admin.marketing.transaksi-pembelian.cash.index', absolute: false),
            'row' => $this->row($sale),
        ]);
    }

    public function createPayment(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'update');
        $sale = $this->findSale($request, $id, [
            'spr.costumer:id,nama,no_identitas,telepon',
            'spr.detailRumah.perumahan:id,nama_perusahaan,cabang_id',
        ]);
        $this->abortIfLocked($sale);

        return Inertia::render('Admin/Marketing/Cash/PaymentForm', [
            'title' => 'Tambah Pembayaran '.$sale->kode_cash,
            'baseUrl' => route('admin.marketing.transaksi-pembelian.cash.show', $sale->id, absolute: false),
            'actionUrl' => route('admin.marketing.transaksi-pembelian.cash.payment.store', $sale->id, absolute: false),
            'paymentMethods' => $this->paymentMethodOptions(),
            'row' => $this->row($sale),
        ]);
    }

    public function store(Request $request, MarketingLeadStatusService $leadStatus): RedirectResponse
    {
        $this->authorizePermission($request, 'create');
        $validated = $request->validate([
            'spr_id' => ['required', 'exists:sprs,id', Rule::unique('cash_sales', 'spr_id')],
            'tanggal_transaksi' => ['required', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);

        $spr = Spr::query()
            ->with(['costumer', 'detailRumah.perumahan'])
            ->findOrFail($validated['spr_id']);
        $this->abortIfCurrentMarketingCannotAccessSpr($request, $spr);
        $this->abortIfCurrentMarketingCannotAccessSprPerumahan($request, $spr);

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

        return redirect()->route('admin.marketing.transaksi-pembelian.cash.index')->with('success', 'Transaksi cash berhasil dibuat dari SPR.');
    }

    public function storePayment(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'update');
        $sale = CashSale::query()
            ->with(['spr.detailRumah.perumahan'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->findOrFail($id);
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
                'perumahan_id' => $sale->spr?->detailRumah?->perumahan_id,
                'tipe_post_id' => $tipePost?->id,
                'source_type' => CashSalePayment::class,
                'source_id' => $payment->id,
                'nomor_referensi' => $sale->kode_cash,
                'tanggal' => $validated['tanggal_pembayaran'],
                'nominal' => $validated['nominal'],
                'keterangan' => trim('Pembayaran cash '.$sale->kode_cash.' - '.($validated['keterangan'] ?? '')),
                'user_id' => $request->user()?->id,
            ]);
            app(AccountingService::class)->recordFinancialTransaction($transaksiKeuangan);

            $payment->update(['transaksi_keuangan_id' => $transaksiKeuangan->id]);

            $sale->refresh();
            $sale->update($this->recalculatePaymentState($sale));
        });

        return redirect()->route('admin.marketing.transaksi-pembelian.cash.show', $sale->id)->with('success', 'Pembayaran cash berhasil disimpan dan masuk ke kas.');
    }

    public function handover(Request $request, string $id): RedirectResponse
    {
        $this->authorizePermission($request, 'update');
        $leadStatus = app(MarketingLeadStatusService::class);
        $sale = CashSale::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->findOrFail($id);
        $this->abortIfLocked($sale);

        abort_unless($sale->status_pembayaran === CashSale::STATUS_LUNAS, 422, 'Serah terima hanya bisa dilakukan setelah lunas.');

        $sale->update(['status_pembayaran' => CashSale::STATUS_SERAH_TERIMA]);
        $sale->detailRumah?->update(['status_penjualan' => 'terjual']);
        app(UnitOwnershipService::class)->syncCashHandover($sale);
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
        $request = request();

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
            'catatan' => $sale->catatan,
            'created_by' => $sale->handler?->name ?? '-',
            'record_status' => $sale->record_status ?? 'draft',
            'record_status_label' => ($sale->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'can_update' => ($sale->record_status ?? 'draft') !== 'locked' && $this->can($request, 'update'),
            'can_lock' => ($sale->record_status ?? 'draft') !== 'locked' && $this->can($request, 'lock'),
            'can_unlock' => $this->currentUserCanManageLockedRecords() && ($sale->record_status ?? 'draft') === 'locked',
            'can_handover' => ($sale->record_status ?? 'draft') !== 'locked' && $sale->status_pembayaran === CashSale::STATUS_LUNAS && $this->can($request, 'update'),
            'payments' => ($sale->relationLoaded('payments') ? $sale->payments : collect())->sortByDesc('tanggal_pembayaran')->values()->map(fn (CashSalePayment $payment) => [
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

    protected function findSale(Request $request, string $id, array $with = []): CashSale
    {
        return CashSale::query()
            ->with($with)
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->findOrFail($id);
    }

    protected function sprOptions(): array
    {
        return Spr::query()
            ->with(['costumer:id,nama,no_identitas', 'detailRumah.perumahan:id,nama_perusahaan'])
            ->where('metode_pembayaran', 'cash')
            ->where('status', Spr::STATUS_DISETUJUI)
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('created_by', request()->user()?->id))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, request())))
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

    protected function lockableQuery()
    {
        return CashSale::query()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', request()->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, request())));
    }

    protected function authorizeLockPermission(): void
    {
        $this->authorizePermission(request(), 'lock');
    }

    protected function abortIfLocked(Model $model): void
    {
        abort_if(($model->record_status ?? 'draft') === 'locked', 422, 'Data sudah dikunci. Gunakan Unlock sebelum melakukan perubahan.');
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    protected function abortIfCurrentMarketingCannotAccessSpr(Request $request, Spr $spr): void
    {
        abort_if(
            $this->shouldScopeToCurrentMarketing($request)
            && (int) $spr->created_by !== (int) $request->user()?->id,
            403,
        );
    }

    protected function abortIfCurrentMarketingCannotAccessSprPerumahan(Request $request, Spr $spr): void
    {
        if (! $this->shouldScopeToActivePerumahan($request)) {
            return;
        }

        abort_unless((int) $spr->detailRumah?->perumahan_id === $this->ensureActivePerumahan($request), 403);
    }

    private function authorizePermission(Request $request, string $action): void
    {
        abort_unless($this->can($request, $action), 403);
    }

    private function can(Request $request, string $action): bool
    {
        return (bool) ($request->user()?->hasRole('super_admin')
            || $request->user()?->can("cash-sale.{$action}")
            || $request->user()?->can('cash-sale.manage'));
    }
}
