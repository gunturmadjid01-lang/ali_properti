<?php

namespace Database\Seeders;

use App\Models\ApprovalSetting;
use App\Support\ApprovalResources;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ApprovalSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ApprovalResources::modules() as $moduleKey => $resource) {
            $financeRoleId = in_array($moduleKey, ['housing-reservation', 'customer-refund'], true) ? Role::query()->whereRaw('LOWER(name) = ?', ['keuangan'])->value('id') : null;
            ApprovalSetting::query()->firstOrCreate(
                ['module_key' => $moduleKey, 'action' => 'lock'],
                [
                    'module_label' => $resource['label'],
                    'requires_approval' => (bool) $financeRoleId,
                    'approval_stages' => $financeRoleId ? 1 : 0,
                    'approver_role_ids' => $financeRoleId ? [$financeRoleId] : [],
                    'approval_steps' => $financeRoleId ? [['step' => 1, 'role_ids' => [$financeRoleId]]] : [],
                    'is_active' => true,
                ]
            );
        }
    }
}
