<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ApprovalSetting;

trait UsesApprovalSettings
{
    protected function approvalSetting(string $moduleKey, string $action = 'create'): ?ApprovalSetting
    {
        return ApprovalSetting::query()
            ->where('module_key', $moduleKey)
            ->where('action', $action)
            ->where('is_active', true)
            ->first();
    }

    protected function requiresApprovalFor(string $moduleKey, string $action = 'create'): bool
    {
        return (bool) $this->approvalSetting($moduleKey, $action)?->requires_approval;
    }

    protected function canApproveFor(string $moduleKey, string $action = 'create'): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        $setting = $this->approvalSetting($moduleKey, $action);

        if (! $setting || ! $setting->requires_approval) {
            return false;
        }

        $roleIds = collect($setting->approver_role_ids ?? [])->map(fn ($id) => (int) $id);

        if ($roleIds->isEmpty()) {
            return $user->hasRole('super_admin');
        }

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }
}
