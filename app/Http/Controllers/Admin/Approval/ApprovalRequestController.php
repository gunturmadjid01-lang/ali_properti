<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Approval\RejectApprovalRequest;
use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflowService;
use App\Support\ApprovalResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', ApprovalRequest::STATUS_PENDING);

        $rows = ApprovalRequest::query()
            ->with(['requester:id,name', 'reviewer:id,name'])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('module_label', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ApprovalRequest $approvalRequest) => [
                'id' => $approvalRequest->id,
                'module_key' => $approvalRequest->module_key,
                'module_label' => $approvalRequest->module_label,
                'action' => $approvalRequest->action,
                'action_label' => ApprovalResources::actions()[$approvalRequest->action] ?? $approvalRequest->action,
                'status' => $approvalRequest->status,
                'requested_by' => $approvalRequest->requester?->name,
                'reviewed_by' => $approvalRequest->reviewer?->name,
                'reviewed_at' => optional($approvalRequest->reviewed_at)->format('Y-m-d H:i'),
                'before_data' => $approvalRequest->before_data,
                'after_data' => $approvalRequest->after_data,
                'rejection_note' => $approvalRequest->rejection_note,
                'created_at' => optional($approvalRequest->created_at)->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/Approval/Index', [
            'title' => 'Approval Perubahan Data',
            'baseUrl' => route('admin.approval.requests.index', absolute: false),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'rows' => $rows,
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
                ['value' => 'all', 'label' => 'Semua'],
            ],
        ]);
    }

    public function approve(ApprovalRequest $approvalRequest, ApprovalWorkflowService $service): RedirectResponse
    {
        $service->approve($approvalRequest);

        return back()->with('success', 'Request berhasil disetujui.');
    }

    public function reject(RejectApprovalRequest $request, ApprovalRequest $approvalRequest, ApprovalWorkflowService $service): RedirectResponse
    {
        $service->reject($approvalRequest, $request->validated('rejection_note'));

        return back()->with('success', 'Request berhasil ditolak.');
    }
}
