<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Support\ApprovalResources;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApprovalWorkflowService
{
    public function create(string $moduleKey, array $payload, Closure $directAction): RedirectResponse
    {
        if ($this->requiresApproval($moduleKey, 'create')) {
            $this->queue($moduleKey, 'create', ApprovalResources::modelClass($moduleKey), null, null, $payload);

            return back()->with('success', 'Pengajuan tambah data masuk ke tabel approval.');
        }

        $directAction($payload);

        return back()->with('success', ApprovalResources::label($moduleKey).' berhasil ditambahkan.');
    }

    public function update(string $moduleKey, Model $row, array $payload, Closure $directAction): RedirectResponse
    {
        if ($this->requiresApproval($moduleKey, 'update')) {
            $this->queue($moduleKey, 'update', $row::class, $row->getKey(), $row->toArray(), $payload);

            return back()->with('success', 'Pengajuan perubahan data masuk ke tabel approval.');
        }

        $directAction($row, $payload);

        return back()->with('success', ApprovalResources::label($moduleKey).' berhasil diperbarui.');
    }

    public function delete(string $moduleKey, Model $row, Closure $directAction): RedirectResponse
    {
        if ($this->requiresApproval($moduleKey, 'delete')) {
            $this->queue($moduleKey, 'delete', $row::class, $row->getKey(), $row->toArray(), null);

            return back()->with('success', 'Pengajuan hapus data masuk ke tabel approval.');
        }

        $directAction($row);

        return back()->with('success', ApprovalResources::label($moduleKey).' berhasil dihapus.');
    }

    public function approve(ApprovalRequest $approvalRequest): void
    {
        abort_unless($approvalRequest->status === ApprovalRequest::STATUS_PENDING, 422, 'Approval request sudah diproses.');
        abort_unless($this->canReview($approvalRequest), 403);

        DB::transaction(function () use ($approvalRequest) {
            $moduleKey = $approvalRequest->module_key;
            $modelClass = ApprovalResources::modelClass($moduleKey);

            if ($approvalRequest->action === 'create') {
                $model = $modelClass::query()->create(ApprovalResources::modelPayload($moduleKey, $approvalRequest->after_data ?? []));
                ApprovalResources::syncRelations($moduleKey, $model, $approvalRequest->after_data ?? []);
            }

            if ($approvalRequest->action === 'update') {
                $model = $modelClass::query()->findOrFail($approvalRequest->model_id);
                $model->update(ApprovalResources::modelPayload($moduleKey, $approvalRequest->after_data ?? []));
                ApprovalResources::syncRelations($moduleKey, $model, $approvalRequest->after_data ?? []);
            }

            if ($approvalRequest->action === 'delete') {
                $modelClass::query()->findOrFail($approvalRequest->model_id)->delete();
            }

            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, ?string $note = null): void
    {
        abort_unless($approvalRequest->status === ApprovalRequest::STATUS_PENDING, 422, 'Approval request sudah diproses.');
        abort_unless($this->canReview($approvalRequest), 403);

        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_note' => $note,
        ]);
    }

    public function requiresApproval(string $moduleKey, string $action): bool
    {
        $setting = ApprovalSetting::query()
            ->where('module_key', $moduleKey)
            ->where('action', $action)
            ->where('is_active', true)
            ->first();

        return (bool) $setting?->requires_approval;
    }

    public function canReview(ApprovalRequest $approvalRequest): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        $setting = ApprovalSetting::query()
            ->where('module_key', $approvalRequest->module_key)
            ->where('action', $approvalRequest->action)
            ->first();

        $roleIds = collect($setting?->approver_role_ids ?? [])->map(fn ($id) => (int) $id);

        if ($roleIds->isEmpty()) {
            return Gate::allows('approval.manage') || $user->hasRole('super_admin');
        }

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }

    private function queue(string $moduleKey, string $action, string $modelClass, int|string|null $modelId, ?array $beforeData, ?array $afterData): void
    {
        ApprovalRequest::query()->create([
            'module_key' => $moduleKey,
            'module_label' => ApprovalResources::label($moduleKey),
            'action' => $action,
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => auth()->id(),
        ]);
    }
}
