<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\CustomerCharge;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\SalesTransaction;
use App\Services\ApprovalWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerChargeController extends Controller
{
    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission) || $request->user()?->hasRole('super_admin'), 403);
    }

    public function index(Request $request): Response
    {
        $this->allow($request, 'customer-charges.view');
        $filters = $request->validate([
            'search' => 'nullable|string|max:150', 'type' => 'nullable|in:additional_charge,customer_advance',
            'status' => 'nullable|in:draft,pending_approval,posted,rejected,reversed', 'category' => 'nullable|string|max:60',
            'perumahan_id' => 'nullable|integer|exists:perumahans,id', 'due_from' => 'nullable|date',
            'due_to' => 'nullable|date|after_or_equal:due_from', 'amount_min' => 'nullable|numeric|min:0', 'amount_max' => 'nullable|numeric|min:0',
        ]);
        $query = CustomerCharge::with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'bankAccount', 'creator', 'invoice', 'journal'])
            ->where(fn (Builder $query) => $query->where('record_status', 'locked')->orWhere('created_by', $request->user()?->id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->where('charge_no', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%")->orWhereHas('salesTransaction', fn (Builder $query) => $query->where('transaction_no', 'like', "%{$value}%"))->orWhereHas('salesTransaction.customer', fn (Builder $query) => $query->where('nama', 'like', "%{$value}%"))))
            ->when($filters['type'] ?? null, fn (Builder $query, string $value) => $query->where('charge_type', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->when($filters['perumahan_id'] ?? null, fn (Builder $query, int $value) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('perumahan_id', $value)))
            ->when($filters['due_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('due_date', '>=', $value))
            ->when($filters['due_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('due_date', '<=', $value))
            ->when($filters['amount_min'] ?? null, fn (Builder $query, $value) => $query->where('amount', '>=', $value))
            ->when($filters['amount_max'] ?? null, fn (Builder $query, $value) => $query->where('amount', '<=', $value));
        $summary = clone $query;
        $rows = $query->latest()->paginate(12)->withQueryString()->through(fn (CustomerCharge $row) => $this->row($row));

        return Inertia::render('Admin/CustomerCharges/Index', [
            'title' => 'Tagihan Tambahan & Talangan Customer', 'rows' => $rows, 'filters' => $filters,
            'summary' => ['count' => (clone $summary)->count(), 'total' => (float) (clone $summary)->whereNotIn('status', ['reversed', 'rejected'])->sum('amount'), 'advance' => (float) (clone $summary)->where('charge_type', 'customer_advance')->whereNotIn('status', ['reversed', 'rejected'])->sum('amount'), 'pending' => (float) (clone $summary)->whereIn('status', ['draft', 'pending_approval'])->sum('amount')],
            'options' => ['perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]), 'categories' => CustomerCharge::query()->finalized()->distinct()->orderBy('category')->pluck('category')],
            'permissions' => ['create' => $request->user()?->can('customer-charges.create') || $request->user()?->hasRole('super_admin')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->allow($request, 'customer-charges.create');

        return $this->form($request, new CustomerCharge(['charge_date' => today(), 'due_date' => today()->addDays(14), 'charge_type' => 'additional_charge']), 'post', route('admin.customer-charges.store', absolute: false));
    }

    public function edit(Request $request, CustomerCharge $charge): Response
    {
        $this->allow($request, 'customer-charges.update');
        abort_unless($charge->record_status === 'draft' && $charge->created_by === $request->user()?->id, 403);

        return $this->form($request, $charge, 'put', route('admin.customer-charges.update', $charge, absolute: false));
    }

    private function form(Request $request, CustomerCharge $charge, string $method, string $actionUrl): Response
    {
        $transactions = SalesTransaction::with(['customer', 'housingProject', 'housingUnit'])->where('status', 'active')->latest()->limit(500)->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->transaction_no} — {$row->customer?->nama} — {$row->housingProject?->nama_perusahaan} / {$row->housingUnit?->nomor_rumah}"]);
        $banks = MasterBank::with('perumahan')->where('status', 'aktif')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->nama_bank} — {$row->nomor_rekening} ({$row->perumahan?->nama_perusahaan})"]);

        return Inertia::render('Admin/CustomerCharges/Form', ['title' => $charge->exists ? 'Ubah Tagihan/Talangan Customer' : 'Tambah Tagihan/Talangan Customer', 'row' => $charge, 'method' => $method, 'actionUrl' => $actionUrl, 'transactions' => $transactions, 'banks' => $banks]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'customer-charges.create');
        $data = $this->validated($request);
        $path = $request->file('proof')?->store('customer-charges/'.now()->format('Y/m'), 'public');
        unset($data['proof']);
        CustomerCharge::create([...$data, 'charge_no' => 'CHG/'.now()->format('Y').'/'.str_pad((string) (CustomerCharge::withTrashed()->count() + 1), 7, '0', STR_PAD_LEFT), 'proof_path' => $path, 'proof_original_name' => $request->file('proof')?->getClientOriginalName(), 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);

        return to_route('admin.customer-charges.index')->with('success', 'Tagihan/talangan disimpan sebagai draf.');
    }

    public function update(Request $request, CustomerCharge $charge): RedirectResponse
    {
        $this->allow($request, 'customer-charges.update');
        abort_unless($charge->record_status === 'draft' && $charge->created_by === $request->user()?->id, 403);
        $data = $this->validated($request, $charge);
        if ($request->hasFile('proof')) {
            if ($charge->proof_path) {
                Storage::disk('public')->delete($charge->proof_path);
            }
            $data['proof_path'] = $request->file('proof')->store('customer-charges/'.now()->format('Y/m'), 'public');
            $data['proof_original_name'] = $request->file('proof')->getClientOriginalName();
        }
        unset($data['proof']);
        $charge->update([...$data, 'updated_by' => $request->user()?->id]);

        return to_route('admin.customer-charges.index')->with('success', 'Draf berhasil diperbarui.');
    }

    private function validated(Request $request, ?CustomerCharge $charge = null): array
    {
        return $request->validate([
            'sales_transaction_id' => 'required|exists:sales_transactions,id', 'charge_type' => ['required', Rule::in(['additional_charge', 'customer_advance'])],
            'category' => 'required|string|max:60', 'description' => 'required|string|max:255', 'amount' => 'required|numeric|min:1',
            'charge_date' => 'required|date', 'due_date' => 'required|date|after_or_equal:charge_date', 'master_bank_id' => 'nullable|exists:master_banks,id',
            'paid_to' => 'nullable|string|max:150', 'payment_reference' => 'nullable|string|max:100', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 'notes' => 'nullable|string|max:2000',
        ]);
    }

    public function lock(Request $request, CustomerCharge $charge, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'customer-charges.lock');
        abort_unless($charge->record_status === 'draft' && $charge->created_by === $request->user()?->id, 403);
        if ($charge->charge_type === 'customer_advance') {
            abort_unless($charge->master_bank_id && $charge->paid_to && $charge->proof_path, 422, 'Talangan wajib memiliki rekening sumber, penerima dana, dan bukti pembayaran.');
        }
        $charge->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()?->id]);
        $approval = $workflow->submitLocked($charge, 'customer-charge');

        return back()->with('success', $approval->status === 'approved' ? 'Tagihan/talangan disetujui dan diposting.' : "Masuk persetujuan tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function unlock(Request $request, CustomerCharge $charge, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'customer-charges.unlock');
        abort_if(in_array($charge->status, ['posted', 'reversed'], true), 422, 'Transaksi terposting tidak dapat dibuka. Gunakan reversal.');
        $workflow->cancelPendingLock($charge);
        $charge->update(['record_status' => 'draft', 'status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Transaksi dikembalikan menjadi draf privat pembuat.');
    }

    public function review(Request $request, CustomerCharge $charge, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::where('model_type', CustomerCharge::class)->where('model_id', $charge->id)->where('status', 'pending')->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);

        return back()->with('success', $decision === 'approve' ? 'Tahap persetujuan disetujui.' : 'Pengajuan ditolak.');
    }

    public function requestReversal(Request $request, CustomerCharge $charge, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'customer-charges.reverse');
        abort_unless($charge->status === 'posted' && in_array($charge->reversal_status, [null, 'rejected'], true), 422, 'Transaksi tidak dapat diajukan reversal.');
        abort_if((float) $charge->invoice?->paid_amount > 0, 422, 'Tagihan sudah memiliki pembayaran dan tidak dapat direversal langsung.');
        $charge->update(['reversal_reason' => $request->validate(['reason' => 'required|string|max:1000'])['reason'], 'reversal_status' => 'pending_approval']);
        $approval = $workflow->submitLocked($charge, 'customer-charge-reversal');

        return back()->with('success', $approval->status === 'approved' ? 'Reversal disetujui dan jurnal pembalik dibuat.' : "Reversal masuk persetujuan tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function preview(Request $request, CustomerCharge $charge): Response
    {
        $this->allow($request, 'customer-charges.print');
        $charge->load(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'bankAccount', 'invoice', 'journal']);

        return Inertia::render('Admin/CustomerCharges/Preview', ['title' => $charge->charge_no, 'row' => $this->row($charge)]);
    }

    public function proof(Request $request, CustomerCharge $charge)
    {
        $this->allow($request, 'customer-charges.view');
        abort_unless($charge->proof_path && Storage::disk('public')->exists($charge->proof_path), 404);

        return Storage::disk('public')->response($charge->proof_path, $charge->proof_original_name);
    }

    private function row(CustomerCharge $row): array
    {
        $approval = ApprovalRequest::where('model_type', CustomerCharge::class)->where('model_id', $row->id)->latest()->first();

        return ['id' => $row->id, 'charge_no' => $row->charge_no, 'type' => $row->charge_type, 'category' => $row->category, 'description' => $row->description, 'amount' => (float) $row->amount, 'charge_date' => optional($row->charge_date)->format('Y-m-d'), 'due_date' => optional($row->due_date)->format('Y-m-d'), 'status' => $row->status, 'record_status' => $row->record_status, 'reversal_status' => $row->reversal_status, 'reversal_reason' => $row->reversal_reason, 'transaction' => $row->salesTransaction?->transaction_no, 'customer' => $row->salesTransaction?->customer?->nama, 'housing' => $row->salesTransaction?->housingProject?->nama_perusahaan, 'unit' => $row->salesTransaction?->housingUnit?->nomor_rumah, 'paid_to' => $row->paid_to, 'bank' => $row->bankAccount?->nama_bank.' — '.$row->bankAccount?->nomor_rekening, 'payment_reference' => $row->payment_reference, 'notes' => $row->notes, 'invoice_no' => $row->invoice?->invoice_no, 'invoice_remaining' => $row->invoice ? max(0, (float) $row->invoice->amount - (float) $row->invoice->paid_amount) : null, 'journal_no' => $row->journal?->nomor_jurnal, 'creator' => $row->creator?->name, 'proof_url' => $row->proof_path ? route('admin.customer-charges.proof', $row, absolute: false) : null, 'preview_url' => route('admin.customer-charges.preview', $row, absolute: false), 'edit_url' => route('admin.customer-charges.edit', $row, absolute: false), 'approval_status' => $approval?->status, 'approval_step' => $approval?->current_step, 'approval_total' => $approval?->total_steps, 'can_review' => $approval ? app(ApprovalWorkflowService::class)->canReview($approval) : false, 'can_edit' => $row->record_status === 'draft' && $row->created_by === auth()->id(), 'can_lock' => $row->record_status === 'draft' && $row->created_by === auth()->id(), 'can_unlock' => $row->record_status === 'locked' && ! in_array($row->status, ['posted', 'reversed'], true) && (auth()->user()?->can('customer-charges.unlock') || auth()->user()?->hasRole('super_admin')), 'can_reverse' => $row->status === 'posted' && in_array($row->reversal_status, [null, 'rejected'], true) && (float) ($row->invoice?->paid_amount ?? 0) <= 0 && (auth()->user()?->can('customer-charges.reverse') || auth()->user()?->hasRole('super_admin'))];
    }
}
