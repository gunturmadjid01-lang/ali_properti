<?php

use App\Models\BarangMaterial;
use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Gudang;
use App\Models\HppRealisasi;
use App\Models\KelompokHpp;
use App\Models\MaterialRequest;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseRequest;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Models\SiteMaterialStock;
use App\Models\StokMaterial;
use App\Models\TahapanPembangunan;
use App\Models\TransaksiLogistik;
use App\Models\User;
use App\Services\MaterialWorkflowService;
use App\Services\MaterialPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('approval lengkap mengeluarkan stok dan pengembalian mengoreksi stok serta hpp', function () {
    $user = User::factory()->create(['phone' => '081234567890']);
    $this->actingAs($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-TEST',
        'nama_cabang' => 'Cabang Test',
        'address' => 'Alamat',
        'phone' => '0800000000',
        'emaiil' => 'cabang@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-TEST',
        'nama_perusahaan' => 'Perumahan Test',
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
        'luas_tanah' => '100',
        'status' => 'aktif',
    ]);
    $tahapan = TahapanPembangunan::query()->create([
        'nama_tahapan' => 'Pondasi',
        'bobot_persen' => 20,
        'urutan' => 1,
        'status' => 'aktif',
    ]);
    $hpp = KelompokHpp::query()->create([
        'nama_hpp' => 'Material Pondasi',
        'kategori' => 'material',
        'status' => 'aktif',
    ]);
    $gudang = Gudang::query()->create([
        'kode_gudang' => 'GD-TEST',
        'nama_gudang' => 'Gudang Test',
        'perumahan_id' => $perumahan->id,
        'status' => 'aktif',
    ]);
    $material = BarangMaterial::query()->create([
        'kode_barang' => 'MAT-TEST',
        'nama_barang' => 'Semen',
        'satuan' => 'sak',
        'harga_hpp' => 100,
        'status' => 'aktif',
    ]);
    StokMaterial::query()->create([
        'barang_material_id' => $material->id,
        'gudang_id' => $gudang->id,
        'qty' => 100,
    ]);

    $request = MaterialRequest::query()->create([
        'kode_request' => 'REQ-TEST',
        'tanggal' => now()->toDateString(),
        'gudang_id' => $gudang->id,
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'tahapan_pembangunan_id' => $tahapan->id,
        'kelompok_hpp_id' => $hpp->id,
        'requested_by' => $user->id,
    ]);
    $request->details()->create([
        'barang_material_id' => $material->id,
        'qty' => 20,
        'satuan' => 'sak',
    ]);

    $workflow = app(MaterialWorkflowService::class);
    $workflow->approveGudang($request);
    $workflow->approveOwner($request->fresh());

    expect($request->fresh()->status)->toBe(MaterialRequest::STATUS_SELESAI)
        ->and(StokMaterial::query()->first()->qty)->toBe(80.0)
        ->and(TransaksiLogistik::query()->count())->toBe(1);

    $siteStock = SiteMaterialStock::query()->firstOrFail();
    expect($siteStock->qty_received)->toBe(20.0)
        ->and($siteStock->qty_available)->toBe(20.0);

    $progress = ProgressPembangunan::query()->create([
        'detail_rumah_id' => $unit->id,
        'tahapan_pembangunan_id' => $tahapan->id,
        'tanggal' => now()->toDateString(),
        'tahapan' => 20,
        'persentase' => 50,
        'persentase_total' => 10,
        'keterangan' => 'Progress test',
        'approval_status' => 'approved',
        'users_id' => $user->id,
    ]);

    $workflow->recordUsage([
        'tanggal' => now()->toDateString(),
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'tahapan_pembangunan_id' => $tahapan->id,
        'progress_pembangunan_id' => $progress->id,
        'keterangan' => 'Pemakaian test',
        'items' => [[
            'site_material_stock_id' => $siteStock->id,
            'qty' => 12,
            'satuan' => 'sak',
        ]],
    ]);

    expect($siteStock->fresh()->qty_used)->toBe(12.0)
        ->and($siteStock->fresh()->qty_available)->toBe(8.0);

    $return = $workflow->submitReturn([
        'tanggal' => now()->toDateString(),
        'gudang_id' => $gudang->id,
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'tahapan_pembangunan_id' => $tahapan->id,
        'keterangan' => 'Sisa test',
        'items' => [[
            'site_material_stock_id' => $siteStock->id,
            'qty' => 5,
        ]],
    ]);
    $workflow->receiveReturn($return);

    expect($siteStock->fresh()->qty_returned)->toBe(5.0)
        ->and($siteStock->fresh()->qty_available)->toBe(3.0)
        ->and(StokMaterial::query()->first()->qty)->toBe(85.0)
        ->and(TransaksiLogistik::query()->count())->toBe(2)
        ->and(HppRealisasi::query()->where('detail_rumah_id', $unit->id)->sum('nominal'))->toBe(1500);
});

test('pemeriksaan pembelian menambah stok hanya untuk item yang sesuai', function () {
    $user = User::factory()->create(['phone' => '081234567891']);
    $this->actingAs($user);

    $gudang = Gudang::query()->create([
        'kode_gudang' => 'GD-CHECK',
        'nama_gudang' => 'Gudang Pemeriksaan',
        'status' => 'aktif',
    ]);
    $semen = BarangMaterial::query()->create([
        'kode_barang' => 'MAT-SEMEN',
        'nama_barang' => 'Semen',
        'satuan' => 'sak',
        'harga_hpp' => 70000,
        'status' => 'aktif',
    ]);
    $pasir = BarangMaterial::query()->create([
        'kode_barang' => 'MAT-PASIR',
        'nama_barang' => 'Pasir',
        'satuan' => 'kubik',
        'harga_hpp' => 300000,
        'status' => 'aktif',
    ]);
    $request = MaterialPurchaseRequest::query()->create([
        'kode_request' => 'PR-GDG-TEST',
        'tanggal' => now()->toDateString(),
        'gudang_id' => $gudang->id,
        'status' => MaterialPurchaseRequest::STATUS_DIPROSES,
        'requested_by' => $user->id,
    ]);
    $purchase = MaterialPurchase::query()->create([
        'kode_pembelian' => 'PB-CHECK',
        'tanggal' => now()->toDateString(),
        'material_purchase_request_id' => $request->id,
        'gudang_id' => $gudang->id,
        'status' => MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
        'metode_pembayaran' => 'tunai',
        'total_nominal' => 1000000,
    ]);
    $accepted = $purchase->details()->create([
        'barang_material_id' => $semen->id,
        'qty' => 10,
        'satuan' => 'sak',
        'harga_satuan' => 70000,
        'subtotal' => 700000,
    ]);
    $rejected = $purchase->details()->create([
        'barang_material_id' => $pasir->id,
        'qty' => 1,
        'satuan' => 'kubik',
        'harga_satuan' => 300000,
        'subtotal' => 300000,
    ]);

    $service = app(MaterialPurchaseService::class);
    $service->inspectItem($purchase, $accepted, ['status' => 'sesuai', 'catatan' => 'Jumlah sesuai']);
    $service->inspectItem($purchase->fresh(), $rejected, ['status' => 'tidak_sesuai', 'catatan' => 'Kualitas ditolak']);

    expect(StokMaterial::query()->where('barang_material_id', $semen->id)->value('qty'))->toBe(10.0)
        ->and(StokMaterial::query()->where('barang_material_id', $pasir->id)->exists())->toBeFalse()
        ->and($purchase->fresh()->status)->toBe(MaterialPurchase::STATUS_DITERIMA_SEBAGIAN)
        ->and($request->fresh()->status)->toBe(MaterialPurchaseRequest::STATUS_SELESAI_SEBAGIAN)
        ->and(TransaksiLogistik::query()->count())->toBe(1);
});
