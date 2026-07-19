<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\OperationTransactionArchive;
use App\Support\ApprovalResources;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowService
{
    public function create(string $moduleKey, array $payload, Closure $directAction, ?Closure $responseFactory = null): RedirectResponse
    {
        $directAction($payload);

        return $responseFactory
            ? $responseFactory(false)
            : back()->with('success', ApprovalResources::label($moduleKey).' berhasil ditambahkan.');
    }

    public function submitLocked(Model $model, ?string $moduleKey = null): ApprovalRequest
    {
        $moduleKey ??= ApprovalResources::keyForModel($model);
        $setting = ApprovalSetting::query()->where(['module_key' => $moduleKey, 'action' => 'lock', 'is_active' => true])->first();
        $stages = max(0, min(3, (int) ($setting?->approval_stages ?? 0)));
        $approvalSteps = $this->normalizedApprovalSteps($setting, $stages);
        $existing = ApprovalRequest::query()->where(['module_key' => $moduleKey, 'action' => 'lock', 'model_type' => $model::class, 'model_id' => $model->getKey(), 'status' => ApprovalRequest::STATUS_PENDING])->first();
        if ($existing) {
            return $existing;
        }
        $request = ApprovalRequest::query()->create(['module_key' => $moduleKey, 'module_label' => ApprovalResources::label($moduleKey), 'action' => 'lock', 'model_type' => $model::class, 'model_id' => $model->getKey(), 'before_data' => ['record_status' => 'draft'], 'after_data' => ['record_status' => 'locked', 'approval_steps_snapshot' => $approvalSteps], 'status' => $stages === 0 ? ApprovalRequest::STATUS_APPROVED : ApprovalRequest::STATUS_PENDING, 'current_step' => 1, 'total_steps' => max(1, $stages), 'step_history' => $stages === 0 ? [['step' => 0, 'decision' => 'auto_approved', 'user_id' => auth()->id(), 'user_name' => auth()->user()?->name, 'decided_at' => now()->toISOString()]] : [], 'requested_by' => auth()->id(), 'reviewed_by' => $stages === 0 ? auth()->id() : null, 'reviewed_at' => $stages === 0 ? now() : null]);
        app(ApprovalWorkflowEffectService::class)->submitted($model, $request);
        if ($stages === 0 && $model instanceof OperationTransactionArchive) {
            $model->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        }
        if ($stages === 0) {
            app(ApprovalWorkflowEffectService::class)->approved($model, $request);
        }

        return $request;
    }

    public function cancelPendingLock(Model $model): void
    {
        ApprovalRequest::query()->where(['action' => 'lock', 'model_type' => $model::class, 'model_id' => $model->getKey(), 'status' => ApprovalRequest::STATUS_PENDING])->update(['status' => ApprovalRequest::STATUS_REJECTED, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'rejection_note' => 'Finalisasi dibatalkan melalui unlock.']);
    }

    public function update(string $moduleKey, Model $row, array $payload, Closure $directAction): RedirectResponse
    {
        $directAction($row, $payload);

        return back()->with('success', ApprovalResources::label($moduleKey).' berhasil diperbarui.');
    }

    public function delete(string $moduleKey, Model $row, Closure $directAction): RedirectResponse
    {
        $directAction($row);

        return back()->with('success', ApprovalResources::label($moduleKey).' berhasil dihapus.');
    }

    public function approve(ApprovalRequest $approvalRequest): void
    {
        abort_unless($approvalRequest->status === ApprovalRequest::STATUS_PENDING, 422, 'Approval request sudah diproses.');
        abort_unless($this->canReview($approvalRequest), 403);

        DB::transaction(function () use ($approvalRequest) {
            $history = $approvalRequest->step_history ?? [];
            $history[] = [
                'step' => $approvalRequest->current_step,
                'decision' => 'approved',
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name,
                'decided_at' => now()->toISOString(),
            ];

            if ($approvalRequest->current_step < $approvalRequest->total_steps) {
                $approvalRequest->update([
                    'current_step' => $approvalRequest->current_step + 1,
                    'step_history' => $history,
                ]);

                return;
            }

            $moduleKey = $approvalRequest->module_key;
            $modelClass = $approvalRequest->model_type ?: ApprovalResources::modelClass($moduleKey);

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

            // Aksi lock hanya mengesahkan data yang sudah difinalkan; tidak menulis ulang payload.
            if ($approvalRequest->action === 'lock') {
                $model = $modelClass::query()->findOrFail($approvalRequest->model_id);
            }
            if ($approvalRequest->action === 'lock' && $model instanceof OperationTransactionArchive) {
                $model->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            }

            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'step_history' => $history,
            ]);
            if ($approvalRequest->action === 'lock' && isset($model)) {
                app(ApprovalWorkflowEffectService::class)->approved($model, $approvalRequest);
            }
        });
    }

    public function skipCurrentStep(ApprovalRequest $approvalRequest, string $reason): void
    {
        abort_unless($approvalRequest->status === ApprovalRequest::STATUS_PENDING, 422, 'Approval request sudah diproses.');

        DB::transaction(function () use ($approvalRequest, $reason) {
            $approvalRequest->refresh();
            $history = $approvalRequest->step_history ?? [];
            $history[] = [
                'step' => $approvalRequest->current_step,
                'decision' => 'skipped',
                'reason' => $reason,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()?->name,
                'decided_at' => now()->toISOString(),
            ];

            if ($approvalRequest->current_step < $approvalRequest->total_steps) {
                $approvalRequest->update([
                    'current_step' => $approvalRequest->current_step + 1,
                    'step_history' => $history,
                ]);

                return;
            }

            $modelClass = $approvalRequest->model_type ?: ApprovalResources::modelClass($approvalRequest->module_key);
            $model = $modelClass::query()->findOrFail($approvalRequest->model_id);
            $approvalRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'step_history' => $history,
            ]);
            app(ApprovalWorkflowEffectService::class)->approved($model, $approvalRequest);
        });
    }

    public function reject(ApprovalRequest $approvalRequest, ?string $note = null): void
    {
        abort_unless($approvalRequest->status === ApprovalRequest::STATUS_PENDING, 422, 'Approval request sudah diproses.');
        abort_unless($this->canReview($approvalRequest), 403);

        $history = $approvalRequest->step_history ?? [];
        $history[] = [
            'step' => $approvalRequest->current_step,
            'decision' => 'rejected',
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'note' => $note,
            'decided_at' => now()->toISOString(),
        ];
        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_note' => $note,
            'step_history' => $history,
        ]);
        if ($approvalRequest->action === 'lock' && $approvalRequest->model_type && $approvalRequest->model_id) {
            $model = $approvalRequest->model_type::query()->find($approvalRequest->model_id);
            if ($model instanceof OperationTransactionArchive) {
                $model->update(['status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now(), 'approval_notes' => $note]);
            } elseif ($model && $approvalRequest->module_key !== 'customer-charge-reversal') {
                $model->forceFill(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null])->save();
            }
        }
        if (isset($model) && $model) {
            app(ApprovalWorkflowEffectService::class)->rejected($model, $approvalRequest, $note);
        }
    }

    public function requiresApproval(string $moduleKey, string $action): bool
    {
        if ($action !== 'lock') {
            return false;
        }
        $setting = ApprovalSetting::query()
            ->where('module_key', $moduleKey)
            ->where('action', $action)
            ->where('is_active', true)
            ->first();

        return (int) ($setting?->approval_stages ?? ($setting?->requires_approval ? 1 : 0)) > 0;
    }

    public function canReview(ApprovalRequest $approvalRequest): bool
    {
        if ($approvalRequest->status !== ApprovalRequest::STATUS_PENDING) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $roleIds = $this->reviewerRoleIds($approvalRequest);

        if ($roleIds->isEmpty()) {
            return false;
        }

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }

    public function reviewerRoleIds(ApprovalRequest $approvalRequest): Collection
    {
        $snapshot = data_get($approvalRequest->after_data, 'approval_steps_snapshot');
        if (is_array($snapshot)) {
            $configuredStep = collect($snapshot)->first(
                fn ($step) => (int) ($step['step'] ?? 0) === (int) $approvalRequest->current_step,
            );

            return collect($configuredStep['role_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();
        }

        $setting = ApprovalSetting::query()
            ->where('module_key', $approvalRequest->module_key)
            ->where('action', $approvalRequest->action)
            ->where('is_active', true)
            ->first();
        $configuredStep = collect($setting?->approval_steps ?? [])->first(
            fn ($step) => (int) ($step['step'] ?? 0) === (int) $approvalRequest->current_step,
        );

        return collect($configuredStep['role_ids'] ?? $setting?->approver_role_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    public function freezePendingConfiguration(ApprovalSetting $setting): void
    {
        $stages = max(0, min(3, (int) ($setting->approval_stages ?? 0)));
        $snapshot = $this->normalizedApprovalSteps($setting, $stages);

        ApprovalRequest::query()
            ->where('module_key', $setting->module_key)
            ->where('action', $setting->action)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->get()
            ->each(function (ApprovalRequest $request) use ($snapshot): void {
                if (empty($request->step_history)) {
                    return;
                }
                if (is_array(data_get($request->after_data, 'approval_steps_snapshot'))) {
                    return;
                }

                $afterData = $request->after_data ?? [];
                $afterData['approval_steps_snapshot'] = $snapshot;
                $request->update(['after_data' => $afterData]);
            });
    }

    public function reconfigureUntouchedPending(ApprovalSetting $setting): void
    {
        $stages = max(0, min(3, (int) ($setting->approval_stages ?? 0)));
        $snapshot = $this->normalizedApprovalSteps($setting, $stages);

        ApprovalRequest::query()
            ->where('module_key', $setting->module_key)
            ->where('action', $setting->action)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->get()
            ->each(function (ApprovalRequest $request) use ($stages, $snapshot): void {
                if (! empty($request->step_history) || $request->current_step !== 1) {
                    return;
                }

                $afterData = $request->after_data ?? [];
                $afterData['approval_steps_snapshot'] = $snapshot;
                $attributes = [
                    'after_data' => $afterData,
                    'total_steps' => max(1, $stages),
                ];

                if ($stages === 0) {
                    $attributes += [
                        'status' => ApprovalRequest::STATUS_APPROVED,
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'step_history' => [[
                            'step' => 0,
                            'decision' => 'auto_approved_after_setting_change',
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()?->name,
                            'decided_at' => now()->toISOString(),
                        ]],
                    ];
                }

                $request->update($attributes);

                if ($stages === 0 && $request->model_type && $request->model_id) {
                    $model = $request->model_type::query()->find($request->model_id);
                    if ($model) {
                        app(ApprovalWorkflowEffectService::class)->approved($model, $request->fresh());
                    }
                }
            });
    }

    private function normalizedApprovalSteps(?ApprovalSetting $setting, int $stages): array
    {
        if ($stages === 0) {
            return [];
        }

        $configured = collect($setting?->approval_steps ?? []);
        $fallbackRoleIds = collect($setting?->approver_role_ids ?? [])->all();

        return collect(range(1, $stages))
            ->map(fn (int $stepNumber) => [
                'step' => $stepNumber,
                'role_ids' => collect(
                    data_get($configured->first(fn ($step) => (int) ($step['step'] ?? 0) === $stepNumber), 'role_ids')
                        ?? ($stepNumber === 1 ? $fallbackRoleIds : [])
                )
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->map(fn ($step, int $index) => [
                'step' => $index + 1,
                'role_ids' => $step['role_ids'],
            ])
            ->all();
    }

    private function queue(string $moduleKey, string $action, string $modelClass, int|string|null $modelId, ?array $beforeData, ?array $afterData): void
    {
        $setting = ApprovalSetting::query()->where('module_key', $moduleKey)->where('action', $action)->where('is_active', true)->first();
        $totalSteps = max(1, min(3, (int) ($setting?->approval_stages ?? 1)));
        ApprovalRequest::query()->create([
            'module_key' => $moduleKey,
            'module_label' => ApprovalResources::label($moduleKey),
            'action' => $action,
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'status' => ApprovalRequest::STATUS_PENDING,
            'current_step' => 1,
            'total_steps' => $totalSteps,
            'step_history' => [],
            'requested_by' => auth()->id(),
        ]);
    }
}
