<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ApprovalWorkflowService;
use App\Support\ApprovalResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

trait HandlesCrudLock
{
    protected function abortIfLocked(Model $model): void
    {
        abort_if(
            ($model->record_status ?? 'draft') === 'locked' && ! $this->currentUserCanManageLockedRecords(),
            403,
            'Data sudah di-lock. Hanya user yang diberi akses unlock yang dapat mengubah atau membuka lock data ini.'
        );
    }

    public function lock(string $id): RedirectResponse
    {
        abort_unless(auth()->check(), 403, 'Silakan login untuk mengunci data.');
        if (method_exists($this, 'authorizeLockPermission')) {
            $this->authorizeLockPermission();
        }

        $model = $this->lockableQuery()->findOrFail($id);

        $data = [
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ];

        if ($model->isFillable('updated_by')) {
            $data['updated_by'] = auth()->id();
        }

        $model->forceFill($data)->save();

        $approval = app(ApprovalWorkflowService::class)->submitLocked($model, ApprovalResources::keyForModel($model));
        $message = $approval->status === 'approved'
            ? 'Data berhasil di-lock dan disetujui otomatis sesuai Setting Approval.'
            : "Data berhasil di-lock dan masuk approval tahap {$approval->current_step} dari {$approval->total_steps}.";

        return back()->with('success', $message);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->currentUserCanManageLockedRecords(), 403, 'Hanya user yang diberi akses yang dapat membuka lock data.');

        DB::transaction(function () use ($id): void {
            $model = $this->lockableQuery()->lockForUpdate()->findOrFail($id);
            abort_unless(($model->record_status ?? 'draft') === 'locked', 422, 'Data tidak sedang terkunci.');

            if (method_exists($this, 'beforeUnlock')) {
                $this->beforeUnlock($model);
            }

            app(ApprovalWorkflowService::class)->reverseLockApproval($model);

            $data = [
                'record_status' => 'draft',
                'locked_at' => null,
                'locked_by' => null,
            ];

            if ($model->isFillable('updated_by')) {
                $data['updated_by'] = auth()->id();
            }

            $model->forceFill($data)->save();
        });

        return back()->with('success', 'Lock data berhasil dibuka.');
    }

    protected function lockableQuery()
    {
        $modelClass = method_exists($this, 'modelClass') ? $this->modelClass() : $this->lockableModelClass();

        return $modelClass::query();
    }

    protected function currentUserCanManageLockedRecords(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $modelClass = method_exists($this, 'modelClass') ? $this->modelClass() : $this->lockableModelClass();
        $moduleKey = ApprovalResources::keyForModel(new $modelClass);

        return $user->can("{$moduleKey}.unlock")
            || $user->can("{$moduleKey}.manage")
            || $user->can("{$moduleKey}-unlock")
            || $user->can("{$moduleKey}-manage");
    }
}
