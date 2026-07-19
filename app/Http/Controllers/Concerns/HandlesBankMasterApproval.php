<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait HandlesBankMasterApproval
{
    abstract protected function approvalModelClass(): string;

    abstract protected function approvalModuleKey(): string;

    abstract protected function approvalPermissionKey(): string;

    public function lock(Request $request, string $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeApprovalAction($request, 'submit');
        $model = $this->approvalModel($id);
        abort_unless($model->record_status === 'draft', 422, 'Data ini sudah difinalisasi.');

        $model->forceFill([
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $request->user()->id,
        ])->save();
        $approval = $workflow->submitLocked($model, $this->approvalModuleKey());

        return back()->with('success', $approval->status === 'approved'
            ? 'Data berhasil difinalisasi dan disetujui otomatis.'
            : "Data berhasil difinalisasi dan masuk approval tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function unlock(Request $request, string $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeApprovalAction($request, 'update');
        $model = $this->approvalModel($id);
        $workflow->cancelPendingLock($model);
        $model->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();

        return back()->with('success', 'Data dikembalikan menjadi draf dan tidak lagi tersedia di modul lain.');
    }

    public function review(Request $request, string $id, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $model = $this->approvalModel($id);
        $approval = ApprovalRequest::query()->where([
            'module_key' => $this->approvalModuleKey(),
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'status' => ApprovalRequest::STATUS_PENDING,
        ])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);

        if ($decision === 'approve') {
            $workflow->approve($approval);
        } else {
            $note = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];
            $workflow->reject($approval, $note);
        }

        return back()->with('success', 'Approval berhasil diproses.');
    }

    protected function approvalState(Model $model, ApprovalWorkflowService $workflow): array
    {
        $approval = ApprovalRequest::query()->where([
            'module_key' => $this->approvalModuleKey(),
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ])->latest()->first();

        return [
            'record_status' => $model->record_status ?? 'draft',
            'approval_status' => $approval?->status,
            'approval_stage' => $approval?->status === ApprovalRequest::STATUS_PENDING
                ? "{$approval->current_step}/{$approval->total_steps}"
                : null,
            'can_review' => $approval?->status === ApprovalRequest::STATUS_PENDING && $workflow->canReview($approval),
        ];
    }

    protected function abortWhenFinalized(Model $model): void
    {
        abort_if(($model->record_status ?? 'draft') === 'locked', 422, 'Data sudah final. Buka lock terlebih dahulu untuk mengubahnya.');
    }

    private function approvalModel(string $id): Model
    {
        $class = $this->approvalModelClass();

        return $class::query()->findOrFail($id);
    }

    private function authorizeApprovalAction(Request $request, string $action): void
    {
        abort_unless($request->user()?->can($this->approvalPermissionKey().'.'.$action) || $request->user()?->hasRole('super_admin'), 403);
    }
}
