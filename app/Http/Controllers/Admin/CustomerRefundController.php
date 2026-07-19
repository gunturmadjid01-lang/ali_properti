<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\CustomerRefund;
use App\Models\MasterBank;
use App\Services\ApprovalWorkflowService;
use App\Services\CustomerRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRefundController extends Controller
{
    private function allow(Request $request, string $action): void { abort_unless($request->user()?->can("customer-refunds.{$action}") || $request->user()?->hasRole('super_admin'), 403); }

    public function index(Request $request): Response
    {
        $this->allow($request, 'view');
        $rows = CustomerRefund::with(['salesTransaction.customer', 'salesTransaction.housingProject', 'resolution', 'bankAccount', 'creator', 'journal', 'approvalRequests'])
            ->where(fn ($q) => $q->where('record_status', 'locked')->orWhere('created_by', $request->user()?->id)->orWhere(fn ($draft) => $draft->where('record_status', 'draft')->when(! $request->user()?->can('customer-refunds.update'), fn ($hidden) => $hidden->whereRaw('1 = 0'))))->latest()->paginate(15)->through(function ($row) {
                $approval = $row->approvalRequests->first();
                return ['id' => $row->id, 'refund_no' => $row->refund_no, 'transaction' => $row->salesTransaction?->transaction_no, 'customer' => $row->salesTransaction?->customer?->nama, 'housing' => $row->salesTransaction?->housingProject?->nama_perusahaan, 'resolution_no' => $row->resolution?->request_no, 'eligible_amount' => (float) $row->eligible_amount, 'penalty_amount' => (float) $row->penalty_amount, 'refund_amount' => (float) $row->refund_amount, 'refund_date' => $row->refund_date?->format('Y-m-d'), 'recipient_name' => $row->recipient_name, 'recipient_bank' => $row->recipient_bank, 'recipient_account' => $row->recipient_account, 'transfer_reference' => $row->transfer_reference, 'notes' => $row->notes, 'status' => $row->status, 'record_status' => $row->record_status, 'journal_no' => $row->journal?->nomor_jurnal, 'proof_url' => $row->proof_path ? route('admin.customer-refunds.proof', $row, absolute: false) : null, 'approval_status' => $approval?->status, 'approval_stage' => $approval?->status === 'pending' ? "Tahap {$approval->current_step}/{$approval->total_steps}" : null, 'can_review' => $approval ? app(ApprovalWorkflowService::class)->canReview($approval) : false, 'can_edit' => $row->record_status === 'draft' && $row->created_by === auth()->id(), 'can_lock' => $row->record_status === 'draft' && $row->created_by === auth()->id(), 'can_unlock' => $row->record_status === 'locked' && $row->status !== 'posted' && (auth()->user()?->can('customer-refunds.unlock') || auth()->user()?->hasRole('super_admin'))];
            });
        $banks = MasterBank::query()->finalized()->where('status', 'aktif')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_bank.' - '.$row->nomor_rekening]);
        return Inertia::render('Admin/CustomerRefunds/Index', ['title' => 'Refund Booking Fee & Uang Muka', 'rows' => $rows, 'banks' => $banks]);
    }

    public function update(Request $request, CustomerRefund $refund, CustomerRefundService $service): RedirectResponse
    {
        $this->allow($request, 'update');
        abort_unless($refund->record_status === 'draft', 403);
        $eligible = $service->eligibleAmount($refund->sales_transaction_id, $refund->id);
        $data = $request->validate(['master_bank_id' => 'required|exists:master_banks,id', 'penalty_amount' => "required|numeric|min:0|max:{$eligible}", 'refund_amount' => "required|numeric|min:0|max:{$eligible}", 'refund_date' => 'required|date', 'recipient_name' => 'required|string|max:150', 'recipient_bank' => 'required|string|max:150', 'recipient_account' => 'required|string|max:100', 'transfer_reference' => 'required|string|max:100', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 'notes' => 'nullable|string|max:2000']);
        abort_if(abs(((float) $data['refund_amount'] + (float) $data['penalty_amount']) - $eligible) > 0.005, 422, 'Refund ditambah potongan harus sama dengan dana yang tersedia.');
        if ($request->hasFile('proof')) { if ($refund->proof_path) Storage::disk('public')->delete($refund->proof_path); $data['proof_path'] = $request->file('proof')->store('customer-refunds/'.now()->format('Y/m'), 'public'); $data['proof_original_name'] = $request->file('proof')->getClientOriginalName(); }
        unset($data['proof']);
        $refund->update([...$data, 'eligible_amount' => $eligible, 'updated_by' => $request->user()?->id]);
        return back()->with('success', 'Draf refund diperbarui.');
    }

    public function lock(Request $request, CustomerRefund $refund, ApprovalWorkflowService $workflow, CustomerRefundService $service): RedirectResponse
    {
        $this->allow($request, 'lock');
        abort_unless($refund->record_status === 'draft', 403);
        $eligible = $service->eligibleAmount($refund->sales_transaction_id, $refund->id);
        abort_unless($refund->master_bank_id && $refund->refund_date && $refund->recipient_name && $refund->recipient_bank && $refund->recipient_account && $refund->transfer_reference && $refund->proof_path, 422, 'Rekening sumber, rekening tujuan, referensi, dan bukti transfer wajib lengkap.');
        abort_if(abs(((float) $refund->refund_amount + (float) $refund->penalty_amount) - $eligible) > 0.005, 422, 'Nominal refund sudah tidak sesuai saldo dana customer.');
        $refund->update(['eligible_amount' => $eligible, 'record_status' => 'locked', 'status' => 'pending_approval', 'locked_at' => now(), 'locked_by' => $request->user()?->id]);
        $approval = $workflow->submitLocked($refund, 'customer-refund');
        return back()->with('success', $approval->status === 'approved' ? 'Refund disetujui dan diposting.' : "Refund masuk persetujuan tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function unlock(Request $request, CustomerRefund $refund, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->allow($request, 'unlock'); abort_if($refund->status === 'posted', 422, 'Refund terposting tidak dapat dibuka.');
        $workflow->cancelPendingLock($refund); $refund->update(['record_status' => 'draft', 'status' => 'draft', 'locked_at' => null, 'locked_by' => null]);
        return back()->with('success', 'Refund dikembalikan menjadi draf privat.');
    }

    public function review(Request $request, CustomerRefund $refund, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::where('model_type', CustomerRefund::class)->where('model_id', $refund->id)->where('status', 'pending')->latest()->firstOrFail(); abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);
        return back()->with('success', $decision === 'approve' ? 'Tahap refund disetujui.' : 'Refund ditolak.');
    }

    public function proof(Request $request, CustomerRefund $refund) { $this->allow($request, 'view'); abort_unless($refund->proof_path && Storage::disk('public')->exists($refund->proof_path), 404); return Storage::disk('public')->response($refund->proof_path, $refund->proof_original_name); }
}
