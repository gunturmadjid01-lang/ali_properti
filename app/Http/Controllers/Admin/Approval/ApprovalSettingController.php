<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Approval\UpdateApprovalSettingsRequest;
use App\Models\ApprovalSetting;
use App\Support\ApprovalResources;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class ApprovalSettingController extends Controller
{
    public function index(): Response
    {
        $this->ensureDefaultSettings();

        return Inertia::render('Admin/Approval/Settings', [
            'title' => 'Setting Approval',
            'baseUrl' => route('admin.approval.settings.update', absolute: false),
            'actions' => ApprovalResources::actions(),
            'roles' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Role $role) => ['value' => (string) $role->id, 'label' => $role->name])
                ->values(),
            'settings' => ApprovalSetting::query()
                ->orderBy('module_label')
                ->orderBy('action')
                ->get()
                ->map(fn (ApprovalSetting $setting) => [
                    'module_key' => $setting->module_key,
                    'module_label' => $setting->module_label,
                    'action' => $setting->action,
                    'requires_approval' => $setting->requires_approval,
                    'approver_role_ids' => collect($setting->approver_role_ids ?? [])->map(fn ($id) => (string) $id)->all(),
                ])
                ->values(),
        ]);
    }

    public function update(UpdateApprovalSettingsRequest $request): RedirectResponse
    {
        foreach ($request->validated('settings') as $setting) {
            ApprovalSetting::query()->updateOrCreate(
                [
                    'module_key' => $setting['module_key'],
                    'action' => $setting['action'],
                ],
                [
                    'module_label' => $setting['module_label'],
                    'requires_approval' => (bool) ($setting['requires_approval'] ?? false),
                    'approver_role_ids' => $setting['approver_role_ids'] ?? [],
                    'is_active' => true,
                ],
            );
        }

        return back()->with('success', 'Setting approval berhasil disimpan.');
    }

    private function ensureDefaultSettings(): void
    {
        foreach (ApprovalResources::modules() as $moduleKey => $module) {
            foreach (ApprovalResources::actions() as $action => $label) {
                ApprovalSetting::query()->firstOrCreate(
                    [
                        'module_key' => $moduleKey,
                        'action' => $action,
                    ],
                    [
                        'module_label' => $module['label'],
                        'requires_approval' => false,
                        'approver_role_ids' => [],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
