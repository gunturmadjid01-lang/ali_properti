<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Kontraktor;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Models\SiteSchedule;
use App\Models\SpkKontraktor;
use App\Models\SpkKontraktorItem;
use App\Models\SpkKontraktorPayment;
use App\Models\User;
use App\Services\ConstructionProgressReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('construction report calculates cumulative and period progress and linked spk payments', function () {
    $user = User::factory()->create(['phone' => '081234567890']);
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-TEST', 'nama_cabang' => 'Cabang Test', 'address' => '-', 'phone' => '-',
        'emaiil' => 'test@example.test', 'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id, 'nama_perusahaan' => 'Proyek Test', 'alamat' => '-',
        'luas_lahan' => '1000', 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $project->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01',
        'tipe_rumah' => '36/78', 'luas_tanah' => '78', 'status' => 'aktif',
    ]);
    $contractor = Kontraktor::query()->create([
        'kode_kontraktor' => 'K-TEST', 'nama_kontraktor' => 'Tukang Test', 'status' => 'aktif',
    ]);
    $spk = SpkKontraktor::query()->create([
        'kontraktor_id' => $contractor->id,
        'perumahan_id' => $project->id,
        'detail_rumah_id' => $unit->id,
        'nomor_spk' => 'SPK-TEST-001',
        'judul_pekerjaan' => 'Borongan Unit',
        'jenis_pekerjaan' => 'rumah',
        'tanggal_spk' => '2026-01-01',
        'nilai_kontrak_dasar' => 1000,
        'nilai_kontrak' => 1000,
        'metode_pembayaran' => 'termin',
        'status' => 'aktif',
    ]);
    SpkKontraktorItem::query()->create([
        'spk_kontraktor_id' => $spk->id,
        'nama_tahap_pekerjaan' => 'Tahap 1',
        'nama_pekerjaan' => 'Pekerjaan Pondasi',
        'volume' => 1,
        'satuan' => 'ls',
        'harga_satuan' => 1000,
        'total' => 1000,
        'urutan' => 1,
    ]);
    $schedule = SiteSchedule::query()->create([
        'kode_jadwal' => 'JDW-TEST-001',
        'perumahan_id' => $project->id,
        'detail_rumah_id' => $unit->id,
        'spk_kontraktor_id' => $spk->id,
        'nama_pekerjaan' => 'Borongan Unit',
        'tanggal_mulai' => '2026-01-01',
        'tanggal_target' => '2026-01-31',
    ]);

    foreach ([['2026-01-05', 20], ['2026-01-12', 30]] as [$date, $percentage]) {
        ProgressPembangunan::query()->create([
            'detail_rumah_id' => $unit->id,
            'site_schedule_id' => $schedule->id,
            'schedule_stage_name' => 'Tahap 1',
            'schedule_item_name' => 'Pekerjaan Pondasi',
            'nama_progress' => 'Pekerjaan Pondasi',
            'tanggal' => $date,
            'tahapan' => 100,
            'persentase' => $percentage,
            'persentase_total' => $percentage,
            'keterangan' => 'Progress test',
            'approval_status' => 'approved',
            'users_id' => $user->id,
        ]);
    }

    foreach ([['2026-01-06', 200], ['2026-01-12', 300]] as $index => [$date, $nominal]) {
        SpkKontraktorPayment::query()->create([
            'spk_kontraktor_id' => $spk->id,
            'termin_ke' => $index + 1,
            'tanggal_pembayaran' => $date,
            'nominal' => $nominal,
            'status' => 'dana_cair',
            'paid_at' => $date.' 09:00:00',
        ]);
    }

    $result = app(ConstructionProgressReportService::class)->build(
        DetailRumah::query()->with('perumahan')->whereKey($unit->id)->get(),
        Carbon::parse('2026-01-08')->startOfDay(),
        Carbon::parse('2026-01-14')->endOfDay(),
    );

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['units'][0]['cumulative'])->toEqualWithDelta(50, 0.001)
        ->and($result['rows'][0]['units'][0]['period'])->toEqualWithDelta(30, 0.001)
        ->and($result['units'][0]['cumulative_weight'])->toEqualWithDelta(50, 0.001)
        ->and($result['units'][0]['previous_weight'])->toEqualWithDelta(20, 0.001)
        ->and($result['units'][0]['period_weight'])->toEqualWithDelta(30, 0.001)
        ->and($result['units'][0]['payment_previous'])->toEqualWithDelta(200, 0.001)
        ->and($result['units'][0]['payment_period'])->toEqualWithDelta(300, 0.001)
        ->and($result['units'][0]['payment_total'])->toEqualWithDelta(500, 0.001);
});
