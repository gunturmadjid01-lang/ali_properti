<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Perumahan;
use App\Models\UnitOwnership;
use App\Models\WaterBillingPeriod;
use App\Models\WaterPayment;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WaterBillingController extends Controller
{
    public function periods(Request $request): Response
    {
        $this->authorizePermission('water-billing-periods.view');
        $housingId = $request->string('perumahan_id')->toString();
        $rows = WaterBillingPeriod::with(['perumahan:id,nama_perusahaan', 'creator:id,name'])
            ->withCount('payments')->withSum(['payments as paid_total' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->when($housingId, fn ($q) => $q->where('perumahan_id', $housingId))->latest('period_start')->paginate(15)->withQueryString()
            ->through(fn ($row) => $this->periodRow($row));

        return Inertia::render('Admin/WaterBilling/Periods/Index', ['title' => 'Periode Tagihan Air', 'rows' => $rows, 'filters' => ['perumahan_id' => $housingId], 'housing' => $this->housingOptions(), 'permissions' => ['create' => $this->allowed('water-billing-periods.create')]]);
    }

    public function createPeriod(): Response
    {
        $this->authorizePermission('water-billing-periods.create');
        return Inertia::render('Admin/WaterBilling/Periods/Form', ['title' => 'Tambah Periode Tagihan Air', 'actionUrl' => route('admin.water-periods.store', absolute: false), 'method' => 'post', 'housing' => $this->housingOptions(), 'row' => null]);
    }

    public function editPeriod(WaterBillingPeriod $period): Response
    {
        $this->authorizePermission('water-billing-periods.update');
        abort_unless($period->record_status === 'draft', 422, 'Periode yang sudah dikunci tidak dapat diubah.');
        return Inertia::render('Admin/WaterBilling/Periods/Form', ['title' => 'Ubah Periode Tagihan Air', 'actionUrl' => route('admin.water-periods.update', $period, false), 'method' => 'put', 'housing' => $this->housingOptions(), 'row' => $period->only(['perumahan_id', 'period_name', 'period_start', 'period_end', 'due_date', 'amount', 'is_active'])]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $this->authorizePermission('water-billing-periods.create');
        $data = $this->periodData($request);
        $number = str_pad((string) ((WaterBillingPeriod::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        WaterBillingPeriod::create([...$data, 'period_code' => 'AIR-PER-'.$number, 'record_status' => 'draft', 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        return redirect()->route('admin.water-periods.index')->with('success', 'Periode tagihan air berhasil disimpan sebagai draf.');
    }

    public function updatePeriod(Request $request, WaterBillingPeriod $period): RedirectResponse
    {
        $this->authorizePermission('water-billing-periods.update');
        abort_unless($period->record_status === 'draft', 422);
        $period->update([...$this->periodData($request), 'updated_by' => auth()->id()]);
        return redirect()->route('admin.water-periods.index')->with('success', 'Periode tagihan air diperbarui.');
    }

    public function payments(Request $request): Response
    {
        $this->authorizePermission('water-payments.view');
        $housingId = $request->string('perumahan_id')->toString();
        $status = $request->string('status')->toString();
        $query = WaterPayment::with(['period:id,period_name,due_date', 'ownership:id,owner_name', 'perumahan:id,nama_perusahaan', 'unit:id,kode_nlok,nomor_rumah', 'creator:id,name'])
            ->when($housingId, fn ($q) => $q->where('perumahan_id', $housingId))->when($status, fn ($q) => $q->where('status', $status));
        $summaryBase = clone $query;
        $summary = ['total' => (clone $summaryBase)->count(), 'paid' => (clone $summaryBase)->where('status', 'paid')->count(), 'unpaid' => (clone $summaryBase)->where('status', 'unpaid')->count(), 'pending' => (clone $summaryBase)->where('status', 'pending_approval')->count(), 'amount' => (float) (clone $summaryBase)->where('status', 'paid')->sum('amount')];
        $chart = collect(['paid' => 'Lunas', 'unpaid' => 'Belum Bayar', 'pending_approval' => 'Menunggu Persetujuan', 'draft' => 'Draf Pembayaran', 'rejected' => 'Ditolak'])->map(fn ($label, $key) => ['label' => $label, 'value' => (clone $summaryBase)->where('status', $key)->count()])->values();
        $rows = $query->latest('payment_date')->paginate(15)->withQueryString()->through(fn ($row) => $this->paymentRow($row));
        return Inertia::render('Admin/WaterBilling/Payments/Index', ['title' => 'Pembayaran Air', 'rows' => $rows, 'summary' => $summary, 'chart' => $chart, 'filters' => ['perumahan_id' => $housingId, 'status' => $status], 'housing' => $this->housingOptions(), 'permissions' => ['create' => $this->allowed('water-payments.create')]]);
    }

    public function createPayment(): Response
    {
        $this->authorizePermission('water-payments.create');
        return Inertia::render('Admin/WaterBilling/Payments/Form', ['title' => 'Input Pembayaran Air', 'actionUrl' => route('admin.water-payments.store', absolute: false), 'housing' => $this->housingOptions(), 'periods' => $this->periodOptions(), 'owners' => $this->ownerOptions()]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $this->authorizePermission('water-payments.create');
        $data = $request->validate(['perumahan_id' => ['required', 'exists:perumahans,id'], 'water_billing_period_id' => ['required', Rule::exists('water_billing_periods', 'id')->where(fn ($q) => $q->where('perumahan_id', $request->perumahan_id)->where('is_active', true)->where('record_status', 'locked'))], 'unit_ownership_id' => ['required', Rule::exists('unit_ownerships', 'id')->where(fn ($q) => $q->where('is_active', true))], 'payment_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:1'], 'payment_method' => ['required', Rule::in(['cash', 'transfer', 'qris'])], 'reference_no' => ['nullable', 'string', 'max:100'], 'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'], 'notes' => ['nullable', 'string']]);
        $owner = UnitOwnership::with('detailRumah')->findOrFail($data['unit_ownership_id']);
        abort_unless((string) $owner->detailRumah?->perumahan_id === (string) $data['perumahan_id'], 422, 'Pemilik unit tidak sesuai dengan perumahan yang dipilih.');
        $period = WaterBillingPeriod::findOrFail($data['water_billing_period_id']);
        abort_unless(abs((float) $period->amount - (float) $data['amount']) < 0.01, 422, 'Nominal pembayaran harus sama dengan nominal periode tagihan.');
        $proof = $request->file('proof')?->store('water-payments', 'public');
        $existing = WaterPayment::where('water_billing_period_id', $data['water_billing_period_id'])->where('unit_ownership_id', $owner->id)->first();
        abort_if($existing && in_array($existing->status, ['paid', 'pending_approval'], true), 422, 'Pembayaran pemilik untuk periode ini sudah diproses.');
        $number = str_pad((string) ((WaterPayment::withTrashed()->max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);
        WaterPayment::updateOrCreate(['water_billing_period_id' => $data['water_billing_period_id'], 'unit_ownership_id' => $owner->id], [...$data, 'detail_rumah_id' => $owner->detail_rumah_id, 'payment_no' => $existing?->payment_no ?? 'AIR-BYR-'.$number, 'proof_path' => $proof ?? $existing?->proof_path, 'status' => 'draft', 'record_status' => 'draft', 'created_by' => $existing?->created_by ?? auth()->id(), 'updated_by' => auth()->id()]);
        return redirect()->route('admin.water-payments.index')->with('success', 'Pembayaran air berhasil disimpan sebagai draf.');
    }

    public function lockPeriod(WaterBillingPeriod $period, ApprovalWorkflowService $workflow): RedirectResponse { $this->authorizePermission('water-billing-periods.lock'); return $this->lock($period, 'water-billing-period', $workflow); }
    public function unlockPeriod(WaterBillingPeriod $period, ApprovalWorkflowService $workflow): RedirectResponse { $this->authorizePermission('water-billing-periods.unlock'); return $this->unlock($period, $workflow); }
    public function lockPayment(WaterPayment $payment, ApprovalWorkflowService $workflow): RedirectResponse { $this->authorizePermission('water-payments.lock'); abort_unless($payment->payment_date && $payment->payment_method, 422, 'Lengkapi data pembayaran sebelum finalisasi.'); $payment->update(['status' => 'pending_approval']); return $this->lock($payment, 'water-payment', $workflow); }
    public function unlockPayment(WaterPayment $payment, ApprovalWorkflowService $workflow): RedirectResponse { $this->authorizePermission('water-payments.unlock'); $payment->update(['status' => 'draft']); return $this->unlock($payment, $workflow); }

    public function approve(string $type, int $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $model = $type === 'period' ? WaterBillingPeriod::findOrFail($id) : WaterPayment::findOrFail($id);
        $approval = ApprovalRequest::where('model_type', $model::class)->where('model_id', $model->id)->where('status', 'pending')->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403); $workflow->approve($approval);
        return back()->with('success', 'Persetujuan berhasil diproses.');
    }

    public function reject(Request $request, string $type, int $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $model = $type === 'period' ? WaterBillingPeriod::findOrFail($id) : WaterPayment::findOrFail($id);
        $approval = ApprovalRequest::where('model_type', $model::class)->where('model_id', $model->id)->where('status', 'pending')->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403); $workflow->reject($approval, $request->string('note')->toString());
        return back()->with('success', 'Data ditolak dan dikembalikan ke draf.');
    }

    private function lock($model, string $key, ApprovalWorkflowService $workflow): RedirectResponse { abort_unless($model->record_status === 'draft', 422); $model->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id()]); $workflow->submitLocked($model, $key); return back()->with('success', 'Data dikunci dan diajukan untuk persetujuan.'); }
    private function unlock($model, ApprovalWorkflowService $workflow): RedirectResponse { abort_unless($model->record_status === 'locked', 422); $workflow->cancelPendingLock($model); $model->update(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null]); return back()->with('success', 'Data dibuka kembali.'); }
    private function periodData(Request $request): array { return $request->validate(['perumahan_id' => ['required', 'exists:perumahans,id'], 'period_name' => ['required', 'string', 'max:100'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'due_date' => ['required', 'date', 'after_or_equal:period_start'], 'amount' => ['required', 'numeric', 'min:1'], 'is_active' => ['required', 'boolean']]); }
    private function housingOptions() { return Perumahan::finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_perusahaan]); }
    private function periodOptions() { return WaterBillingPeriod::where('is_active', true)->where('record_status', 'locked')->orderByDesc('period_start')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->period_name.' — Rp '.number_format($x->amount, 0, ',', '.'), 'perumahan_id' => (string) $x->perumahan_id, 'amount' => (float) $x->amount]); }
    private function ownerOptions() { return UnitOwnership::with('detailRumah.perumahan')->where('is_active', true)->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->owner_name.' — '.($x->detailRumah?->perumahan?->nama_perusahaan ?? '-').' / Blok '.$x->detailRumah?->kode_nlok.' No. '.$x->detailRumah?->nomor_rumah, 'perumahan_id' => (string) $x->detailRumah?->perumahan_id]); }
    private function latestApproval($row) { return ApprovalRequest::where('model_type', $row::class)->where('model_id', $row->id)->latest()->first(); }
    private function periodRow($x): array { $a = $this->latestApproval($x); return ['id' => $x->id, 'code' => $x->period_code, 'name' => $x->period_name, 'housing' => $x->perumahan?->nama_perusahaan, 'start' => $x->period_start?->format('d/m/Y'), 'end' => $x->period_end?->format('d/m/Y'), 'due' => $x->due_date?->format('d/m/Y'), 'amount' => (float) $x->amount, 'active' => $x->is_active, 'payments' => $x->payments_count, 'paid_total' => (float) $x->paid_total, 'record_status' => $x->record_status, 'approval' => $a?->status === 'pending' ? "Tahap {$a->current_step}/{$a->total_steps}" : ($a?->status ?? 'Belum diajukan'), 'can_review' => $a ? app(ApprovalWorkflowService::class)->canReview($a) : false, 'can_edit' => $x->record_status === 'draft', 'can_lock' => $x->record_status === 'draft', 'can_unlock' => $x->record_status === 'locked' && $a?->status === 'pending']; }
    private function paymentRow($x): array { $a = $this->latestApproval($x); return ['id' => $x->id, 'number' => $x->payment_no, 'owner' => $x->ownership?->owner_name, 'unit' => 'Blok '.$x->unit?->kode_nlok.' No. '.$x->unit?->nomor_rumah, 'housing' => $x->perumahan?->nama_perusahaan, 'period' => $x->period?->period_name, 'date' => $x->payment_date?->format('d/m/Y'), 'amount' => (float) $x->amount, 'method' => $x->payment_method, 'reference' => $x->reference_no, 'status' => $x->status, 'record_status' => $x->record_status, 'approval' => $a?->status === 'pending' ? "Tahap {$a->current_step}/{$a->total_steps}" : ($a?->status ?? 'Belum diajukan'), 'can_review' => $a ? app(ApprovalWorkflowService::class)->canReview($a) : false, 'can_lock' => $x->record_status === 'draft' && $x->status === 'draft', 'can_unlock' => $x->record_status === 'locked' && $x->status !== 'paid']; }
    private function allowed(string $permission): bool { return auth()->user()?->hasRole('super_admin') || auth()->user()?->can($permission); }
    private function authorizePermission(string $permission): void { abort_unless($this->allowed($permission), 403); }
}
