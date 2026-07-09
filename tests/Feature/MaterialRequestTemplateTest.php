<?php

use App\Models\BarangMaterial;
use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\DetailRumahHpp;
use App\Models\DetailRumahHppItem;
use App\Models\Gudang;
use App\Models\KelompokHpp;
use App\Models\Perumahan;
use App\Models\TahapanPembangunan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('template permintaan barang mengikuti hpp unit dan menghitung estimasi progres', function () {
    $user = User::factory()->create(['phone' => '081234567820']);
    $user->givePermissionTo(Permission::findOrCreate('material-request.create', 'web'));
    $this->actingAs($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-TPL',
        'nama_cabang' => 'Cabang Template',
        'address' => 'Alamat',
        'phone' => '0800000001',
        'emaiil' => 'template@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-TPL',
        'nama_perusahaan' => 'Perumahan Template',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '1',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);
    $tahapan = TahapanPembangunan::query()->create([
        'nama_tahapan' => 'I PERSIAPAN',
        'bobot_persen' => 25,
        'urutan' => 1,
        'status' => 'aktif',
        'konteks' => 'unit',
        'detail_rumah_id' => $unit->id,
    ]);
    $material = BarangMaterial::query()->create([
        'kode_barang' => 'MAT-TPL',
        'nama_barang' => 'Semen',
        'satuan' => 'sak',
        'harga_hpp' => 50000,
        'status' => 'aktif',
    ]);
    $kelompok = KelompokHpp::query()->create([
        'nama_hpp' => 'Material Struktur',
        'kategori' => 'material',
        'status' => 'aktif',
    ]);
    $detailHpp = DetailRumahHpp::query()->create([
        'detail_rumah_id' => $unit->id,
        'user_id' => $user->id,
        'tanggal_dibuat' => now()->toDateString(),
    ]);
    DetailRumahHppItem::query()->create([
        'detail_rumah_hpp_id' => $detailHpp->id,
        'kelompok_hpp_id' => $kelompok->id,
        'tahapan_pembangunan_id' => $tahapan->id,
        'nama_pekerjaan' => 'Semen cor',
        'barang_material_id' => $material->id,
        'volume' => 10,
        'satuan' => 'sak',
        'harga_satuan' => 50000,
        'jumlah_rab' => 500000,
        'urutan' => 1,
    ]);

    $this->getJson(route('admin.material-request.templates', [
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'tahapan_pembangunan_id' => $tahapan->id,
    ]))
        ->assertOk()
        ->assertJsonPath('data.scope', 'unit')
        ->assertJsonPath('data.perumahan_id', (string) $perumahan->id)
        ->assertJsonPath('data.detail_rumah_id', (string) $unit->id)
        ->assertJsonPath('data.tahapan_pembangunan_id', (string) $tahapan->id)
        ->assertJsonPath('data.items.0.barang_material_id', (string) $material->id)
        ->assertJsonPath('data.items.0.jumlah_rab', 500000)
        ->assertJsonPath('data.total_rab', 500000)
        ->assertJsonPath('data.estimated_progress', 100);
});

test('permintaan barang bisa disimpan tanpa kelompok hpp ketika template tidak tunggal', function () {
    $user = User::factory()->create(['phone' => '081234567821']);
    $user->givePermissionTo(Permission::findOrCreate('material-request.create', 'web'));
    $this->actingAs($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-REQ',
        'nama_cabang' => 'Cabang Request',
        'address' => 'Alamat',
        'phone' => '0800000002',
        'emaiil' => 'request@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-REQ',
        'nama_perusahaan' => 'Perumahan Request',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'B',
        'nomor_rumah' => '2',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);
    $gudang = Gudang::query()->create([
        'kode_gudang' => 'GD-REQ',
        'nama_gudang' => 'Gudang Request',
        'perumahan_id' => $perumahan->id,
        'status' => 'aktif',
    ]);
    $material = BarangMaterial::query()->create([
        'kode_barang' => 'MAT-REQ',
        'nama_barang' => 'Besi',
        'satuan' => 'batang',
        'harga_hpp' => 100000,
        'status' => 'aktif',
    ]);

    $this->post(route('admin.material-request.store'), [
        'tanggal' => now()->toDateString(),
        'gudang_id' => $gudang->id,
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'items' => [[
            'barang_material_id' => $material->id,
            'qty' => 3,
            'satuan' => 'batang',
        ]],
    ])->assertRedirect();

    $this->assertDatabaseHas('material_requests', [
        'gudang_id' => $gudang->id,
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'kelompok_hpp_id' => null,
    ]);
});
