<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\DetailRumah;
use App\Models\MasterBank;
use App\Models\Spr;
use App\Models\SprPayment;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Services\AccountingService;
use App\Services\Marketing\MarketingLeadStatusService;
use App\Services\Marketing\MarketingOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SprPaymentController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $tab = in_array($request->query('tab'), ['booking', 'dp'], true)
            ? $request->query('tab')
            : 'booking';

        $sprs = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan',
                'payments.masterBank:id,kode_bank,nama_bank,nomor_rekening,nama_rekening',
                'payments.creator:id,name',
                'payments.confirmer:id,name',
            ])
            ->where('status', Spr::STATUS_DISETUJUI)
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_spr', 'like', "%{$search}%")
                        ->orWhereHas('costumer', fn (Builder $query) => $query
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_identitas', 'like', "%{$search}%"))
                        ->orWhereHas('detailRumah.perumahan', fn (Builder $query) => $query
                            ->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->get();

        $bookingRows = $sprs
            ->filter(fn (Spr $spr) => $this->bookingRemaining($spr) > 0)
            ->map(fn (Spr $spr) => $this->paymentRow($spr))
            ->values();

        $dpRows = $sprs
            ->filter(fn (Spr $spr) => $this->dpRemaining($spr) > 0 && $this->bookingPaid($spr) >= (float) ($spr->booking_fee ?? 0))
            ->map(fn (Spr $spr) => $this->paymentRow($spr))
            ->values();

        return Inertia::render('Admin/Marketing/SprPayment/Index', [
            'title' => 'Pembayaran SPR',
            'description' => 'Kelola pembayaran Booking Fee dan Uang Muka dari SPR yang sudah disetujui.',
            'baseUrl' => route('admin.marketing.pembayaran-spr.index', absolute: false),
            'filters' => ['search' => $search, 'tab' => $tab],
            'bookingRows' => $bookingRows,
            'dpRows' => $dpRows,
            'bankOptions' => $this->bankOptions(),
            'canConfirmPayments' => $this->canConfirmPayments($request),
            'tabs' => [
                ['value' => 'booking', 'label' => 'Booking Fee'],
                ['value' => 'dp', 'label' => 'Uang Muka'],
            ],
        ]);
    }

    public function financeIndex(Request $request): Response
    {
        abort_unless($this->canConfirmPayments($request), 403, 'Hanya admin/keuangan/owner yang dapat mengakses konfirmasi pembayaran.');

        $search = trim((string) $request->query('search', ''));
        $tab = in_array($request->query('tab'), ['booking', 'dp'], true)
            ? $request->query('tab')
            : 'booking';

        $sprs = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan',
                'payments.masterBank:id,kode_bank,nama_bank,nomor_rekening,nama_rekening',
                'payments.creator:id,name',
                'payments.confirmer:id,name',
            ])
            ->whereHas('payments', fn (Builder $query) => $query->whereIn('jenis_pembayaran', ['booking_fee', 'uang_muka']))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode_spr', 'like', "%{$search}%")
                        ->orWhereHas('costumer', fn (Builder $query) => $query
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_identitas', 'like', "%{$search}%"))
                        ->orWhereHas('detailRumah.perumahan', fn (Builder $query) => $query
                            ->where('nama_perusahaan', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->get();

        $bookingRows = $sprs
            ->filter(fn (Spr $spr) => $spr->payments->where('jenis_pembayaran', 'booking_fee')->isNotEmpty())
            ->map(fn (Spr $spr) => $this->paymentRow($spr))
            ->values();

        $dpRows = $sprs
            ->filter(fn (Spr $spr) => $spr->payments->where('jenis_pembayaran', 'uang_muka')->isNotEmpty())
            ->map(fn (Spr $spr) => $this->paymentRow($spr))
            ->values();

        return Inertia::render('Admin/Marketing/SprPayment/Index', [
            'title' => 'Konfirmasi Pembayaran SPR',
            'description' => 'Validasi pembayaran Booking Fee dan Uang Muka yang diinput oleh marketing sebelum masuk transaksi keuangan.',
            'baseUrl' => route('admin.keuangan.pembayaran-spr.index', absolute: false),
            'filters' => ['search' => $search, 'tab' => $tab],
            'bookingRows' => $bookingRows,
            'dpRows' => $dpRows,
            'bankOptions' => [],
            'canConfirmPayments' => true,
            'canInputPayments' => false,
            'areaLabel' => 'Keuangan',
            'tabs' => [
                ['value' => 'booking', 'label' => 'Booking Fee'],
                ['value' => 'dp', 'label' => 'Uang Muka'],
            ],
        ]);
    }

    public function refundIndex(Request $request): Response
    {
        abort_unless($this->canAccessRefundPage($request), 403, 'Hanya keuangan, manajer, atau owner yang dapat mengakses refund SPR.');

        $search = trim((string) $request->query('search', ''));
        $mode = $request->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']) ? 'approval' : 'finance';

        $sprs = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan,cabang_id',
                'payments.masterBank:id,kode_bank,nama_bank,nomor_rekening,nama_rekening',
            ])
            ->when($mode === 'approval', fn (Builder $query) => $query->whereNotNull('refund_status'))
            ->whereHas('payments', fn (Builder $query) => $query
                ->whereIn('jenis_pembayaran', ['booking_fee', 'uang_muka'])
                ->where('status', 'dikonfirmasi'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('kode_spr', 'like', "%{$search}%")
                        ->orWhereHas('costumer', fn (Builder $query) => $query
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_identitas', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->get()
            ->map(fn (Spr $spr) => $this->refundRow($spr, $request))
            ->values();

        return Inertia::render('Admin/Marketing/SprPayment/Refund', [
            'title' => $mode === 'approval' ? 'Approval Refund SPR' : 'Refund Booking Fee & Uang Muka',
            'description' => $mode === 'approval'
                ? 'Review pengajuan pengembalian dana booking fee dan uang muka sebelum dicairkan.'
                : 'Ajukan pengembalian dana customer untuk SPR yang batal atau tidak lanjut.',
            'baseUrl' => $mode === 'approval'
                ? route('admin.refund-spr.approval.index', absolute: false)
                : route('admin.keuangan.refund-spr.index', absolute: false),
            'filters' => ['search' => $search],
            'rows' => $sprs,
            'bankOptions' => $this->bankOptions(),
            'mode' => $mode,
        ]);
    }

    public function storeRefundRequest(Request $request, string $sprId): RedirectResponse
    {
        abort_unless($this->canRequestRefund($request), 403, 'Hanya admin keuangan yang dapat mengajukan refund.');

        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);

        $validated = $request->validate([
            'alasan_batal' => ['required', 'string', 'max:255'],
            'refund_amount' => ['required', 'numeric', 'min:1'],
            'refund_master_bank_id' => ['required', 'exists:master_banks,id'],
            'refund_at' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ]);

        $paidTotal = $this->bookingPaid($spr) + $this->dpPaid($spr) + $this->otherPaid($spr);
        $refundAmount = (float) $validated['refund_amount'];

        abort_if($refundAmount > $paidTotal, 422, 'Jumlah refund tidak boleh lebih besar dari total pembayaran yang sudah dikonfirmasi.');
        abort_if(! $spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat refund.');
        abort_if(in_array($spr->refund_status, ['menunggu_manager', 'menunggu_owner'], true), 422, 'SPR ini sudah memiliki pengajuan refund yang sedang berjalan.');

        DB::transaction(function () use ($request, $spr, $validated, $refundAmount): void {
            $spr->update([
                'status' => Spr::STATUS_DITOLAK,
                'alasan_batal' => $validated['alasan_batal'],
                'refund_master_bank_id' => $validated['refund_master_bank_id'],
                'refund_amount' => $refundAmount,
                'refund_at' => $validated['refund_at'] ?? now()->toDateString(),
                'refund_status' => 'menunggu_manager',
                'refund_requested_by' => $request->user()?->id,
                'refund_requested_at' => now(),
                'refund_manager_approved_by' => null,
                'refund_manager_approved_at' => null,
                'refund_owner_approved_by' => null,
                'refund_owner_approved_at' => null,
                'refund_rejected_by' => null,
                'refund_rejected_at' => null,
                'refund_approval_note' => $validated['catatan'] ?? null,
                'catatan' => trim(($spr->catatan ? $spr->catatan."\n" : '').($validated['catatan'] ?? '')),
            ]);

            $spr->detailRumah?->update([
                'status_penjualan' => 'tersedia',
                'booking_spr_id' => null,
                'booking_at' => null,
            ]);
        });

        return to_route('admin.refund-spr.index')->with('success', 'Pengajuan refund berhasil dikirim ke manajer.');
    }

    public function createRefundRequest(Request $request, string $sprId): Response
    {
        abort_unless($this->canRequestRefund($request), 403, 'Hanya admin keuangan yang dapat mengajukan refund.');
        $spr = Spr::query()->with(['costumer', 'detailRumah.perumahan', 'payments'])->findOrFail($sprId);
        $row = $this->refundRow($spr, $request);
        abort_unless($row['can_request'], 422, 'SPR ini tidak dapat diajukan refund.');

        return Inertia::render('Admin/Marketing/SprPayment/RefundFormPage', [
            'title' => 'Ajukan Refund '.$spr->kode_spr,
            'mode' => 'request',
            'baseUrl' => route('admin.refund-spr.index', absolute: false),
            'actionUrl' => route('admin.keuangan.refund-spr.store', $spr->id, false),
            'row' => $row,
            'bankOptions' => $this->bankOptions(),
        ]);
    }

    public function reviewRefund(Request $request, string $sprId, string $action): Response
    {
        abort_unless(in_array($action, ['manager', 'owner', 'reject'], true), 404);
        $spr = Spr::query()->with(['costumer', 'detailRumah.perumahan', 'payments'])->findOrFail($sprId);
        $row = $this->refundRow($spr, $request);
        abort_unless(
            ($action === 'manager' && $row['can_approve_manager'])
            || ($action === 'owner' && $row['can_approve_owner'])
            || ($action === 'reject' && $row['can_reject']),
            403,
        );

        $actionUrl = match ($action) {
            'manager' => route('admin.refund-spr.approve-manager', $spr->id, false),
            'owner' => route('admin.refund-spr.approve-owner', $spr->id, false),
            default => route('admin.refund-spr.reject', $spr->id, false),
        };

        return Inertia::render('Admin/Marketing/SprPayment/RefundFormPage', [
            'title' => ($action === 'reject' ? 'Tolak' : 'Approval').' Refund '.$spr->kode_spr,
            'mode' => 'review',
            'action' => $action,
            'baseUrl' => route('admin.refund-spr.index', absolute: false),
            'actionUrl' => $actionUrl,
            'row' => $row,
            'bankOptions' => [],
        ]);
    }

    public function approveRefundManager(Request $request, string $sprId): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']), 403, 'Hanya manajer/owner yang dapat approve refund tahap manajer.');

        $spr = Spr::query()->findOrFail($sprId);
        abort_unless(($spr->record_status ?? 'draft') === 'locked', 422, 'Refund harus di-lock terlebih dahulu.');
        abort_unless($spr->refund_status === 'menunggu_manager', 422, 'Refund tidak sedang menunggu approval manajer.');

        $validated = $request->validate(['note' => ['nullable', 'string']]);

        $spr->update([
            'refund_status' => 'menunggu_owner',
            'refund_manager_approved_by' => $request->user()?->id,
            'refund_manager_approved_at' => now(),
            'refund_approval_note' => $this->appendApprovalNote($spr->refund_approval_note, 'Manajer', $validated['note'] ?? null),
        ]);

        return to_route('admin.refund-spr.index')->with('success', 'Refund disetujui manajer dan menunggu owner.');
    }

    public function approveRefundOwner(Request $request, string $sprId): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat approve final refund.');

        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang'])
            ->findOrFail($sprId);
        abort_unless(($spr->record_status ?? 'draft') === 'locked', 422, 'Refund harus di-lock terlebih dahulu.');
        abort_unless($spr->refund_status === 'menunggu_owner', 422, 'Refund belum siap approval owner.');
        abort_unless(! $spr->refund_transaksi_keuangan_id, 422, 'Refund ini sudah dicairkan.');

        $validated = $request->validate(['note' => ['nullable', 'string']]);

        DB::transaction(function () use ($request, $spr, $validated): void {
            $tipePost = $this->resolveTipePost('Pengembalian Dana SPR', 'pengeluaran');
            $transaksiKeuangan = TransaksiKeuangan::create([
                'cabang_id' => $spr->detailRumah?->perumahan?->cabang_id,
                'perumahan_id' => $spr->detailRumah?->perumahan_id,
                'master_bank_id' => $spr->refund_master_bank_id,
                'tipe_post_id' => $tipePost->id,
                'source_type' => Spr::class,
                'source_id' => $spr->id,
                'nomor_referensi' => $spr->kode_spr,
                'tanggal' => $spr->refund_at ?? now()->toDateString(),
                'nominal' => (float) $spr->refund_amount,
                'keterangan' => 'Pengembalian dana SPR '.$spr->kode_spr.' - '.($spr->alasan_batal ?? 'Refund customer'),
                'user_id' => $request->user()?->id,
            ]);
            app(AccountingService::class)->recordFinancialTransaction($transaksiKeuangan);

            $spr->update([
                'refund_status' => 'disetujui',
                'refund_owner_approved_by' => $request->user()?->id,
                'refund_owner_approved_at' => now(),
                'refund_transaksi_keuangan_id' => $transaksiKeuangan->id,
                'refund_approval_note' => $this->appendApprovalNote($spr->refund_approval_note, 'Owner', $validated['note'] ?? null),
            ]);
        });

        return to_route('admin.refund-spr.index')->with('success', 'Refund disetujui owner dan transaksi pengeluaran sudah dibuat.');
    }

    public function rejectRefund(Request $request, string $sprId): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']), 403, 'Hanya manajer/owner yang dapat menolak refund.');

        $validated = $request->validate(['note' => ['required', 'string']]);
        $spr = Spr::query()->findOrFail($sprId);
        abort_unless(($spr->record_status ?? 'draft') === 'locked', 422, 'Refund harus di-lock terlebih dahulu.');
        abort_unless(in_array($spr->refund_status, ['menunggu_manager', 'menunggu_owner'], true), 422, 'Refund sudah diproses.');

        $spr->update([
            'refund_status' => 'ditolak',
            'refund_rejected_by' => $request->user()?->id,
            'refund_rejected_at' => now(),
            'refund_approval_note' => $this->appendApprovalNote($spr->refund_approval_note, 'Reject', $validated['note']),
        ]);

        return to_route('admin.refund-spr.index')->with('success', 'Refund ditolak.');
    }

    public function storeBookingFee(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);
        $this->abortIfCurrentMarketingCannotAccessSpr($request, $spr);
        $this->abortIfCurrentMarketingCannotAccessSprPerumahan($request, $spr);

        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui.');

        $validated = $request->validate([
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['required', 'file', 'image', 'max:4096'],
        ]);

        abort_unless($spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');
        $this->validatePaymentAmountDoesNotExceedRemaining($spr, 'booking_fee', (float) $validated['nominal']);

        DB::transaction(function () use ($request, $spr, $validated) {
            $bukti = $request->file('bukti_pembayaran')?->store('spr-payments', 'public');
            SprPayment::create([
                'spr_id' => $spr->id,
                'master_bank_id' => $validated['master_bank_id'],
                'created_by' => $request->user()?->id,
                'jenis_pembayaran' => 'booking_fee',
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'bukti_pembayaran' => $bukti,
                'keterangan' => $validated['keterangan'] ?? null,
                'status' => 'menunggu_konfirmasi',
            ]);
        });

        return back()->with('success', 'Pembayaran Booking Fee berhasil diajukan dan menunggu konfirmasi admin.');
    }

    public function storeDownPayment(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);
        $this->abortIfCurrentMarketingCannotAccessSpr($request, $spr);
        $this->abortIfCurrentMarketingCannotAccessSprPerumahan($request, $spr);

        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui.');

        $validated = $request->validate([
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['required', 'file', 'image', 'max:4096'],
        ]);

        abort_unless($spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');
        $this->validatePaymentAmountDoesNotExceedRemaining($spr, 'uang_muka', (float) $validated['nominal']);
        $this->validateDownPaymentInstallmentLimit($spr);

        DB::transaction(function () use ($request, $spr, $validated) {
            $bukti = $request->file('bukti_pembayaran')?->store('spr-payments', 'public');
            SprPayment::create([
                'spr_id' => $spr->id,
                'master_bank_id' => $validated['master_bank_id'],
                'created_by' => $request->user()?->id,
                'jenis_pembayaran' => 'uang_muka',
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'bukti_pembayaran' => $bukti,
                'keterangan' => $validated['keterangan'] ?? null,
                'status' => 'menunggu_konfirmasi',
            ]);
        });

        return back()->with('success', 'Pembayaran Uang Muka berhasil diajukan dan menunggu konfirmasi admin.');
    }

    public function storeOtherPayment(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);
        $this->abortIfCurrentMarketingCannotAccessSpr($request, $spr);
        $this->abortIfCurrentMarketingCannotAccessSprPerumahan($request, $spr);

        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui.');

        $validated = $request->validate([
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['required', 'file', 'image', 'max:4096'],
        ]);

        abort_unless($spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');

        DB::transaction(function () use ($request, $spr, $validated) {
            $bukti = $request->file('bukti_pembayaran')?->store('spr-payments', 'public');
            SprPayment::create([
                'spr_id' => $spr->id,
                'master_bank_id' => $validated['master_bank_id'],
                'created_by' => $request->user()?->id,
                'jenis_pembayaran' => 'lainnya',
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'bukti_pembayaran' => $bukti,
                'keterangan' => $validated['keterangan'] ?? null,
                'status' => 'menunggu_konfirmasi',
            ]);
        });

        return back()->with('success', 'Pembayaran lainnya berhasil diajukan dan menunggu konfirmasi admin.');
    }

    public function confirmPayment(Request $request, string $paymentId): RedirectResponse
    {
        abort_unless($this->canConfirmPayments($request), 403, 'Hanya admin/keuangan/owner yang dapat konfirmasi pembayaran.');

        $payment = SprPayment::query()
            ->with(['spr.detailRumah.perumahan.cabang', 'spr.payments'])
            ->findOrFail($paymentId);

        abort_unless($payment->status === 'menunggu_konfirmasi', 422, 'Pembayaran ini sudah diproses.');
        abort_unless($payment->spr?->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');

        $validated = $request->validate([
            'confirmation_note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $payment, $validated): void {
            $spr = $payment->spr;
            $tipePost = $this->resolveTipePost(match ($payment->jenis_pembayaran) {
                'booking_fee' => 'Booking Fee SPR',
                'uang_muka' => 'Uang Muka SPR',
                default => 'Pembayaran Lainnya SPR',
            });

            $transaksiKeuangan = TransaksiKeuangan::create([
                'cabang_id' => $spr->detailRumah?->perumahan?->cabang_id,
                'perumahan_id' => $spr->detailRumah?->perumahan_id,
                'master_bank_id' => $payment->master_bank_id,
                'tipe_post_id' => $tipePost->id,
                'source_type' => SprPayment::class,
                'source_id' => $payment->id,
                'nomor_referensi' => $spr->kode_spr,
                'tanggal' => $payment->tanggal_pembayaran,
                'nominal' => (float) $payment->nominal,
                'keterangan' => trim(ucwords(str_replace('_', ' ', $payment->jenis_pembayaran)).' SPR '.$spr->kode_spr.' - '.($payment->keterangan ?? '')),
                'user_id' => $request->user()?->id,
            ]);
            app(AccountingService::class)->recordFinancialTransaction($transaksiKeuangan);

            $payment->update([
                'status' => 'dikonfirmasi',
                'confirmed_at' => now(),
                'confirmed_by' => $request->user()?->id,
                'confirmation_note' => $validated['confirmation_note'] ?? null,
                'transaksi_keuangan_id' => $transaksiKeuangan->id,
            ]);

            if ($payment->jenis_pembayaran === 'booking_fee') {
                $spr->detailRumah?->update(['status_penjualan' => 'booking']);
                app(MarketingLeadStatusService::class)->markSpr($spr, MarketingLeadStatusService::BOOKING_FEE);
            } else {
                $this->syncDownPaymentState($spr);
                app(MarketingLeadStatusService::class)->markSpr($spr, MarketingLeadStatusService::CLOSING);
            }

            app(MarketingOperationsService::class)->syncBillingSchedules($spr->fresh(['payments']));
        });

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    public function rejectPayment(Request $request, string $paymentId): RedirectResponse
    {
        abort_unless($this->canConfirmPayments($request), 403, 'Hanya admin/keuangan/owner yang dapat menolak pembayaran.');

        $validated = $request->validate([
            'confirmation_note' => ['nullable', 'string'],
        ]);

        $payment = SprPayment::query()->findOrFail($paymentId);
        abort_unless($payment->status === 'menunggu_konfirmasi', 422, 'Pembayaran ini sudah diproses.');
        $payment->update([
            'status' => 'ditolak',
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()?->id,
            'confirmation_note' => $validated['confirmation_note'] ?? null,
        ]);

        return back()->with('success', 'Pembayaran ditolak.');
    }

    public function cancelSpr(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);
        $this->abortIfCurrentMarketingCannotAccessSpr($request, $spr);
        $this->abortIfCurrentMarketingCannotAccessSprPerumahan($request, $spr);
        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR yang belum disetujui tidak perlu di-cancel dari pembayaran.');

        $validated = $request->validate([
            'alasan_batal' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($spr, $validated, $leadStatus) {
            $spr->update([
                'status' => Spr::STATUS_DITOLAK,
                'alasan_batal' => $validated['alasan_batal'],
                'catatan' => trim(($spr->catatan ? $spr->catatan."\n" : '').($validated['catatan'] ?? '')),
            ]);

            $spr->detailRumah?->update([
                'status_penjualan' => 'tersedia',
                'booking_spr_id' => null,
                'booking_at' => null,
            ]);
            $leadStatus->markSpr($spr, MarketingLeadStatusService::BATAL);
        });

        return back()->with('success', 'SPR berhasil dibatalkan.');
    }

    protected function bookingRemaining(Spr $spr): float
    {
        return max(0, (float) ($spr->booking_fee ?? 0) - $this->bookingSubmitted($spr));
    }

    protected function bookingPaid(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'booking_fee')->where('status', 'dikonfirmasi')->sum('nominal');
    }

    protected function bookingSubmitted(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'booking_fee')->where('status', '!=', 'ditolak')->sum('nominal');
    }

    protected function dpPaid(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'uang_muka')->where('status', 'dikonfirmasi')->sum('nominal');
    }

    protected function dpSubmitted(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'uang_muka')->where('status', '!=', 'ditolak')->sum('nominal');
    }

    protected function otherPaid(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'lainnya')->where('status', 'dikonfirmasi')->sum('nominal');
    }

    protected function dpRemaining(Spr $spr): float
    {
        return max(0, (float) ($spr->uang_muka ?? 0) - $this->dpSubmitted($spr));
    }

    protected function validatePaymentAmountDoesNotExceedRemaining(Spr $spr, string $type, float $nominal): void
    {
        $remaining = $type === 'booking_fee'
            ? $this->bookingRemaining($spr)
            : $this->dpRemaining($spr);

        abort_if($remaining <= 0, 422, $type === 'booking_fee'
            ? 'Booking Fee sudah lunas atau sedang menunggu konfirmasi.'
            : 'Uang Muka sudah lunas atau sedang menunggu konfirmasi.');

        abort_if($nominal > $remaining, 422, 'Nominal pembayaran melebihi sisa tagihan. Sisa yang dapat diinput: Rp '.number_format($remaining, 0, ',', '.').'.');
    }

    protected function validateDownPaymentInstallmentLimit(Spr $spr): void
    {
        $maxInstallments = (int) ($spr->uang_muka_jumlah_pembayaran ?? 0);

        if ($maxInstallments <= 0) {
            return;
        }

        $submittedInstallments = $spr->payments
            ->where('jenis_pembayaran', 'uang_muka')
            ->where('status', '!=', 'ditolak')
            ->count();

        abort_if($submittedInstallments >= $maxInstallments, 422, "Pembayaran Uang Muka maksimal {$maxInstallments} kali sesuai termin SPR.");
    }

    protected function paymentRow(Spr $spr): array
    {
        $bookingPaid = $this->bookingPaid($spr);
        $dpPaid = $this->dpPaid($spr);
        $otherPaid = $this->otherPaid($spr);
        $bookingRemaining = $this->bookingRemaining($spr);
        $dpRemaining = $this->dpRemaining($spr);
        $bookingPayments = $spr->payments->where('jenis_pembayaran', 'booking_fee');
        $dpPayments = $spr->payments->where('jenis_pembayaran', 'uang_muka');
        $bookingPending = $bookingPayments->where('status', 'menunggu_konfirmasi')->count();
        $dpPending = $dpPayments->where('status', 'menunggu_konfirmasi')->count();

        return [
            'id' => $spr->id,
            'kode_spr' => $spr->kode_spr,
            'customer' => $spr->costumer?->nama ?? '-',
            'no_identitas' => $spr->costumer?->no_identitas ?? '-',
            'telepon' => $spr->costumer?->telepon ?? '-',
            'unit' => $spr->detailRumah ? trim(($spr->detailRumah->kode_nlok ?? '').' '.($spr->detailRumah->nomor_rumah ?? '')) : '-',
            'perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'booking_fee' => (float) ($spr->booking_fee ?? 0),
            'booking_paid' => $bookingPaid,
            'booking_remaining' => $bookingRemaining,
            'uang_muka' => (float) ($spr->uang_muka ?? 0),
            'dp_paid' => $dpPaid,
            'dp_remaining' => $dpRemaining,
            'dp_installments_used' => $dpPayments->where('status', '!=', 'ditolak')->count(),
            'dp_installments_limit' => (int) ($spr->uang_muka_jumlah_pembayaran ?? 0),
            'other_paid' => $otherPaid,
            'booking_status' => $bookingRemaining <= 0 ? 'Lunas' : 'Belum Bayar',
            'dp_status' => $dpRemaining <= 0 ? 'DP Lunas' : 'DP',
            'other_status' => $otherPaid > 0 ? 'Ada Pembayaran' : 'Belum Ada',
            'booking_confirmation_status' => $bookingPending > 0 ? $bookingPending.' Menunggu Konfirmasi' : ($bookingPayments->isNotEmpty() ? 'Sudah Diproses' : 'Belum Ada Pembayaran'),
            'dp_confirmation_status' => $dpPending > 0 ? $dpPending.' Menunggu Konfirmasi' : ($dpPayments->isNotEmpty() ? 'Sudah Diproses' : 'Belum Ada Pembayaran'),
            'booking_fee_includes_dp' => (bool) ($spr->booking_fee_includes_dp ?? false),
            'tanggal_jatuh_tempo_dp' => optional($spr->tanggal_jatuh_tempo_dp)->format('Y-m-d'),
            'created_at' => optional($spr->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($spr->updated_at)->format('d/m/Y H:i'),
            'alasan_batal' => $spr->alasan_batal,
            'refund_amount' => (float) ($spr->refund_amount ?? 0),
            'refund_at' => optional($spr->refund_at)->format('Y-m-d'),
            'refundable_paid' => $bookingPaid + $dpPaid + $otherPaid,
            'payments' => $spr->payments->sortByDesc('tanggal_pembayaran')->values()->map(fn (SprPayment $payment) => [
                'id' => $payment->id,
                'jenis_pembayaran' => $payment->jenis_pembayaran,
                'tanggal_pembayaran' => optional($payment->tanggal_pembayaran)->format('Y-m-d'),
                'nominal' => $payment->nominal,
                'bank' => $payment->masterBank?->nama_bank.' - '.($payment->masterBank?->nomor_rekening ?? '-'),
                'bukti_url' => $payment->bukti_pembayaran ? route('media', ['path' => $payment->bukti_pembayaran], false) : null,
                'keterangan' => $payment->keterangan,
                'status' => $payment->status ?? 'menunggu_konfirmasi',
                'status_label' => $this->paymentStatusLabel($payment->status ?? 'menunggu_konfirmasi'),
                'created_by' => $payment->creator?->name ?? '-',
                'created_at' => optional($payment->created_at)->format('d/m/Y H:i'),
                'updated_at' => optional($payment->updated_at)->format('d/m/Y H:i'),
                'confirmed_at' => optional($payment->confirmed_at)->format('d/m/Y H:i'),
                'confirmed_by' => $payment->confirmer?->name ?? '-',
                'confirmation_note' => $payment->confirmation_note,
            ])->all(),
        ];
    }

    protected function bankOptions(): array
    {
        return MasterBank::query()
            ->where('status', 'aktif')
            ->with('perumahan:id,nama_perusahaan')
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->orderBy('nama_bank')
            ->get(['id', 'perumahan_id', 'kode_bank', 'nama_bank', 'nomor_rekening', 'nama_rekening'])
            ->map(fn (MasterBank $bank) => [
                'value' => (string) $bank->id,
                'label' => trim($bank->nama_bank.' - '.($bank->nomor_rekening ?? '-') .' - '.($bank->nama_rekening ?? '-')),
                'search' => strtolower(trim($bank->nama_bank.' '.$bank->nomor_rekening.' '.$bank->nama_rekening.' '.$bank->kode_bank)),
                'perumahan' => $bank->perumahan?->nama_perusahaan ?? '-',
            ])
            ->values()
            ->all();
    }

    protected function refundRow(Spr $spr, Request $request): array
    {
        $bookingPaid = $this->bookingPaid($spr);
        $dpPaid = $this->dpPaid($spr);
        $otherPaid = $this->otherPaid($spr);
        $refundable = $bookingPaid + $dpPaid + $otherPaid;
        $status = $spr->refund_status ?: 'belum_diajukan';

        return [
            'id' => $spr->id,
            'kode_spr' => $spr->kode_spr,
            'customer' => $spr->costumer?->nama ?? '-',
            'no_identitas' => $spr->costumer?->no_identitas ?? '-',
            'unit' => $spr->detailRumah ? trim(($spr->detailRumah->kode_nlok ?? '').' '.($spr->detailRumah->nomor_rumah ?? '')) : '-',
            'perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'booking_paid' => $bookingPaid,
            'dp_paid' => $dpPaid,
            'refundable_paid' => $refundable,
            'refund_amount' => (float) ($spr->refund_amount ?? 0),
            'refund_at' => optional($spr->refund_at)->format('Y-m-d'),
            'refund_status' => $status,
            'refund_status_label' => $this->refundStatusLabel($status),
            'alasan_batal' => $spr->alasan_batal,
            'refund_approval_note' => $spr->refund_approval_note,
            'can_request' => $this->canRequestRefund($request) && $refundable > 0 && ! in_array($status, ['menunggu_manager', 'menunggu_owner', 'disetujui'], true),
            'can_approve_manager' => ($spr->record_status ?? 'draft') === 'locked' && (bool) $request->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']) && $status === 'menunggu_manager',
            'can_approve_owner' => ($spr->record_status ?? 'draft') === 'locked' && (bool) $request->user()?->hasAnyRole(['owner', 'super_admin']) && $status === 'menunggu_owner',
            'can_reject' => ($spr->record_status ?? 'draft') === 'locked' && (bool) $request->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']) && in_array($status, ['menunggu_manager', 'menunggu_owner'], true),
        ];
    }

    protected function refundStatusLabel(string $status): string
    {
        return [
            'belum_diajukan' => 'Belum Diajukan',
            'menunggu_manager' => 'Menunggu Manajer',
            'menunggu_owner' => 'Menunggu Owner',
            'disetujui' => 'Disetujui Owner',
            'ditolak' => 'Ditolak',
        ][$status] ?? $status;
    }

    protected function appendApprovalNote(?string $oldNote, string $actor, ?string $note): string
    {
        $line = now()->format('d/m/Y H:i').' '.$actor.': '.($note ?: '-');

        return trim(($oldNote ? $oldNote."\n" : '').$line);
    }

    protected function resolveTipePost(string $nama, string $jenis = 'pemasukan'): TipePost
    {
        return TipePost::query()->firstOrCreate(
            ['nama_post' => $nama],
            [
                'jenis' => $jenis,
                'debit_account_id' => $jenis === 'pemasukan' ? ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id') : null,
                'credit_account_id' => $jenis === 'pengeluaran' ? ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id') : null,
                'status' => 'aktif',
                'is_system' => true,
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => auth()->id(),
            ],
        );
    }

    protected function syncDownPaymentState(Spr $spr): void
    {
        $dpPaid = $this->dpPaid($spr->fresh('payments'));
        $dpTarget = (float) ($spr->uang_muka ?? 0);

        if ($dpTarget <= 0) {
            return;
        }

        $spr->detailRumah?->update([
            'status_penjualan' => $dpPaid >= $dpTarget ? 'dp_lunas' : 'dp',
        ]);
    }

    protected function modelClass(): string
    {
        return SprPayment::class;
    }

    protected function canConfirmPayments(Request $request): bool
    {
        return (bool) $request->user()?->can('spr-payment.update')
            || $request->user()?->hasAnyRole(['owner', 'super_admin']);
    }

    protected function canAccessRefundPage(Request $request): bool
    {
        return (bool) $request->user()?->can('refund-spr.view')
            || $request->user()?->can('refund-spr.create')
            || $request->user()?->hasAnyRole(['manajer_pimpro', 'owner', 'super_admin']);
    }

    protected function canRequestRefund(Request $request): bool
    {
        return (bool) $request->user()?->can('refund-spr.create')
            || $request->user()?->hasAnyRole(['owner', 'super_admin']);
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

    protected function paymentStatusLabel(string $status): string
    {
        return [
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'dikonfirmasi' => 'Dikonfirmasi',
            'ditolak' => 'Ditolak',
        ][$status] ?? $status;
    }
}
