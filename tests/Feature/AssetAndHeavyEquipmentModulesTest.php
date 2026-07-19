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
    $this->user->givePermissionTo(Permission::query()->where(fn ($query) => $query->where('name', 'like', 'company-inventory.%.%')->orWhere('name', 'like', 'heavy-equipment.%.%'))->get());
    $this->actingAs($this->user);
});

test('permission inventaris dan alat berat terpisah untuk setiap halaman', function () {
    $this->user->syncPermissions([
        Permission::findOrCreate('company-inventory.units.view', 'web'),
        Permission::findOrCreate('heavy-equipment.fuel.view', 'web'),
    ]);

    $this->get(route('admin.company-inventory.index', ['section' => 'units']))->assertOk();
    $this->get(route('admin.company-inventory.index', ['section' => 'locations']))->assertForbidden();
    $this->get(route('admin.heavy-equipment.index', ['section' => 'fuel']))->assertOk();
    $this->get(route('admin.heavy-equipment.index', ['section' => 'usage']))->assertForbidden();
});

test('form inventaris perusahaan menggunakan halaman khusus untuk tambah dan edit', function () {
    $this->get(route('admin.company-inventory.create', ['section' => 'categories']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsModule/Form')
            ->where('module', 'inventory')
            ->where('section', 'categories')
            ->where('method', 'post')
            ->where('row', null));

    $categoryId = DB::table('inventory_categories')->value('id');

    $this->get(route('admin.company-inventory.edit', ['section' => 'categories', 'id' => $categoryId]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsModule/Form')
            ->where('module', 'inventory')
            ->where('section', 'categories')
            ->where('method', 'put')
            ->where('row.id', $categoryId));
});

test('form alat berat menggunakan halaman khusus untuk tambah dan edit', function () {
    $this->get(route('admin.heavy-equipment.create', ['section' => 'types']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsModule/Form')
            ->where('module', 'heavy')
            ->where('section', 'types')
            ->where('method', 'post')
            ->where('row', null));

    $typeId = DB::table('heavy_equipment_types')->value('id');

    $this->get(route('admin.heavy-equipment.edit', ['section' => 'types', 'id' => $typeId]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsModule/Form')
            ->where('module', 'heavy')
            ->where('section', 'types')
            ->where('method', 'put')
            ->where('row.id', $typeId));
});

test('kode seluruh transaksi inventaris dibuat otomatis ketika tidak diisi', function () {
    $categoryId = DB::table('inventory_categories')->value('id');
    $initialLocationId = DB::table('inventory_locations')->value('id');

    $this->post(route('admin.company-inventory.store', ['section' => 'locations']), [
        'name' => 'Gudang Otomatis', 'type' => 'warehouse', 'address' => 'Lokasi pengujian',
    ])->assertSessionHasNoErrors();
    $newLocation = DB::table('inventory_locations')->where('name', 'Gudang Otomatis')->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'items']), [
        'name' => 'Aset Otomatis', 'inventory_category_id' => $categoryId, 'unit' => 'Unit',
        'inventory_type' => 'unit', 'minimum_stock' => 0, 'total_stock' => 2, 'available_stock' => 2,
    ])->assertSessionHasNoErrors();
    $item = DB::table('inventory_items')->where('name', 'Aset Otomatis')->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'units']), [
        'inventory_item_id' => $item->id, 'nomor_seri' => 'SERIAL-AUTO-001',
        'inventory_location_id' => $initialLocationId, 'status' => 'available', 'condition' => 'good',
    ])->assertSessionHasNoErrors();
    $asset = DB::table('office_assets')->where('nomor_seri', 'SERIAL-AUTO-001')->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'loans']), [
        'date' => now()->toDateString(), 'borrower' => 'Peminjam Otomatis',
        'inventory_location_id' => $initialLocationId, 'inventory_item_id' => $item->id,
        'office_asset_id' => $asset->id, 'quantity' => 1, 'purpose' => 'Pengujian kode otomatis',
    ])->assertSessionHasNoErrors();
    $loan = DB::table('inventory_loans')->where('borrower', 'Peminjam Otomatis')->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'returns']), [
        'inventory_loan_id' => $loan->id, 'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    $return = DB::table('inventory_returns')->where('inventory_loan_id', $loan->id)->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'transfers']), [
        'date' => now()->toDateString(), 'inventory_item_id' => $item->id,
        'office_asset_id' => $asset->id, 'quantity' => 1, 'from_location_id' => $initialLocationId,
        'to_location_id' => $newLocation->id, 'reason' => 'Pengujian kode otomatis',
    ])->assertSessionHasNoErrors();
    $transfer = DB::table('inventory_transfers')->where('reason', 'Pengujian kode otomatis')->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'stock-opname']), [
        'date' => now()->toDateString(), 'inventory_location_id' => $newLocation->id,
        'inventory_item_id' => $item->id, 'physical_quantity' => 2,
    ])->assertSessionHasNoErrors();
    $opname = DB::table('inventory_stock_opnames')->latest('id')->first();

    $this->get(route('admin.company-inventory.create', ['section' => 'loans']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.units', fn ($units) => collect($units)->contains(fn ($unit) => $unit['value'] === (string) $asset->id
                && $unit['inventory_item_id'] === (string) $item->id
                && $unit['inventory_location_id'] === (string) $newLocation->id
            )));

    $this->get(route('admin.company-inventory.index', ['section' => 'loans']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.item_summary', fn ($value) => str_contains($value, $item->name) && str_contains($value, $asset->kode_aset))
            ->where('rows.data.0.inventory_location_id', fn ($value) => str_contains($value, 'Gudang Utama')));

    expect($newLocation->code)->toMatch('/^LOK-\d{5}$/')
        ->and($item->code)->toMatch('/^BRG-\d{5}$/')
        ->and($asset->kode_aset)->toMatch('/^AST-\d{5}$/')
        ->and($loan->transaction_no)->toMatch('/^PJM-\d{5}$/')
        ->and($return->return_no)->toMatch('/^KMB-\d{5}$/')
        ->and($transfer->transaction_no)->toMatch('/^MUT-\d{5}$/')
        ->and($opname->opname_no)->toMatch('/^SO-\d{5}$/');
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
        'code' => 'INV-UNIT-001', 'name' => 'Laptop Proyek', 'inventory_category_id' => $categoryId, 'unit' => 'Unit',
        'inventory_type' => 'unit', 'minimum_stock' => 0, 'total_stock' => 1, 'available_stock' => 1,
    ])->assertSessionHasNoErrors();
    $itemId = DB::table('inventory_items')->where('code', 'INV-UNIT-001')->value('id');
    $this->post(route('admin.company-inventory.store', ['section' => 'units']), [
        'inventory_item_id' => $itemId, 'kode_aset' => 'AST-001', 'nomor_seri' => 'SERIAL-001',
        'inventory_location_id' => $locationId, 'status' => 'available', 'condition' => 'good',
    ])->assertSessionHasNoErrors();
    $assetId = DB::table('office_assets')->where('kode_aset', 'AST-001')->value('id');

    $this->post(route('admin.company-inventory.store', ['section' => 'loans']), [
        'transaction_no' => 'PJM-UNIT-001', 'date' => now()->toDateString(), 'borrower' => 'Site Manager',
        'inventory_location_id' => $locationId, 'inventory_item_id' => $itemId, 'office_asset_id' => $assetId,
        'quantity' => 1, 'purpose' => 'Monitoring proyek',
    ])->assertSessionHasNoErrors();
    expect(DB::table('office_assets')->find($assetId)->status)->toBe('borrowed');

    $loanId = DB::table('inventory_loans')->where('transaction_no', 'PJM-UNIT-001')->value('id');
    $this->post(route('admin.company-inventory.store', ['section' => 'returns']), [
        'return_no' => 'KMB-UNIT-001', 'inventory_loan_id' => $loanId, 'date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect(DB::table('office_assets')->find($assetId)->status)->toBe('available');

    $this->get(route('admin.company-inventory.export', ['section' => 'items', 'format' => 'pdf']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('akun gudang menerima menu modul dari permission role tanpa sidebar khusus role', function () {
    $role = Role::findOrCreate('user_area_gudang', 'web');
    $permission = Permission::findOrCreate('company-inventory.dashboard.view', 'web');
    $role->givePermissionTo($permission);
    $gudang = User::factory()->create(['email' => 'gudang-sidebar@example.com', 'phone' => '081288880099']);
    $gudang->assignRole($role);

    $this->actingAs($gudang)
        ->get(route('admin.company-inventory.index', ['section' => 'dashboard']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OperationsModule/Index')
            ->where('auth.user.roles.0', 'user_area_gudang')
            ->where('auth.user.permissions.0', 'company-inventory.dashboard.view'));
});

test('pusat laporan menyediakan laporan aset dan alat berat dengan dataset cetak yang sama', function () {
    foreach (['laporan.view', 'laporan.export', 'laporan-master-data.view'] as $name) {
        $this->user->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    $this->get(route('admin.reports.show', ['group' => 'aset-perusahaan', 'jenis_laporan' => 'daftar-aset']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Show')
            ->where('group', 'aset-perusahaan')
            ->where('selectedType', 'daftar-aset'));

    $this->get(route('admin.reports.show', ['group' => 'alat-berat', 'jenis_laporan' => 'daftar-alat']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Show')
            ->where('group', 'alat-berat')
            ->where('selectedType', 'daftar-alat'));

    $this->get(route('admin.reports.print', ['group' => 'alat-berat', 'jenis_laporan' => 'daftar-alat']))
        ->assertOk()
        ->assertSee('Daftar dan Status Alat Berat');
});

test('alat berat menjaga status komponen hour meter dan total bbm', function () {
    $typeId = DB::table('heavy_equipment_types')->where('name', 'Excavator')->value('id');
    $this->post(route('admin.heavy-equipment.store', ['section' => 'operators']), ['name' => 'Operator Satu', 'phone' => '0812', 'identity_no' => 'OP-001', 'certification' => 'SIO'])->assertSessionHasNoErrors();
    $operatorId = DB::table('heavy_equipment_operators')->value('id');
    $this->post(route('admin.heavy-equipment.store', ['section' => 'equipment']), ['code' => 'EQ-001', 'name' => 'Excavator 01', 'heavy_equipment_type_id' => $typeId, 'brand' => 'Komatsu', 'model' => 'PC200', 'year' => 2024, 'serial_no' => 'SN-EQ-001', 'current_hour_meter' => 100, 'ownership' => 'company', 'status' => 'active'])->assertSessionHasNoErrors();
    $equipmentId = DB::table('heavy_equipments')->value('id');
    foreach ([['CMP-OLD', 'Bucket Lama', 'SN-CMP-OLD', 'installed', $equipmentId], ['CMP-NEW', 'Bucket Baru', 'SN-CMP-NEW', 'available', null]] as [$code,$name,$serial,$status,$equipment]) {
        $this->post(route('admin.heavy-equipment.store', ['section' => 'components']), ['code' => $code, 'name' => $name, 'heavy_equipment_type_id' => $typeId, 'heavy_equipment_id' => $equipment, 'component_type' => 'Bucket', 'serial_no' => $serial, 'condition' => 'good', 'status' => $status])->assertSessionHasNoErrors();
    }
    $oldId = DB::table('heavy_equipment_components')->where('code', 'CMP-OLD')->value('id');
    $newId = DB::table('heavy_equipment_components')->where('code', 'CMP-NEW')->value('id');
    $this->post(route('admin.heavy-equipment.store', ['section' => 'replacements']), ['transaction_no' => 'RPL-001', 'date' => now()->toDateString(), 'heavy_equipment_id' => $equipmentId, 'old_component_id' => $oldId, 'new_component_id' => $newId, 'hour_meter' => 105, 'reason' => 'Upgrade bucket', 'operator_id' => $operatorId, 'technician' => 'Teknisi', 'old_component_condition' => 'worn', 'old_component_status' => 'service'])->assertSessionHasNoErrors();
    expect(DB::table('heavy_equipment_components')->find($oldId)->status)->toBe('service')
        ->and(DB::table('heavy_equipment_components')->find($newId)->status)->toBe('installed')
        ->and(DB::table('heavy_equipment_components')->find($newId)->heavy_equipment_id)->toBe($equipmentId);

    $this->post(route('admin.heavy-equipment.store', ['section' => 'usage']), ['transaction_no' => 'USE-001', 'date' => now()->toDateString(), 'heavy_equipment_id' => $equipmentId, 'operator_id' => $operatorId, 'project' => 'Proyek A', 'hour_meter_start' => 105, 'hour_meter_end' => 110])->assertSessionHasNoErrors();
    expect((float) DB::table('heavy_equipments')->find($equipmentId)->current_hour_meter)->toBe(110.0)
        ->and(DB::table('heavy_equipments')->find($equipmentId)->status)->toBe('active');

    $this->post(route('admin.heavy-equipment.store', ['section' => 'fuel']), ['date' => now()->toDateString(), 'heavy_equipment_id' => $equipmentId, 'fuel_type' => 'Solar', 'liters' => 50, 'price_per_liter' => 15000, 'hour_meter' => 110])->assertSessionHasNoErrors();
    expect((float) DB::table('heavy_equipment_fuelings')->value('total_cost'))->toBe(750000.0);
    $this->get(route('admin.heavy-equipment.create', ['section' => 'replacements']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.components', fn ($components) => collect($components)->contains(fn ($component) => $component['value'] === (string) $newId
                && $component['heavy_equipment_type_id'] === (string) $typeId
                && $component['status'] === 'installed'
            )));
    $this->get(route('admin.heavy-equipment.index', ['section' => 'components']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['heavy_equipment_type_id'] === 'Excavator'
                && ($row['heavy_equipment_id'] ?? '') === 'Excavator 01'
            )));
    $this->get(route('admin.heavy-equipment.show', $equipmentId))->assertOk();
});

test('pengambilan multi barang dan pengembalian parsial menjaga stok ledger serta pertanggungjawaban', function () {
    $categoryId = DB::table('inventory_categories')->value('id');
    $sourceLocationId = DB::table('inventory_locations')->value('id');
    $destinationLocationId = DB::table('inventory_locations')->where('id', '!=', $sourceLocationId)->value('id') ?? $sourceLocationId;

    $this->post(route('admin.company-inventory.store', ['section' => 'items']), [
        'name' => 'Kursi Proyek', 'inventory_category_id' => $categoryId, 'unit' => 'Buah',
        'inventory_type' => 'quantity', 'minimum_stock' => 2, 'total_stock' => 10, 'available_stock' => 10,
    ])->assertSessionHasNoErrors();
    $quantityItem = DB::table('inventory_items')->where('name', 'Kursi Proyek')->first();

    $this->post(route('admin.company-inventory.store', ['section' => 'items']), [
        'name' => 'LCD Proyek', 'inventory_category_id' => $categoryId, 'unit' => 'Unit',
        'inventory_type' => 'unit', 'minimum_stock' => 0, 'total_stock' => 99, 'available_stock' => 99,
    ])->assertSessionHasNoErrors();
    $unitItem = DB::table('inventory_items')->where('name', 'LCD Proyek')->first();
    $this->post(route('admin.company-inventory.store', ['section' => 'units']), [
        'inventory_item_id' => $unitItem->id, 'nomor_seri' => 'SERIAL-LCD-ERP-01',
        'inventory_location_id' => $sourceLocationId, 'condition' => 'good',
    ])->assertSessionHasNoErrors();
    $asset = DB::table('office_assets')->where('nomor_seri', 'SERIAL-LCD-ERP-01')->first();

    expect(DB::table('inventory_items')->find($unitItem->id)->total_stock)->toBe(1)
        ->and(DB::table('inventory_items')->find($unitItem->id)->available_stock)->toBe(1);

    $this->post(route('admin.company-inventory.store', ['section' => 'loans']), [
        'transaction_type' => 'loan', 'date' => now()->toDateString(),
        'borrower' => 'Pengawas Proyek', 'taken_by_name' => 'Rahmat', 'taken_by_phone' => '08123456789',
        'source_location_id' => $sourceLocationId, 'inventory_location_id' => $destinationLocationId,
        'planned_return_date' => now()->addWeek()->toDateString(), 'purpose' => 'Operasional kantor proyek',
        'items' => [
            ['inventory_item_id' => $quantityItem->id, 'quantity' => 3, 'condition_out' => 'good'],
            ['inventory_item_id' => $unitItem->id, 'office_asset_id' => $asset->id, 'quantity' => 1, 'condition_out' => 'good'],
        ],
    ])->assertSessionHasNoErrors();

    $loan = DB::table('inventory_loans')->where('taken_by_name', 'Rahmat')->first();
    $quantityLine = DB::table('inventory_loan_items')->where('inventory_loan_id', $loan->id)->where('inventory_item_id', $quantityItem->id)->first();
    $unitLine = DB::table('inventory_loan_items')->where('inventory_loan_id', $loan->id)->where('inventory_item_id', $unitItem->id)->first();
    expect(DB::table('inventory_loan_items')->where('inventory_loan_id', $loan->id)->count())->toBe(2)
        ->and(DB::table('inventory_items')->find($quantityItem->id)->available_stock)->toBe(7)
        ->and(DB::table('office_assets')->find($asset->id)->status)->toBe('borrowed')
        ->and(DB::table('inventory_movements')->where('reference_id', $loan->id)->where('reference_type', 'inventory_loan')->count())->toBe(2);

    $this->get(route('admin.company-inventory.create', ['section' => 'loans']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/LoanForm'));
    $this->get(route('admin.company-inventory.create', ['section' => 'returns', 'loan' => $loan->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/ReturnForm')
            ->where('selectedLoanId', (string) $loan->id)
            ->where('transactions.0.items', fn ($items) => count($items) === 2));

    $this->post(route('admin.company-inventory.store', ['section' => 'returns']), [
        'inventory_loan_id' => $loan->id, 'date' => now()->toDateString(), 'return_location_id' => $sourceLocationId,
        'items' => [
            ['loan_item_id' => $quantityLine->id, 'good_quantity' => 2, 'damaged_quantity' => 0, 'lost_quantity' => 0, 'condition_in' => 'good'],
            ['loan_item_id' => $unitLine->id, 'good_quantity' => 0, 'damaged_quantity' => 0, 'lost_quantity' => 1, 'condition_in' => 'lost', 'responsible_person' => 'Rahmat'],
        ],
    ])->assertSessionHasNoErrors();

    expect(DB::table('inventory_loans')->find($loan->id)->status)->toBe('partially_returned')
        ->and(DB::table('inventory_items')->find($quantityItem->id)->available_stock)->toBe(9)
        ->and(DB::table('inventory_items')->find($quantityItem->id)->borrowed_stock)->toBe(1)
        ->and(DB::table('office_assets')->find($asset->id)->status)->toBe('lost');

    $this->post(route('admin.company-inventory.store', ['section' => 'returns']), [
        'inventory_loan_id' => $loan->id, 'date' => now()->toDateString(), 'return_location_id' => $sourceLocationId,
        'items' => [['loan_item_id' => $quantityLine->id, 'good_quantity' => 1, 'damaged_quantity' => 0, 'lost_quantity' => 0, 'condition_in' => 'good']],
    ])->assertSessionHasNoErrors();

    expect(DB::table('inventory_loans')->find($loan->id)->status)->toBe('closed_with_loss')
        ->and(DB::table('inventory_items')->find($quantityItem->id)->available_stock)->toBe(10)
        ->and(DB::table('inventory_items')->find($quantityItem->id)->borrowed_stock)->toBe(0);

    $this->get(route('admin.company-inventory.index', ['section' => 'reports', 'preset' => 'today', 'inventory_item_id' => $quantityItem->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Reports')
            ->where('transactions.0.taken_by_name', 'Rahmat')
            ->where('transactions.0.items', fn ($items) => collect($items)->contains(fn ($item) => $item['item_name'] === 'Kursi Proyek')));

    $this->get(route('admin.company-inventory.index', ['section' => 'ledger', 'inventory_item_id' => $quantityItem->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Ledger')
            ->where('rows.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['item_name'] === 'Kursi Proyek')));
});
