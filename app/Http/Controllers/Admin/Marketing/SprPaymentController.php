<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\DetailRumah;
use App\Models\MasterBank;
use App\Models\Spr;
use App\Models\SprPayment;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
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
    use HandlesCrudLock;

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

    public function storeBookingFee(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);
        $this->abortIfCurrentMarketingCannotAccessSpr($request, $spr);

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
                'master_bank_id' => $payment->master_bank_id,
                'tipe_post_id' => $tipePost->id,
                'tanggal' => $payment->tanggal_pembayaran,
                'nominal' => (float) $payment->nominal,
                'keterangan' => trim(ucwords(str_replace('_', ' ', $payment->jenis_pembayaran)).' SPR '.$spr->kode_spr.' - '.($payment->keterangan ?? '')),
                'user_id' => $request->user()?->id,
            ]);

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
        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR yang belum disetujui tidak perlu di-cancel dari pembayaran.');

        $validated = $request->validate([
            'alasan_batal' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_master_bank_id' => ['nullable', 'exists:master_banks,id'],
            'refund_at' => ['nullable', 'date'],
        ]);

        $refundAmount = (float) ($validated['refund_amount'] ?? 0);
        $paidTotal = $this->bookingPaid($spr) + $this->dpPaid($spr) + $this->otherPaid($spr);

        abort_if($refundAmount > $paidTotal, 422, 'Jumlah pengembalian tidak boleh lebih besar dari total pembayaran yang sudah dikonfirmasi.');
        abort_if($refundAmount > 0 && empty($validated['refund_master_bank_id']), 422, 'Pilih bank/kas untuk mencatat pengembalian dana.');
        abort_if($refundAmount > 0 && ! $spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pengembalian dana.');

        DB::transaction(function () use ($request, $spr, $validated, $leadStatus, $refundAmount) {
            $refundTransaction = null;

            if ($refundAmount > 0) {
                $tipePost = $this->resolveTipePost('Pengembalian Dana SPR', 'pengeluaran');
                $refundTransaction = TransaksiKeuangan::create([
                    'cabang_id' => $spr->detailRumah?->perumahan?->cabang_id,
                    'master_bank_id' => $validated['refund_master_bank_id'],
                    'tipe_post_id' => $tipePost->id,
                    'tanggal' => $validated['refund_at'] ?? now()->toDateString(),
                    'nominal' => $refundAmount,
                    'keterangan' => 'Pengembalian dana SPR '.$spr->kode_spr.' - '.$validated['alasan_batal'],
                    'user_id' => $request->user()?->id,
                ]);
            }

            $spr->update([
                'status' => Spr::STATUS_DITOLAK,
                'alasan_batal' => $validated['alasan_batal'],
                'refund_master_bank_id' => $refundAmount > 0 ? $validated['refund_master_bank_id'] : null,
                'refund_transaksi_keuangan_id' => $refundTransaction?->id,
                'refund_amount' => $refundAmount,
                'refund_at' => $refundAmount > 0 ? ($validated['refund_at'] ?? now()->toDateString()) : null,
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
        return (bool) $request->user()?->hasAnyRole(['admin', 'admin_keuangan', 'keuangan', 'owner', 'super_admin']);
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

    protected function paymentStatusLabel(string $status): string
    {
        return [
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'dikonfirmasi' => 'Dikonfirmasi',
            'ditolak' => 'Ditolak',
        ][$status] ?? $status;
    }
}
