<?php

use App\Models\ApprovalSetting;
use App\Models\User;
use Database\Seeders\InventoryHeavyEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);
test('transaksi alat berat dapat diajukan disetujui dan dicetak oleh petugas berbeda', function () {
    $this->seed(InventoryHeavyEquipmentSeeder::class);
    foreach (['heavy-equipment.fuel.create', 'heavy-equipment.fuel.approve', 'heavy-equipment.fuel.print'] as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $responsible = User::factory()->create(['phone' => '081211110001']);
    $responsible->givePermissionTo('heavy-equipment.fuel.create');
    $role = Role::findOrCreate('approver_arsip', 'web');
    $approver = User::factory()->create(['phone' => '081211110002']);
    $approver->givePermissionTo('heavy-equipment.fuel.approve');
    $approver->assignRole($role);
    ApprovalSetting::create(['module_key' => 'heavy-fuel', 'module_label' => 'Alat Berat - Pengisian BBM', 'action' => 'lock', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]], 'is_active' => true]);
    $printer = User::factory()->create(['phone' => '081211110003']);
    $printer->givePermissionTo('heavy-equipment.fuel.print');
    $typeId = DB::table('heavy_equipment_types')->value('id');
    $equipmentId = DB::table('heavy_equipments')->insertGetId(['code' => 'ALT-TEST', 'name' => 'Alat Arsip', 'heavy_equipment_type_id' => $typeId, 'serial_no' => 'SN-ARSIP-1', 'current_hour_meter' => 0, 'ownership' => 'company', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $equipment = DB::table('heavy_equipments')->find($equipmentId);
    $id = DB::table('heavy_equipment_fuelings')->insertGetId(['date' => today(), 'heavy_equipment_id' => $equipment->id, 'fuel_type' => 'Solar', 'liters' => 10, 'price_per_liter' => 15000, 'total_cost' => 150000, 'hour_meter' => 10, 'created_by' => $responsible->id, 'updated_by' => $responsible->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($responsible)->post(route('admin.transaction-archive.submit', ['module' => 'heavy', 'section' => 'fuel', 'id' => $id]))->assertSessionHasNoErrors();
    expect(DB::table('operation_transaction_archives')->value('status'))->toBe('submitted')->and(DB::table('operation_transaction_archives')->value('responsible_user_id'))->toBe($responsible->id);
    $this->actingAs($approver)->post(route('admin.transaction-archive.decision', ['module' => 'heavy', 'section' => 'fuel', 'id' => $id]), ['action' => 'approve', 'notes' => 'Sesuai bukti BBM'])->assertSessionHasNoErrors();
    $this->actingAs($printer)->get(route('admin.transaction-archive.print', ['module' => 'heavy', 'section' => 'fuel', 'id' => $id]))->assertOk()->assertHeader('content-type', 'application/pdf');
    $archive = DB::table('operation_transaction_archives')->first();
    expect($archive->status)->toBe('approved')->and($archive->approved_by)->toBe($approver->id)->and($archive->last_printed_by)->toBe($printer->id)->and($archive->print_count)->toBe(1);
});

test('user tanpa permission tidak dapat approve atau mencetak arsip', function () {
    $this->seed(InventoryHeavyEquipmentSeeder::class);
    $user = User::factory()->create(['phone' => '081211110004']);
    $typeId = DB::table('heavy_equipment_types')->value('id');
    $equipmentId = DB::table('heavy_equipments')->insertGetId(['code' => 'ALT-TEST-2', 'name' => 'Alat Arsip 2', 'heavy_equipment_type_id' => $typeId, 'serial_no' => 'SN-ARSIP-2', 'current_hour_meter' => 0, 'ownership' => 'company', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $equipment = DB::table('heavy_equipments')->find($equipmentId);
    $id = DB::table('heavy_equipment_fuelings')->insertGetId(['date' => today(), 'heavy_equipment_id' => $equipment->id, 'fuel_type' => 'Solar', 'liters' => 1, 'price_per_liter' => 1, 'total_cost' => 1, 'hour_meter' => 1, 'created_by' => $user->id, 'updated_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($user)->post(route('admin.transaction-archive.decision', ['module' => 'heavy', 'section' => 'fuel', 'id' => $id]), ['action' => 'approve'])->assertNotFound();
    $this->get(route('admin.transaction-archive.print', ['module' => 'heavy', 'section' => 'fuel', 'id' => $id]))->assertForbidden();
});
