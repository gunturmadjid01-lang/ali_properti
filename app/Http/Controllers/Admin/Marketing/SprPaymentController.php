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
            ])
            ->where('status', Spr::STATUS_DISETUJUI)
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

        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui.');

        $validated = $request->validate([
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['required', 'file', 'image', 'max:4096'],
        ]);

        abort_unless($spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');

        DB::transaction(function () use ($request, $spr, $validated, $leadStatus) {
            $bukti = $request->file('bukti_pembayaran')?->store('spr-payments', 'public');
            $payment = SprPayment::create([
                'spr_id' => $spr->id,
                'master_bank_id' => $validated['master_bank_id'],
                'created_by' => $request->user()?->id,
                'jenis_pembayaran' => 'booking_fee',
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'bukti_pembayaran' => $bukti,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            $tipePost = $this->resolveTipePost('Booking Fee SPR');
            $transaksiKeuangan = TransaksiKeuangan::create([
                'cabang_id' => $spr->detailRumah?->perumahan?->cabang_id,
                'master_bank_id' => $validated['master_bank_id'],
                'tipe_post_id' => $tipePost->id,
                'tanggal' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'keterangan' => trim('Booking Fee SPR '.$spr->kode_spr.' - '.($validated['keterangan'] ?? '')),
                'user_id' => $request->user()?->id,
            ]);

            $payment->update(['transaksi_keuangan_id' => $transaksiKeuangan->id]);
            $spr->detailRumah?->update(['status_penjualan' => 'booking']);
            $leadStatus->markSpr($spr, MarketingLeadStatusService::BOOKING_FEE);
            app(MarketingOperationsService::class)->syncBillingSchedules($spr->fresh(['payments']));
        });

        return back()->with('success', 'Pembayaran Booking Fee berhasil disimpan.');
    }

    public function storeDownPayment(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);

        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui.');

        $validated = $request->validate([
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['required', 'file', 'image', 'max:4096'],
        ]);

        abort_unless($spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');

        DB::transaction(function () use ($request, $spr, $validated, $leadStatus) {
            $bukti = $request->file('bukti_pembayaran')?->store('spr-payments', 'public');
            $payment = SprPayment::create([
                'spr_id' => $spr->id,
                'master_bank_id' => $validated['master_bank_id'],
                'created_by' => $request->user()?->id,
                'jenis_pembayaran' => 'uang_muka',
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'bukti_pembayaran' => $bukti,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            $tipePost = $this->resolveTipePost('Uang Muka SPR');
            $transaksiKeuangan = TransaksiKeuangan::create([
                'cabang_id' => $spr->detailRumah?->perumahan?->cabang_id,
                'master_bank_id' => $validated['master_bank_id'],
                'tipe_post_id' => $tipePost->id,
                'tanggal' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'keterangan' => trim('Uang Muka SPR '.$spr->kode_spr.' - '.($validated['keterangan'] ?? '')),
                'user_id' => $request->user()?->id,
            ]);

            $payment->update(['transaksi_keuangan_id' => $transaksiKeuangan->id]);
            $this->syncDownPaymentState($spr);
            $leadStatus->markSpr($spr, MarketingLeadStatusService::CLOSING);
            app(MarketingOperationsService::class)->syncBillingSchedules($spr->fresh(['payments']));
        });

        return back()->with('success', 'Pembayaran Uang Muka berhasil disimpan.');
    }

    public function storeOtherPayment(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()
            ->with(['detailRumah.perumahan.cabang', 'payments'])
            ->findOrFail($sprId);

        abort_unless($spr->status === Spr::STATUS_DISETUJUI, 422, 'SPR harus sudah disetujui.');

        $validated = $request->validate([
            'master_bank_id' => ['required', 'exists:master_banks,id'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string'],
            'bukti_pembayaran' => ['required', 'file', 'image', 'max:4096'],
        ]);

        abort_unless($spr->detailRumah?->perumahan?->cabang_id, 422, 'Perumahan harus memiliki cabang untuk mencatat pembayaran.');

        DB::transaction(function () use ($request, $spr, $validated, $leadStatus) {
            $bukti = $request->file('bukti_pembayaran')?->store('spr-payments', 'public');
            $payment = SprPayment::create([
                'spr_id' => $spr->id,
                'master_bank_id' => $validated['master_bank_id'],
                'created_by' => $request->user()?->id,
                'jenis_pembayaran' => 'lainnya',
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'bukti_pembayaran' => $bukti,
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            $tipePost = $this->resolveTipePost('Pembayaran Lainnya SPR');
            $transaksiKeuangan = TransaksiKeuangan::create([
                'cabang_id' => $spr->detailRumah?->perumahan?->cabang_id,
                'master_bank_id' => $validated['master_bank_id'],
                'tipe_post_id' => $tipePost->id,
                'tanggal' => $validated['tanggal_pembayaran'],
                'nominal' => (float) $validated['nominal'],
                'keterangan' => trim('Pembayaran Lainnya SPR '.$spr->kode_spr.' - '.($validated['keterangan'] ?? '')),
                'user_id' => $request->user()?->id,
            ]);

            $payment->update(['transaksi_keuangan_id' => $transaksiKeuangan->id]);
            $leadStatus->markSpr($spr, MarketingLeadStatusService::CLOSING);
            app(MarketingOperationsService::class)->syncBillingSchedules($spr->fresh(['payments']));
        });

        return back()->with('success', 'Pembayaran lainnya berhasil disimpan.');
    }

    public function cancelSpr(Request $request, string $sprId): RedirectResponse
    {
        $leadStatus = app(MarketingLeadStatusService::class);
        $spr = Spr::query()->with('detailRumah')->findOrFail($sprId);
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
        return max(0, (float) ($spr->booking_fee ?? 0) - $this->bookingPaid($spr));
    }

    protected function bookingPaid(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'booking_fee')->sum('nominal');
    }

    protected function dpPaid(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'uang_muka')->sum('nominal');
    }

    protected function otherPaid(Spr $spr): float
    {
        return (float) $spr->payments->where('jenis_pembayaran', 'lainnya')->sum('nominal');
    }

    protected function dpRemaining(Spr $spr): float
    {
        return max(0, (float) ($spr->uang_muka ?? 0) - $this->dpPaid($spr));
    }

    protected function paymentRow(Spr $spr): array
    {
        $bookingPaid = $this->bookingPaid($spr);
        $dpPaid = $this->dpPaid($spr);
        $otherPaid = $this->otherPaid($spr);
        $bookingRemaining = $this->bookingRemaining($spr);
        $dpRemaining = $this->dpRemaining($spr);

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
            'other_paid' => $otherPaid,
            'booking_status' => $bookingRemaining <= 0 ? 'Lunas' : 'Belum Bayar',
            'dp_status' => $dpRemaining <= 0 ? 'DP Lunas' : 'DP',
            'other_status' => $otherPaid > 0 ? 'Ada Pembayaran' : 'Belum Ada',
            'booking_fee_includes_dp' => (bool) ($spr->booking_fee_includes_dp ?? false),
            'tanggal_jatuh_tempo_dp' => optional($spr->tanggal_jatuh_tempo_dp)->format('Y-m-d'),
            'alasan_batal' => $spr->alasan_batal,
            'payments' => $spr->payments->sortByDesc('tanggal_pembayaran')->values()->map(fn (SprPayment $payment) => [
                'id' => $payment->id,
                'jenis_pembayaran' => $payment->jenis_pembayaran,
                'tanggal_pembayaran' => optional($payment->tanggal_pembayaran)->format('Y-m-d'),
                'nominal' => $payment->nominal,
                'bank' => $payment->masterBank?->nama_bank.' - '.($payment->masterBank?->nomor_rekening ?? '-'),
                'bukti_url' => $payment->bukti_pembayaran ? route('media', ['path' => $payment->bukti_pembayaran], false) : null,
                'keterangan' => $payment->keterangan,
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

    protected function resolveTipePost(string $nama): TipePost
    {
        return TipePost::query()->firstOrCreate(
            ['nama_post' => $nama],
            [
                'jenis' => 'pemasukan',
                'debit_account_id' => ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->value('id'),
                'credit_account_id' => null,
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
}
