<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesResolutionRequest;
use App\Models\SalesTransaction;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesResolutionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('booking.view') || $request->user()?->can('booking.manage'), 403);
        $marketingId = $request->integer('marketing_id');
        $canViewAll = $request->user()?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing']) || $request->user()?->can('booking.manage');
        $requests = SalesResolutionRequest::with(['salesTransaction.customer', 'salesTransaction.marketing', 'spr', 'creator', 'approvalRequests'])
            ->when(! $canViewAll, fn ($q) => $q->where('created_by', $request->user()->id))
            ->when($marketingId, fn ($q) => $q->whereHas('salesTransaction', fn ($q) => $q->where('marketing_user_id', $marketingId)))
            ->latest()->paginate(15)->withQueryString()->through(function ($row) use ($request) {
                $approval = $row->approvalRequests->first();

                return [
                    'id' => $row->id, 'request_no' => $row->request_no, 'transaction' => $row->salesTransaction?->transaction_no, 'customer' => $row->salesTransaction?->customer?->nama,
                    'marketing' => $row->salesTransaction?->marketing?->name, 'spr' => $row->spr?->kode_spr, 'action' => $row->action, 'failed_stage' => $row->failed_stage,
                    'failure_category' => $row->failure_category, 'failure_reason' => $row->failure_reason, 'proposed_payment_method' => $row->proposed_payment_method,
                    'status' => $row->status, 'record_status' => $row->record_status, 'approval_status' => $approval?->status, 'approval_id' => $approval?->id,
                    'approval_stage' => $approval?->status === 'pending' ? "Tahap {$approval->current_step}/{$approval->total_steps}" : null,
                    'can_review' => $approval ? app(ApprovalWorkflowService::class)->canReview($approval) : false,
                    'can_lock' => $row->record_status === 'draft' && $row->created_by === $request->user()->id,
                    'can_unlock' => $row->record_status === 'locked' && $row->status !== 'approved' && ($request->user()->can('booking.manage') || $request->user()->hasAnyRole(['owner', 'manager', 'manajer_pimpro'])),
                ];
            });
        $transactions = SalesTransaction::with(['customer', 'marketing', 'spr', 'processSteps'])->whereNotIn('status', ['closed_lost', 'completed'])->latest()->limit(300)->get()->map(fn ($row) => [
            'value' => (string) $row->id, 'label' => $row->transaction_no.' - '.$row->customer?->nama.' - '.$row->marketing?->name,
            'spr_id' => $row->spr_id, 'payment_method' => $row->payment_method, 'stage' => ($row->processSteps->firstWhere('status', 'in_progress') ?: $row->processSteps->firstWhere('status', 'available'))?->code,
        ]);

        return Inertia::render('Admin/SalesResolution/Index', ['title' => 'Penanganan Proses Penjualan Gagal', 'baseUrl' => route('admin.sales-resolutions.index', absolute: false), 'rows' => $requests, 'transactions' => $transactions, 'filters' => ['marketing_id' => $marketingId ?: '']]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('booking.manage') || $request->user()?->can('booking.update'), 403);
        $data = $request->validate(['sales_transaction_id' => 'required|exists:sales_transactions,id', 'action' => 'required|in:retry_stage,change_payment_method,close_lost', 'failed_stage' => 'nullable|string|max:100', 'failure_category' => 'required|string|max:100', 'failure_reason' => 'required|string|max:2000', 'proposed_payment_method' => 'required_if:action,change_payment_method|nullable|in:cash,cash_bertahap,kpr_bank,kpr_developer', 'restart_stage' => 'nullable|string|max:100', 'financial_treatment' => 'required|in:review_required,carry_forward,refund,forfeit', 'resolution_notes' => 'nullable|string|max:2000']);
        $transaction = SalesTransaction::findOrFail($data['sales_transaction_id']);
        SalesResolutionRequest::create([...$data, 'request_no' => 'PJG/'.now()->format('Y').'/'.str_pad((string) (SalesResolutionRequest::max('id') + 1), 6, '0', STR_PAD_LEFT), 'spr_id' => $transaction->spr_id, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Draf penanganan gagal dibuat. Periksa lalu ajukan ke approval.');
    }

    public function lock(Request $request, SalesResolutionRequest $resolution, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()?->can('booking.manage') || $request->user()?->can('booking.update'), 403);
        abort_unless($resolution->created_by === $request->user()->id, 403, 'Hanya pembuat draf yang dapat mengajukan approval.');
        abort_unless($resolution->record_status === 'draft', 422, 'Usulan sudah diajukan.');
        $resolution->update(['record_status' => 'locked', 'status' => 'pending_approval', 'locked_at' => now(), 'locked_by' => $request->user()->id]);
        $approval = $workflow->submitLocked($resolution, 'sales-resolution-request');

        return back()->with('success', $approval->status === 'approved' ? 'Tindak lanjut disetujui dan diterapkan.' : 'Tindak lanjut diajukan ke Setting Approval.');
    }

    public function unlock(Request $request, SalesResolutionRequest $resolution, ApprovalWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()?->can('booking.manage'), 403);
        abort_if($resolution->status === 'approved', 422, 'Tindak lanjut sudah diterapkan.');
        $workflow->cancelPendingLock($resolution);
        $resolution->update(['record_status' => 'draft', 'status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success','Usulan dikembalikan menjadi draf.');
    }
}
