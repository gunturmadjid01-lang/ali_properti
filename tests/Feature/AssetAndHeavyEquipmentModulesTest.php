<?php

use App\Models\User;
use Database\Seeders\InventoryHeavyEquipmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InventoryHeavyEquipmentSeeder::class);
    $this->user = User::factory()->create(['phone' => '081288880001']);
    $this->user->assignRole(Role::findOrCreate('manajer_pimpro', 'web'));
    $this->actingAs($this->user);
});

test('inventaris perusahaan memperbarui stok saat pinjam kembali dan verifikasi opname', function () {
    $categoryId = DB::table('inventory_categories')->value('id');
    $locationId = DB::table('inventory_locations')->value('id');

    $this->post(route('admin.company-inventory.store', ['section' => 'items']), [
        'code' => 'INV-001', 'name' => 'Sekop', 'inventory_category_id' => $categoryId,
        'unit' => 'Buah', 'inventory_type' => 'quantity', 'minimum_stock' => 2,
        'total_stock' => 10, 'available_stock' => 10,
    ])->assertSessionHasNoErrors();
    $itemId = DB::table('inventory_items')->where('code', 'INV-001')->value('id');

    $this->post(route('admin.company-inventory.store', ['section' => 'loans']), [
        'transaction_no' => 'PJM-001', 'date' => now()->toDateString(), 'borrower' => 'Petugas Lapangan',
        'division' => 'Teknik', 'inventory_location_id' => $locationId, 'inventory_item_id' => $itemId,
        'quantity' => 3, 'planned_return_date' => now()->addDay()->toDateString(), 'purpose' => 'Pekerjaan lapangan',
    ])->assertSessionHasNoErrors();
    expect(DB::table('inventory_items')->find($itemId)->available_stock)->toBe(7)
        ->and(DB::table('inventory_items')->find($itemId)->borrowed_stock)->toBe(3);

    $loanId = DB::table('inventory_loans')->where('transaction_no', 'PJM-001')->value('id');
    $this->post(route('admin.company-inventory.store', ['section' => 'returns']), [
        'return_no' => 'KMB-001', 'inventory_loan_id' => $loanId, 'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect(DB::table('inventory_items')->find($itemId)->available_stock)->toBe(10)
        ->and(DB::table('inventory_items')->find($itemId)->borrowed_stock)->toBe(0);

    $this->post(route('admin.company-inventory.store', ['section' => 'stock-opname']), [
        'opname_no' => 'SO-001', 'date' => now()->toDateString(), 'inventory_location_id' => $locationId,
        'inventory_item_id' => $itemId, 'physical_quantity' => 8,
    ])->assertSessionHasNoErrors();
    expect(DB::table('inventory_items')->find($itemId)->total_stock)->toBe(10);
    $opnameId = DB::table('inventory_stock_opnames')->where('opname_no', 'SO-001')->value('id');
    $this->post(route('admin.company-inventory.stock-opname.verify', $opnameId))->assertSessionHasNoErrors();
    expect(DB::table('inventory_items')->find($itemId)->total_stock)->toBe(8)
        ->and(DB::table('inventory_stock_opnames')->find($opnameId)->status)->toBe('verified');
});

test('inventaris berbasis unit melacak unit fisik dan menghasilkan ekspor pdf', function () {
    $categoryId = DB::table('inventory_categories')->value('id');
    $locationId = DB::table('inventory_locations')->value('id');

    $this->post(route('admin.company-inventory.store', ['section' => 'items']), [
        'code'=>'INV-UNIT-001','name'=>'Laptop Proyek','inventory_category_id'=>$categoryId,'unit'=>'Unit',
        'inventory_type'=>'unit','minimum_stock'=>0,'total_stock'=>1,'available_stock'=>1,
    ])->assertSessionHasNoErrors();
    $itemId = DB::table('inventory_items')->where('code','INV-UNIT-001')->value('id');
    $this->post(route('admin.company-inventory.store', ['section' => 'units']), [
        'inventory_item_id'=>$itemId,'kode_aset'=>'AST-001','nomor_seri'=>'SERIAL-001',
        'inventory_location_id'=>$locationId,'status'=>'available','condition'=>'good',
    ])->assertSessionHasNoErrors();
    $assetId = DB::table('office_assets')->where('kode_aset','AST-001')->value('id');

    $this->post(route('admin.company-inventory.store', ['section' => 'loans']), [
        'transaction_no'=>'PJM-UNIT-001','date'=>now()->toDateString(),'borrower'=>'Site Manager',
        'inventory_location_id'=>$locationId,'inventory_item_id'=>$itemId,'office_asset_id'=>$assetId,
        'quantity'=>1,'purpose'=>'Monitoring proyek',
    ])->assertSessionHasNoErrors();
    expect(DB::table('office_assets')->find($assetId)->status)->toBe('borrowed');

    $loanId = DB::table('inventory_loans')->where('transaction_no','PJM-UNIT-001')->value('id');
    $this->post(route('admin.company-inventory.store', ['section' => 'returns']), [
        'return_no'=>'KMB-UNIT-001','inventory_loan_id'=>$loanId,'date'=>now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect(DB::table('office_assets')->find($assetId)->status)->toBe('available');

    $this->get(route('admin.company-inventory.export', ['section'=>'items','format'=>'pdf']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('akun gudang menerima menu modul dari permission role tanpa sidebar khusus role', function () {
    $role = Role::findOrCreate('user_area_gudang', 'web');
    $permission = Permission::findOrCreate('company-inventory.view', 'web');
    $role->givePermissionTo($permission);
    $gudang = User::factory()->create(['email'=>'gudang-sidebar@example.com','phone'=>'081288880099']);
    $gudang->assignRole($role);

    $this->actingAs($gudang)
        ->get(route('admin.company-inventory.index', ['section'=>'dashboard']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsModule/Index')
            ->where('auth.user.roles.0', 'user_area_gudang')
            ->where('auth.user.permissions.0', 'company-inventory.view'));
});

test('alat berat menjaga status komponen hour meter dan total bbm', function () {
    $typeId = DB::table('heavy_equipment_types')->where('name', 'Excavator')->value('id');
    $this->post(route('admin.heavy-equipment.store', ['section' => 'operators']), ['name'=>'Operator Satu','phone'=>'0812','identity_no'=>'OP-001','certification'=>'SIO'])->assertSessionHasNoErrors();
    $operatorId = DB::table('heavy_equipment_operators')->value('id');
    $this->post(route('admin.heavy-equipment.store', ['section' => 'equipment']), ['code'=>'EQ-001','name'=>'Excavator 01','heavy_equipment_type_id'=>$typeId,'brand'=>'Komatsu','model'=>'PC200','year'=>2024,'serial_no'=>'SN-EQ-001','current_hour_meter'=>100,'ownership'=>'company','status'=>'active'])->assertSessionHasNoErrors();
    $equipmentId = DB::table('heavy_equipments')->value('id');
    foreach ([['CMP-OLD','Bucket Lama','SN-CMP-OLD','installed',$equipmentId],['CMP-NEW','Bucket Baru','SN-CMP-NEW','available',null]] as [$code,$name,$serial,$status,$equipment]) {
        $this->post(route('admin.heavy-equipment.store', ['section' => 'components']), ['code'=>$code,'name'=>$name,'heavy_equipment_type_id'=>$typeId,'heavy_equipment_id'=>$equipment,'component_type'=>'Bucket','serial_no'=>$serial,'condition'=>'good','status'=>$status])->assertSessionHasNoErrors();
    }
    $oldId=DB::table('heavy_equipment_components')->where('code','CMP-OLD')->value('id');$newId=DB::table('heavy_equipment_components')->where('code','CMP-NEW')->value('id');
    $this->post(route('admin.heavy-equipment.store', ['section' => 'replacements']), ['transaction_no'=>'RPL-001','date'=>now()->toDateString(),'heavy_equipment_id'=>$equipmentId,'old_component_id'=>$oldId,'new_component_id'=>$newId,'hour_meter'=>105,'reason'=>'Upgrade bucket','operator_id'=>$operatorId,'technician'=>'Teknisi','old_component_condition'=>'worn','old_component_status'=>'service'])->assertSessionHasNoErrors();
    expect(DB::table('heavy_equipment_components')->find($oldId)->status)->toBe('service')
        ->and(DB::table('heavy_equipment_components')->find($newId)->status)->toBe('installed')
        ->and(DB::table('heavy_equipment_components')->find($newId)->heavy_equipment_id)->toBe($equipmentId);

    $this->post(route('admin.heavy-equipment.store', ['section' => 'usage']), ['transaction_no'=>'USE-001','date'=>now()->toDateString(),'heavy_equipment_id'=>$equipmentId,'operator_id'=>$operatorId,'project'=>'Proyek A','hour_meter_start'=>105,'hour_meter_end'=>110])->assertSessionHasNoErrors();
    expect((float) DB::table('heavy_equipments')->find($equipmentId)->current_hour_meter)->toBe(110.0)
        ->and(DB::table('heavy_equipments')->find($equipmentId)->status)->toBe('active');

    $this->post(route('admin.heavy-equipment.store', ['section' => 'fuel']), ['date'=>now()->toDateString(),'heavy_equipment_id'=>$equipmentId,'fuel_type'=>'Solar','liters'=>50,'price_per_liter'=>15000,'hour_meter'=>110])->assertSessionHasNoErrors();
    expect((float) DB::table('heavy_equipment_fuelings')->value('total_cost'))->toBe(750000.0);
    $this->get(route('admin.heavy-equipment.show', $equipmentId))->assertOk();
});
