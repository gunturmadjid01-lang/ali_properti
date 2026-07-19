<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $groups = [
            'company-inventory' => ['dashboard', 'categories', 'items', 'units', 'locations', 'receipts', 'loans', 'returns', 'transfers', 'damages', 'losses', 'stock-opname', 'ledger', 'reports'],
            'heavy-equipment' => ['dashboard', 'equipment', 'types', 'components', 'replacements', 'usage', 'operators', 'maintenance', 'damages', 'fuel', 'reports'],
        ];
        $actions = ['view', 'create', 'update', 'delete', 'export', 'verify', 'approve', 'print'];
        foreach ($groups as $prefix => $sections) {
            foreach ($sections as $section) {
                foreach ($actions as $action) {
                    Permission::findOrCreate("{$prefix}.{$section}.{$action}", 'web');
                }
            }
        }
        Role::with('permissions')->get()->each(function (Role $role) use ($groups, $actions) {
            foreach ($groups as $prefix => $sections) {
                foreach ($actions as $action) {
                    if ($role->permissions->contains('name', "{$prefix}.{$action}")) {
                        $role->givePermissionTo(collect($sections)->map(fn ($section) => "{$prefix}.{$section}.{$action}")->all());
                        $role->revokePermissionTo("{$prefix}.{$action}");
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Permission::where(fn ($q) => $q->where('name', 'like', 'company-inventory.%.%')->orWhere('name', 'like', 'heavy-equipment.%.%'))->delete();
    }
};
