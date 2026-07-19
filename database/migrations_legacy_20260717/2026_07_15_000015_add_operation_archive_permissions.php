<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['company-inventory.approve', 'company-inventory.print', 'heavy-equipment.approve', 'heavy-equipment.print'] as $name) {
            Permission::findOrCreate($name, 'web');
        }foreach (['owner', 'super_admin', 'manajer_pimpro'] as $name) {
            if ($role = Role::where(['name' => $name, 'guard_name' => 'web'])->first()) {
                $role->givePermissionTo(['company-inventory.approve', 'company-inventory.print', 'heavy-equipment.approve', 'heavy-equipment.print']);
            }
        }foreach (['user_area_gudang', 'petugas'] as $name) {
            if ($role = Role::where(['name' => $name, 'guard_name' => 'web'])->first()) {
                $role->givePermissionTo(['company-inventory.print', 'heavy-equipment.print']);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['company-inventory.approve', 'company-inventory.print', 'heavy-equipment.approve', 'heavy-equipment.print'])->delete();
    }
};
