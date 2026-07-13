<?php

use App\Models\BarangMaterial;
use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Gudang;
use App\Models\MaterialUsage;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Models\SiteMaterialStock;
use App\Models\TahapanPembangunan;
use App\Models\User;
use App\Services\MaterialUsageReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('material usage report filters project unit and period and aggregates material', function () {
    $user = User::factory()->create(['phone' => '081234567890']);
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-RPT', 'nama_cabang' => 'Cabang Report', 'address' => '-', 'phone' => '-',
        'emaiil' => 'report@example.test', 'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id, 'nama_perusahaan' => 'Proyek Report', 'alamat' => '-',
        'luas_lahan' => '1000', 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-07-01', 'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $project->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01',
        'tipe_rumah' => '36/78', 'luas_tanah' => '78', 'status' => 'aktif',
    ]);
    $stage = TahapanPembangunan::query()->create([
        'nama_tahapan' => 'Pondasi', 'bobot_persen' => 20, 'urutan' => 1, 'status' => 'aktif',
    ]);
    $progress = ProgressPembangunan::query()->create([
        'detail_rumah_id' => $unit->id, 'tahapan_pembangunan_id' => $stage->id,
        'nama_progress' => 'Pemasangan Pondasi', 'tanggal' => '2026-07-08', 'tahapan' => 20,
        'persentase' => 50, 'persentase_total' => 10, 'keterangan' => 'Progress',
        'approval_status' => 'approved', 'users_id' => $user->id,
    ]);
    $warehouse = Gudang::query()->create([
        'kode_gudang' => 'GD-RPT', 'nama_gudang' => 'Gudang Report', 'perumahan_id' => $project->id, 'status' => 'aktif',
    ]);
    $material = BarangMaterial::query()->create([
        'kode_barang' => 'MAT-RPT', 'nama_barang' => 'Semen', 'satuan' => 'sak', 'harga_hpp' => 75000, 'status' => 'aktif',
    ]);
    $siteStock = SiteMaterialStock::query()->create([
        'gudang_id' => $warehouse->id, 'perumahan_id' => $project->id, 'detail_rumah_id' => $unit->id,
        'tahapan_pembangunan_id' => $stage->id, 'barang_material_id' => $material->id,
        'qty_received' => 20, 'qty_used' => 5, 'qty_returned' => 0, 'qty_reserved_return' => 0, 'qty_available' => 15,
    ]);

    foreach ([['2026-07-08', 2], ['2026-07-10', 3], ['2026-06-30', 4]] as $index => [$date, $qty]) {
        $usage = MaterialUsage::query()->create([
            'kode_pemakaian' => 'USE-RPT-'.$index, 'tanggal' => $date, 'perumahan_id' => $project->id,
            'detail_rumah_id' => $unit->id, 'tahapan_pembangunan_id' => $stage->id,
            'progress_pembangunan_id' => $progress->id, 'keterangan' => 'Pemakaian semen',
        ]);
        $usage->details()->create([
            'site_material_stock_id' => $siteStock->id, 'barang_material_id' => $material->id,
            'qty' => $qty, 'satuan' => 'sak',
        ]);
    }

    $report = app(MaterialUsageReportService::class)->build(
        $project,
        $unit,
        Carbon::parse('2026-07-06')->startOfDay(),
        Carbon::parse('2026-07-12')->endOfDay(),
    );

    expect($report['totals']['transactions'])->toBe(2)
        ->and($report['totals']['materials'])->toBe(1)
        ->and($report['summary'])->toHaveCount(1)
        ->and($report['summary'][0]['quantity'])->toEqualWithDelta(5, 0.001)
        ->and($report['summary'][0]['amount'])->toEqualWithDelta(375000, 0.001)
        ->and($report['details'])->toHaveCount(2);

    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    $this->actingAs($user)
        ->get('/admin/laporan-pemakaian-barang?period_type=weekly&reference_date=2026-07-08&perumahan_id='.$project->id.'&detail_rumah_id='.$unit->id)
        ->assertOk();
});
