<?php

use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\User;
use App\Services\UnitOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ownershipFixture(): array
{
    $user = User::factory()->create(['phone' => '081111111111']);
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-OWN', 'nama_cabang' => 'Cabang Ownership', 'address' => '-', 'phone' => '-',
        'emaiil' => 'ownership@example.test', 'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id, 'nama_perusahaan' => 'Proyek Ownership', 'alamat' => '-',
        'luas_lahan' => '1000', 'jumlah_unit' => 1, 'tanggal_mulai' => '2020-01-01', 'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $project->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01',
        'tipe_rumah' => '36/78', 'luas_tanah' => '78', 'status' => 'aktif',
    ]);
    $customer = Costumer::query()->create([
        'kode_costumer' => 'CST-OWN-1', 'perumahan_id' => $project->id, 'nama' => 'Pemilik Pertama',
        'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => '73710001',
        'status_perkawinan' => 'menikah', 'alamat' => 'Alamat pertama', 'telepon' => '0811',
    ]);

    return compact('user', 'project', 'unit', 'customer');
}

test('legacy ownership marks unit sold and keeps ownership history', function () {
    ['user' => $user, 'project' => $project, 'unit' => $unit, 'customer' => $firstCustomer] = ownershipFixture();
    $secondCustomer = Costumer::query()->create([
        'kode_costumer' => 'CST-OWN-2', 'perumahan_id' => $project->id, 'nama' => 'Pemilik Kedua',
        'jenis_kelamin' => 'P', 'jenis_identitas' => 'KTP', 'no_identitas' => '73710002',
        'status_perkawinan' => 'belum_menikah', 'alamat' => 'Alamat kedua',
    ]);
    $service = app(UnitOwnershipService::class);

    $first = $service->createLegacy([
        'detail_rumah_id' => $unit->id, 'owner_name' => $firstCustomer->nama,
        'identity_type' => 'KTP', 'identity_number' => $firstCustomer->no_identitas,
        'address' => $firstCustomer->alamat, 'acquired_at' => '2020-01-10',
        'acquisition_method' => 'data_lama',
    ], $firstCustomer, $user->id);
    $second = $service->createLegacy([
        'detail_rumah_id' => $unit->id, 'owner_name' => $secondCustomer->nama,
        'identity_type' => 'KTP', 'identity_number' => $secondCustomer->no_identitas,
        'address' => $secondCustomer->alamat, 'acquired_at' => '2024-02-01',
        'acquisition_method' => 'data_lama',
    ], $secondCustomer, $user->id);

    expect($unit->fresh()->status_penjualan)->toBe('terjual')
        ->and($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue()
        ->and($unit->fresh()->currentOwnership?->owner_name)->toBe('Pemilik Kedua');

    $service->deactivate($second->fresh('detailRumah'), $user->id, '2026-01-01');

    expect($first->fresh()->is_active)->toBeTrue()
        ->and($unit->fresh()->currentOwnership?->owner_name)->toBe('Pemilik Pertama');
});

test('super admin can input legacy owner from ownership page', function () {
    ['user' => $user, 'unit' => $unit] = ownershipFixture();
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');

    $this->actingAs($user)->get('/admin/pemilik-unit')->assertOk();
    $this->post('/admin/pemilik-unit', [
        'detail_rumah_id' => $unit->id,
        'owner_name' => 'Pemilik Data Lama',
        'identity_type' => 'KTP',
        'identity_number' => '7371999999990001',
        'phone' => '081234567890',
        'email' => 'pemilik@example.test',
        'address' => 'Alamat pemilik lama',
        'acquisition_method' => 'data_lama',
        'acquired_at' => '2019-05-10',
        'document_number' => 'DOC-LAMA-001',
    ])->assertRedirect();

    $this->assertDatabaseHas('unit_ownerships', [
        'detail_rumah_id' => $unit->id,
        'owner_name' => 'Pemilik Data Lama',
        'identity_number' => '7371999999990001',
        'source_type' => 'legacy',
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('costumers', [
        'nama' => 'Pemilik Data Lama',
        'no_identitas' => '7371999999990001',
    ]);
});
