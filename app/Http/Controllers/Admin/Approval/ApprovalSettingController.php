<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Approval\UpdateApprovalSettingsRequest;
use App\Models\ApprovalSetting;
use App\Services\ApprovalWorkflowService;
use App\Support\ApprovalResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Throwable;

class ApprovalSettingController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('approval.settings'), 403);
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
                ->whereIn('action', array_keys(ApprovalResources::actions()))
                ->orderBy('module_label')
                ->orderBy('action')
                ->get()
                ->map(function (ApprovalSetting $setting) {
                    $category = ApprovalResources::category($setting->module_key);

                    return [
                    'module_key' => $setting->module_key,
                    'module_label' => $setting->module_label,
                    'category_key' => $category['key'],
                    'category_label' => $category['label'],
                    'action' => $setting->action,
                    'requires_approval' => $setting->requires_approval,
                    'approval_stages' => (int) ($setting->approval_stages ?? ($setting->requires_approval ? 1 : 0)),
                    'approver_role_ids' => collect($setting->approver_role_ids ?? [])->map(fn ($id) => (string) $id)->all(),
                    'approval_steps' => collect($setting->approval_steps ?? [])->map(fn ($step) => [
                        'step' => (int) ($step['step'] ?? 1),
                        'role_ids' => collect($step['role_ids'] ?? [])->take(1)->map(fn ($id) => (string) $id)->all(),
                    ])->values()->all(),
                    ];
                })
                ->values(),
            'approvalCategories' => collect(ApprovalResources::categories())
                ->map(fn (array $category, string $key) => ['key' => $key, 'label' => $category['label']])
                ->values(),
        ]);
    }

    public function update(UpdateApprovalSettingsRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('approval.settings'), 403);
        try {
            DB::transaction(function () use ($request): void {
                foreach ($request->validated('settings') as $setting) {
                    $current = ApprovalSetting::query()
                        ->where('module_key', $setting['module_key'])
                        ->where('action', $setting['action'])
                        ->first();
                    if ($current) {
                        app(ApprovalWorkflowService::class)->freezePendingConfiguration($current);
                    }

                    $updated = ApprovalSetting::query()->updateOrCreate(
                        [
                            'module_key' => $setting['module_key'],
                            'action' => $setting['action'],
                        ],
                        [
                            'module_label' => $setting['module_label'],
                            'requires_approval' => (int) $setting['approval_stages'] > 0,
                            'approval_stages' => (int) $setting['approval_stages'],
                            'approver_role_ids' => $setting['approval_steps'][0]['role_ids'] ?? [],
                            'approval_steps' => collect($setting['approval_steps'] ?? [])->take((int) $setting['approval_stages'])->values()->all(),
                            'is_active' => true,
                        ],
                    );
                    app(ApprovalWorkflowService::class)->reconfigureUntouchedPending($updated);
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Setting approval gagal disimpan. Tidak ada perubahan yang diterapkan. Silakan coba kembali.');
        }

        return back()->with('success', 'Setting approval berhasil disimpan.');
    }

    private function ensureDefaultSettings(): void
    {
        $roleIds = fn (array $roleNames) => Role::query()
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $defaults = [
            'customer' => [
                'create' => [true, $roleIds(['supervisor_marketing', 'manajer_pimpro'])],
                'update' => [true, $roleIds(['supervisor_marketing', 'manajer_pimpro'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'marketing-lead-source' => [
                'create' => [false, []],
                'update' => [false, []],
                'delete' => [false, []],
            ],
            'perumahan' => [
                'create' => [false, []],
                'update' => [false, []],
                'delete' => [false, []],
            ],
            'detail-rumah' => [
                'create' => [false, []],
                'update' => [false, []],
                'delete' => [false, []],
            ],
            'progress' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'site-report' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'quality-inspection' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'field-supervision' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'site-schedule' => [
                'create' => [false, []],
                'update' => [false, []],
                'delete' => [false, []],
            ],
            'material-request' => [
                'create' => [true, $roleIds(['user_area_gudang', 'owner'])],
                'update' => [true, $roleIds(['user_area_gudang', 'owner'])],
                'delete' => [true, $roleIds(['owner'])],
            ],
            'material-purchase' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'spk-kontraktor' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
            'spr' => [
                'create' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'update' => [true, $roleIds(['manajer_pimpro', 'owner'])],
                'delete' => [true, $roleIds(['manajer_pimpro', 'owner'])],
            ],
        ];

        foreach (ApprovalResources::modules() as $moduleKey => $module) {
            foreach (ApprovalResources::actions() as $action => $label) {
                [$requiresApproval, $approverRoleIds] = $defaults[$moduleKey][$action] ?? $defaults[$moduleKey]['create'] ?? [false, []];
                $primaryRoleIds = collect($approverRoleIds)->take(1)->values()->all();
                ApprovalSetting::query()->firstOrCreate(
                    [
                        'module_key' => $moduleKey,
                        'action' => $action,
                    ],
                    [
                        'module_label' => $module['label'],
                        'requires_approval' => $requiresApproval,
                        'approval_stages' => $requiresApproval ? 1 : 0,
                        'approver_role_ids' => $primaryRoleIds,
                        'approval_steps' => $requiresApproval ? [['step' => 1, 'role_ids' => $primaryRoleIds]] : [],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
