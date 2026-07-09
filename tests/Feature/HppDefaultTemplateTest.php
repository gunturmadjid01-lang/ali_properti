<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\DetailRumahHppItem;
use App\Models\PerumahanHpp;
use App\Models\Perumahan;
use App\Models\TahapanPembangunan;
use App\Models\User;
use App\Services\HppTemplateService;
use Database\Seeders\HppReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('perumahan dan unit baru mendapat salinan template hpp dengan rab nol', function () {
    User::factory()->create(['phone' => '081234567811']);
    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-HPP',
        'nama_cabang' => 'Cabang HPP',
        'address' => 'Alamat',
        'phone' => '081234567812',
        'emaiil' => 'hpp@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $sumber = buatPerumahanHpp($cabang->id, 'SUMBER');
    DetailRumah::query()->create([
        'perumahan_id' => $sumber->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '1',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);
    $this->seed(HppReferenceSeeder::class);

    $perumahanBaru = buatPerumahanHpp($cabang->id, 'BARU');
    $itemsKawasan = $perumahanBaru->perumahanHpps()->firstOrFail()->detailPerumahanHpps;

    expect($itemsKawasan)->toHaveCount(24)
        ->and((float) $itemsKawasan->sum('harga_satuan'))->toBe(0.0)
        ->and((float) $itemsKawasan->sum('jumlah_rab'))->toBe(0.0);

    $unitA = buatUnitHpp($perumahanBaru->id, '1');
    $unitB = buatUnitHpp($perumahanBaru->id, '2');
    $itemsA = DetailRumahHppItem::query()
        ->whereHas('detailRumahHpp', fn ($query) => $query->where('detail_rumah_id', $unitA->id))
        ->get();
    $itemsB = DetailRumahHppItem::query()
        ->whereHas('detailRumahHpp', fn ($query) => $query->where('detail_rumah_id', $unitB->id))
        ->get();

    expect($itemsA)->toHaveCount(44)
        ->and($itemsB)->toHaveCount(44)
        ->and((float) $itemsA->sum('jumlah_rab'))->toBe(0.0)
        ->and((float) $itemsB->sum('jumlah_rab'))->toBe(0.0);

    $itemsA->first()->update(['harga_satuan' => 50000, 'jumlah_rab' => 50000]);

    expect((float) $itemsA->first()->fresh()->jumlah_rab)->toBe(50000.0)
        ->and((float) $itemsB->first()->fresh()->jumlah_rab)->toBe(0.0);
});

test('tahap iv rab bangunan perumahan tidak dapat dihapus', function () {
    $role = Role::findOrCreate('rab_manager', 'web');
    $role->givePermissionTo([
        Permission::findByName('rab-perumahan.delete'),
        Permission::findByName('rab-perumahan.view'),
    ]);

    $user = User::factory()->create(['phone' => '081234567815']);
    $user->assignRole($role);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-HPP2',
        'nama_cabang' => 'Cabang HPP 2',
        'address' => 'Alamat',
        'phone' => '081234567816',
        'emaiil' => 'hpp2@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $perumahan = buatPerumahanHpp($cabang->id, 'WAJIB');
    $this->seed(HppReferenceSeeder::class);

    $stage = TahapanPembangunan::query()
        ->where('perumahan_id', $perumahan->id)
        ->where('konteks', 'kawasan')
        ->where('nama_tahapan', 'IV RAB BANGUNAN')
        ->firstOrFail();

    $this->actingAs($user)
        ->delete(route('admin.management.tahapan-hpp.destroy', $stage))
        ->assertStatus(422);

    expect($stage->fresh())->not->toBeNull();
});

test('rab perumahan bangunan otomatis mengikuti total rab unit per tipe', function () {
    User::factory()->create(['phone' => '081234567817']);
    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-HPP3',
        'nama_cabang' => 'Cabang HPP 3',
        'address' => 'Alamat',
        'phone' => '081234567818',
        'emaiil' => 'hpp3@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $perumahan = buatPerumahanHpp($cabang->id, 'SYNC');
    $this->seed(HppReferenceSeeder::class);

    $units = collect(range(1, 10))->map(fn (int $nomor) => buatUnitHpp($perumahan->id, (string) $nomor));
    $editedUnit = $units->first();
    $item = DetailRumahHppItem::query()
        ->whereHas('detailRumahHpp', fn ($query) => $query->where('detail_rumah_id', $editedUnit->id))
        ->firstOrFail();
    $item->update(['harga_satuan' => 1250000, 'jumlah_rab' => 1250000]);

    app(HppTemplateService::class)->syncBuildingTypeSummary($perumahan->id);

    $buildingItem = PerumahanHpp::query()
        ->where('perumahan_id', $perumahan->id)
        ->firstOrFail()
        ->detailPerumahanHpps()
        ->where('nama_pekerjaan', 'Bangunan Rumah Type 36')
        ->firstOrFail();

    expect((float) $buildingItem->volume)->toBe(10.0)
        ->and((float) $buildingItem->harga_satuan)->toBe(1250000.0)
        ->and((float) $buildingItem->jumlah_rab)->toBe(12500000.0);
});

function buatPerumahanHpp(int $cabangId, string $kode): Perumahan
{
    return Perumahan::query()->create([
        'cabang_id' => $cabangId,
        'kode_proyek' => "PRJ-{$kode}",
        'nama_perusahaan' => "Perumahan {$kode}",
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 2,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
}

function buatUnitHpp(int $perumahanId, string $nomor): DetailRumah
{
    return DetailRumah::query()->create([
        'perumahan_id' => $perumahanId,
        'kode_nlok' => 'B',
        'nomor_rumah' => $nomor,
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);
}
