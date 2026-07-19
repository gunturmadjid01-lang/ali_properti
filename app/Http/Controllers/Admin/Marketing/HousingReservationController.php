<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\HousingReservation;
use App\Models\CashInstallmentScheme;
use App\Models\DeveloperKprProduct;
use App\Models\BankCreditProduct;
use App\Models\MasterBank;
use App\Models\PettyCashAccount;
use App\Models\ApprovalRequest;
use App\Services\HousingReservationService;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class HousingReservationController extends Controller
{
    use ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('housing-reservation.view') || $request->user()?->hasRole('super_admin'), 403);
        $year = (int) $request->integer('year', now()->year);
        $month = $request->filled('month') ? (int) $request->integer('month') : null;
        $query = HousingReservation::query()->with(['customer', 'unit.perumahan', 'creator', 'spr:id,kode_spr,status', 'latestApproval', 'paymentSchedule'])
            ->where(fn ($q) => $q->where('record_status', 'locked')->orWhere('created_by', $request->user()->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn ($q) => $q->whereHas('unit', fn ($unit) => $this->scopeToActivePerumahan($unit, $request)));
        $period = HousingReservation::query()
            ->where(fn ($q) => $q->where('record_status', 'locked')->orWhere('created_by', $request->user()->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn ($q) => $q->whereHas('unit', fn ($unit) => $this->scopeToActivePerumahan($unit, $request)))
            ->whereYear('reserved_at', $year);
        if ($month) {
            $query->whereYear('reserved_at', $year)->whereMonth('reserved_at', $month);
            $period->whereMonth('reserved_at', $month);
        } else {
            $query->whereYear('reserved_at', $year);
        }
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('payment_status')) $query->where('payment_status', $request->input('payment_status'));
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('reservation_no', 'like', $search)->orWhere('invoice_no', 'like', $search)->orWhereHas('customer', fn ($c) => $c->where('nama', 'like', $search)));
        }
        $chartRows = (clone $period)->selectRaw(($month ? 'DAY(reserved_at)' : 'MONTH(reserved_at)').' as bucket, COUNT(*) as total, SUM(booking_fee) as billed, SUM(paid_amount) as paid')->groupBy('bucket')->get()->keyBy('bucket');
        $chart = collect(range(1, $month ? now()->setYear($year)->setMonth($month)->daysInMonth : 12))->map(function ($bucket) use ($chartRows, $month) {
            $value = $chartRows->get($bucket);
            return ['label' => $month ? (string) $bucket : now()->setMonth($bucket)->translatedFormat('M'), 'total' => (int) ($value?->total ?? 0), 'billed' => (float) ($value?->billed ?? 0), 'paid' => (float) ($value?->paid ?? 0)];
        });
        return Inertia::render('Admin/Marketing/Reservations/Index', [
            'title' => 'Reservasi Perumahan',
            'rows' => $query->latest('reserved_at')->paginate(15)->withQueryString()->through(function ($row) use ($request) {
                $ownDraft = $row->record_status === 'draft' && $row->created_by === $request->user()->id;
                $canCancel = $ownDraft && ! $row->spr_id && ! in_array($row->status, ['completed', 'cancelled', 'customer_cancelled', 'expired'], true);
                return [...$row->toArray(),
                    'can_edit' => $ownDraft && ($request->user()->can('housing-reservation.update') || $request->user()->hasRole('super_admin')),
                    'can_delete' => $ownDraft && ($request->user()->can('housing-reservation.delete') || $request->user()->hasRole('super_admin')),
                    'can_lock' => $ownDraft && ($request->user()->can('housing-reservation.lock') || $request->user()->hasRole('super_admin')),
                    'can_cancel' => $canCancel && ($request->user()->can('housing-reservation.update') || $request->user()->hasRole('super_admin')),
                    'approval_status' => $row->latestApproval?->status,
                    'approval_id' => $row->latestApproval?->id,
                    'approval_current_step' => $row->latestApproval?->current_step,
                    'approval_total_steps' => $row->latestApproval?->total_steps,
                    'can_review' => $row->latestApproval?->status === 'pending' && app(ApprovalWorkflowService::class)->canReview($row->latestApproval),
                    'show_url' => route('admin.marketing.reservations.show', $row, false), 'edit_url' => route('admin.marketing.reservations.edit', $row, false),
                    'invoice_url' => $row->paymentSchedule && ($request->user()->can('housing-reservation.print') || $request->user()->hasRole('super_admin')) ? route('admin.marketing.reservations.invoice', $row, false) : null];
            }),
            'filters' => ['year' => $year, 'month' => $month, 'status' => $request->input('status'), 'payment_status' => $request->input('payment_status'), 'search' => $request->input('search')],
            'statistics' => ['total' => (clone $period)->count(), 'active' => (clone $period)->whereNotIn('status', ['cancelled', 'customer_cancelled', 'expired', 'completed'])->count(), 'completed' => (clone $period)->where('status', 'completed')->count(), 'cancelled' => (clone $period)->whereIn('status', ['cancelled', 'customer_cancelled', 'expired'])->count(), 'billed' => (float) (clone $period)->sum('booking_fee'), 'paid' => (float) (clone $period)->sum('paid_amount')],
            'chart' => $chart,
            'years' => HousingReservation::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn ($q) => $q->whereHas('unit', fn ($unit) => $this->scopeToActivePerumahan($unit, $request)))
                ->selectRaw('YEAR(reserved_at) as year')->distinct()->orderByDesc('year')->pluck('year')->filter()->values(),
            'canCreate' => $request->user()?->can('housing-reservation.create') || $request->user()?->hasRole('super_admin'),
            'canManage' => $request->user()?->can('housing-reservation.update') || $request->user()?->hasRole('super_admin'),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('housing-reservation.create') || $request->user()?->hasRole('super_admin'), 403);
        return Inertia::render('Admin/Marketing/Reservations/Create', $this->formProps($request, null) + [
            'title' => 'Buat Reservasi Perumahan',
        ]);
    }

    public function store(Request $request, HousingReservationService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('housing-reservation.create') || $request->user()?->hasRole('super_admin'), 403);
        $data = $this->prepareReservationData($request, $this->validatedReservation($request, true));
        $service->create($data);
        return to_route('admin.marketing.reservations.index')->with('success', 'Reservasi dan penerimaan Booking Fee disimpan sebagai draft privat. Lock untuk mengajukan verifikasi Keuangan.');
    }

    public function edit(Request $request, HousingReservation $reservation): Response
    {
        abort_unless($request->user()?->can('housing-reservation.update') || $request->user()?->hasRole('super_admin'), 403);
        $this->assertDraftOwner($request, $reservation);
        return Inertia::render('Admin/Marketing/Reservations/Create', $this->formProps($request, $reservation) + ['title' => 'Edit Reservasi Perumahan']);
    }

    public function update(Request $request, HousingReservation $reservation, HousingReservationService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('housing-reservation.update') || $request->user()?->hasRole('super_admin'), 403);
        $this->assertDraftOwner($request, $reservation);
        $service->updateDraft($reservation, $this->prepareReservationData($request, $this->validatedReservation($request, false), $reservation));
        return to_route('admin.marketing.reservations.index')->with('success', 'Draft reservasi diperbarui.');
    }

    public function destroy(Request $request, HousingReservation $reservation): RedirectResponse
    {
        abort_unless($request->user()?->can('housing-reservation.delete') || $request->user()?->hasRole('super_admin'), 403);
        $this->assertDraftOwner($request, $reservation);
        $reservation->delete();
        return back()->with('success', 'Draft reservasi dihapus.');
    }

    public function lock(Request $request, HousingReservation $reservation, HousingReservationService $service, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()?->can('housing-reservation.lock') || $request->user()?->hasRole('super_admin'), 403);
        $this->assertDraftOwner($request, $reservation);
        $row = $service->lock($reservation);
        $approval = $workflow->submitLocked($row, 'housing-reservation');
        if ($row->payment_method === 'cash' && $approval->status === ApprovalRequest::STATUS_PENDING) {
            $workflow->skipCurrentStep($approval, 'Metode pembayaran Cash tidak memerlukan verifikasi transaksi Keuangan.');
            $approval->refresh();
        }
        return back()->with('success', $approval->status === 'approved'
            ? 'Reservasi dan Booking Fee disetujui serta langsung dibukukan sesuai lokasi dana.'
            : ($row->payment_method === 'cash'
                ? "Reservasi Cash tidak memerlukan verifikasi Keuangan dan diteruskan ke approval tahap {$approval->current_step}/{$approval->total_steps}."
                : "Reservasi dan Booking Fee dikunci lalu masuk verifikasi transaksi Keuangan tahap {$approval->current_step}/{$approval->total_steps}."));
    }

    public function show(Request $request, HousingReservation $reservation): Response
    {
        $this->assertVisible($request, $reservation);
        $reservation->load(['customer', 'unit.perumahan', 'creator', 'canceller', 'paymentSchedule', 'latestApproval', 'spr:id,kode_spr,status', 'fundBank', 'pettyCashAccount']);
        $approval = ApprovalRequest::query()
            ->where(['module_key' => 'housing-reservation', 'model_type' => HousingReservation::class, 'model_id' => $reservation->id])
            ->latest('id')
            ->first();

        return Inertia::render('Admin/Marketing/Reservations/Show', [
            'title' => 'Detail '.$reservation->reservation_no,
            'row' => $reservation,
            'approval' => $approval,
            'invoiceUrl' => $reservation->paymentSchedule ? route('admin.marketing.reservations.invoice', $reservation, false) : null,
        ]);
    }

    public function invoice(Request $request, HousingReservation $reservation): Response
    {
        abort_unless($request->user()?->can('housing-reservation.print') || $request->user()?->hasRole('super_admin'), 403);
        $this->assertVisible($request, $reservation);
        abort_unless($reservation->record_status === 'locked' && $reservation->paymentSchedule()->exists(), 404);
        $reservation->load(['customer', 'unit.perumahan', 'creator', 'paymentSchedule', 'spr:id,kode_spr,status', 'fundBank', 'pettyCashAccount']);
        return Inertia::render('Admin/Marketing/Reservations/Invoice', ['title' => 'Invoice '.$reservation->invoice_no, 'row' => $reservation]);
    }

    public function paymentProof(Request $request, HousingReservation $reservation)
    {
        $approval = $reservation->latestApproval;
        abort_unless($request->user()?->can('housing-reservation.view') || $request->user()?->hasRole('super_admin') || ($approval && app(ApprovalWorkflowService::class)->canReview($approval)), 403);
        abort_unless($reservation->payment_proof_path && Storage::disk('public')->exists($reservation->payment_proof_path), 404);
        return Storage::disk('public')->response($reservation->payment_proof_path, $reservation->payment_proof_original_name);
    }

    public function reviewPayment(Request $request, HousingReservation $reservation, ApprovalWorkflowService $workflow): Response
    {
        $approval = ApprovalRequest::query()->where(['module_key' => 'housing-reservation', 'model_type' => HousingReservation::class, 'model_id' => $reservation->id, 'status' => 'pending'])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        abort_unless($approval->current_step === 1 && $reservation->payment_method !== 'cash', 404);
        $reservation->load(['customer', 'unit.perumahan', 'creator', 'fundBank', 'pettyCashAccount']);
        return Inertia::render('Admin/Marketing/Reservations/PaymentReview', ['title' => 'Verifikasi Booking Fee '.$reservation->invoice_no, 'row' => $reservation, 'approval' => $approval, 'proofUrl' => route('admin.marketing.reservations.payment-proof', $reservation, false)]);
    }

    public function decidePayment(Request $request, HousingReservation $reservation, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        $approval = ApprovalRequest::query()->where(['module_key' => 'housing-reservation', 'model_type' => HousingReservation::class, 'model_id' => $reservation->id, 'status' => 'pending'])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        abort_unless($approval->current_step === 1 && $reservation->payment_method !== 'cash', 404);
        if ($decision === 'reject') {
            $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);
            return to_route('admin.customer-receipts.index')->with('success', 'Reservasi dan penerimaan Booking Fee ditolak. Draft dikembalikan kepada Marketing untuk diperbaiki.');
        }
        $data = $request->validate(['fund_received_at' => 'required|date|before_or_equal:now', 'settlement_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 'finance_verification_notes' => 'required|string|max:2000']);
        $proof = $request->file('settlement_proof')?->store('reservation-booking-fees/settlements/'.now()->format('Y/m'), 'public');
        $reservation->update(['fund_received_at' => $data['fund_received_at'], 'fund_received_by' => $request->user()?->id, 'settlement_proof_path' => $proof, 'settlement_proof_original_name' => $request->file('settlement_proof')?->getClientOriginalName(), 'finance_verification_notes' => $data['finance_verification_notes']]);
        $workflow->approve($approval);
        return to_route('admin.customer-receipts.index')->with('success', 'Reservasi dan Booking Fee disetujui, penerimaan dibukukan, dan jurnal dibuat.');
    }

    public function cancel(Request $request, HousingReservation $reservation, HousingReservationService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('housing-reservation.update') || $request->user()?->hasRole('super_admin'), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000', 'type' => 'required|in:customer,internal']);
        $service->cancel($reservation, $data['reason'], $data['type']);
        return back()->with('success', 'Reservasi dibatalkan dan unit kembali tersedia.');
    }

    private function assertDraftOwner(Request $request, HousingReservation $reservation): void
    {
        $this->ensurePerumahanAllowed($request, (int) $reservation->unit()->value('perumahan_id'));
        abort_unless($reservation->record_status === 'draft' && $reservation->created_by === $request->user()?->id, 403, 'Hanya penginput yang dapat mengubah draft yang belum dikunci.');
    }

    private function assertVisible(Request $request, HousingReservation $reservation): void
    {
        $this->ensurePerumahanAllowed($request, (int) $reservation->unit()->value('perumahan_id'));
        $approval = $reservation->latestApproval;
        $canReview = $approval && app(ApprovalWorkflowService::class)->canReview($approval);
        abort_unless($request->user()?->can('housing-reservation.view') || $request->user()?->hasRole('super_admin') || $canReview, 403);
        abort_if($reservation->record_status === 'draft' && $reservation->created_by !== $request->user()?->id, 403);
    }

    private function validatedReservation(Request $request, bool $creating): array
    {
        $proofRule = $creating || ! $request->route('reservation')?->payment_proof_path ? 'required' : 'nullable';

        return $request->validate([
            'costumer_id' => 'required|exists:costumers,id', 'detail_rumah_id' => 'required|exists:detail_rumahs,id',
            'payment_method' => 'required|in:cash,cash_bertahap,kpr_bank,kpr_developer',
            'booking_fee_source_id' => 'nullable|integer', 'booking_fee' => 'required|numeric|min:1',
            'payment_submitted_at' => 'required|date|before_or_equal:today',
            'payment_channel' => 'required|in:cash,transfer',
            'payment_sender_name' => 'required|string|max:150',
            'payment_bank_reference' => 'nullable|required_if:payment_channel,transfer|string|max:150',
            'fund_master_bank_id' => 'nullable|required_if:payment_channel,transfer|integer|exists:master_banks,id',
            'petty_cash_account_id' => 'nullable|required_if:payment_channel,cash|integer|exists:petty_cash_accounts,id',
            'payment_notes' => 'nullable|string|max:1000',
            'proof' => [$proofRule, 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
    }

    private function prepareReservationData(Request $request, array $data, ?HousingReservation $reservation = null): array
    {
        $unit = DetailRumah::query()->findOrFail($data['detail_rumah_id']);
        $this->ensurePerumahanAllowed($request, (int) $unit->perumahan_id);
        $source = match ($data['payment_method']) {
            'cash_bertahap' => CashInstallmentScheme::query()->where('status', 'aktif')->where('record_status', 'locked')->find($data['booking_fee_source_id'] ?? 0),
            'kpr_developer' => DeveloperKprProduct::query()->where('status', 'aktif')->where('record_status', 'locked')->find($data['booking_fee_source_id'] ?? 0),
            'kpr_bank' => BankCreditProduct::query()->where('status', 'aktif')->where('record_status', 'locked')->find($data['booking_fee_source_id'] ?? 0),
            default => null,
        };

        if ($source) {
            $configured = $source instanceof CashInstallmentScheme ? (float) $source->minimum_booking_fee : (float) data_get($source, 'fees.booking_fee', 0);
            if ($configured > 0) $data['booking_fee'] = $configured;
            $data['booking_fee_source_type'] = $source::class;
            $data['booking_fee_source_id'] = $source->id;
            $data['booking_fee_snapshot'] = ['label' => $source->name ?? $source->product_name, 'booking_fee' => $data['booking_fee']];
        } else {
            $data['booking_fee_source_type'] = null;
            $data['booking_fee_source_id'] = null;
            $data['booking_fee_snapshot'] = null;
        }

        if ($data['payment_channel'] === 'transfer') {
            $bank = MasterBank::query()->finalized()->where('status', 'aktif')->where('perumahan_id', $unit->perumahan_id)->find($data['fund_master_bank_id']);
            if (! $bank) {
                throw ValidationException::withMessages(['fund_master_bank_id' => 'Pilih rekening aktif dan sudah final milik perumahan unit tersebut.']);
            }
            $data['fund_destination_type'] = 'company_bank';
            $data['petty_cash_account_id'] = null;
            $data['fund_custody_status'] = 'awaiting_bank_verification';
        } else {
            $pettyCash = PettyCashAccount::query()->where('status', 'active')->where('assigned_user_id', $request->user()->id)->find($data['petty_cash_account_id']);
            if (! $pettyCash) {
                throw ValidationException::withMessages(['petty_cash_account_id' => 'Pilih Kas Kecil aktif yang ditugaskan kepada Marketing penginput.']);
            }
            $data['fund_destination_type'] = 'petty_cash';
            $data['fund_master_bank_id'] = null;
            $data['fund_custody_status'] = 'held_in_marketing_petty_cash';
        }

        if ($request->hasFile('proof')) {
            if ($reservation?->payment_proof_path) {
                Storage::disk('public')->delete($reservation->payment_proof_path);
            }
            $data['payment_proof_path'] = $request->file('proof')->store('reservation-booking-fees/'.now()->format('Y/m'), 'public');
            $data['payment_proof_original_name'] = $request->file('proof')->getClientOriginalName();
        }
        unset($data['proof']);

        return $data;
    }

    private function formProps(Request $request, ?HousingReservation $reservation): array
    {
        $units = DetailRumah::with('perumahan:id,nama_perusahaan')->where(function ($query) use ($reservation) {
            $query->whereIn('status_penjualan', ['tersedia', 'available']);
            if ($reservation) $query->orWhereKey($reservation->detail_rumah_id);
        });
        $this->scopeToActivePerumahan($units, $request);
        $units = $units->orderBy('perumahan_id')->orderBy('nomor_rumah')->get(['id','perumahan_id','kode_nlok','nomor_rumah','tipe_rumah','harga_jual','luas_tanah','luas_bangunan']);
        return [
            'row' => $reservation,
            'customers' => $this->scopeToActivePerumahan(Costumer::query(), $request)->orderBy('nama')->get(['id', 'nama', 'kode_costumer', 'telepon']),
            'units' => $units,
            'bookingSources' => [
                'cash_bertahap' => CashInstallmentScheme::query()->where('status', 'aktif')->where('record_status', 'locked')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->name, 'booking_fee' => (float) $row->minimum_booking_fee]),
                'kpr_developer' => DeveloperKprProduct::query()->where('status', 'aktif')->where('record_status', 'locked')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->name, 'booking_fee' => (float) data_get($row->fees, 'booking_fee', 0)]),
                'kpr_bank' => BankCreditProduct::query()->where('status', 'aktif')->where('record_status', 'locked')->get()->map(fn ($row) => ['id' => $row->id, 'label' => $row->product_name, 'booking_fee' => 0]),
                'cash' => [],
            ],
            'bankAccounts' => $this->scopeToActivePerumahan(MasterBank::query(), $request)->finalized()->where('status', 'aktif')->with('perumahan:id,nama_perusahaan')->orderBy('nama_bank')->get()->map(fn ($bank) => [
                'value' => (string) $bank->id,
                'perumahan_id' => (string) $bank->perumahan_id,
                'label' => $bank->nama_bank.' — '.($bank->nomor_rekening ?: 'Nomor rekening belum diisi').' — '.$bank->nama_rekening,
            ]),
            'pettyCashAccounts' => PettyCashAccount::query()->where('status', 'active')->where('assigned_user_id', auth()->id())->orderBy('name')->get()->map(fn ($account) => [
                'value' => (string) $account->id,
                'label' => $account->code.' — '.$account->name,
                'balance' => (float) $account->balance,
            ]),
        ];
    }
}
