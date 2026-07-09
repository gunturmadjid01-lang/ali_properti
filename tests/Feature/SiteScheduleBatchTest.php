<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\SiteSchedule;
use App\Models\TahapanPembangunan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function giveScheduleCreatePermission(User $user): void
{
    $role = Role::findOrCreate('pengawas', 'web');
    $role->givePermissionTo(Permission::findOrCreate('site-schedule.create', 'web'));
    $user->assignRole($role);
}

test('pengawas dapat membuat time schedule sekaligus dari tahapan rab kawasan', function () {
    $user = User::factory()->create(['phone' => '081234567899']);
    giveScheduleCreatePermission($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-JDL',
        'nama_cabang' => 'Cabang Jadwal',
        'address' => 'Alamat',
        'phone' => '081234567898',
        'emaiil' => 'jadwal@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-JDL',
        'nama_perusahaan' => 'Perumahan Jadwal',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);

    $tanah = TahapanPembangunan::query()->create([
        'perumahan_id' => $perumahan->id,
        'konteks' => 'kawasan',
        'nama_tahapan' => 'RAB TANAH',
        'bobot_persen' => 40,
        'urutan' => 1,
        'status' => 'aktif',
    ]);
    $sarana = TahapanPembangunan::query()->create([
        'perumahan_id' => $perumahan->id,
        'konteks' => 'kawasan',
        'nama_tahapan' => 'RAB SARANA',
        'bobot_persen' => 60,
        'urutan' => 2,
        'status' => 'aktif',
    ]);

    $this->actingAs($user)
        ->post(route('admin.site-schedule.store'), [
            'perumahan_id' => $perumahan->id,
            'detail_rumah_ids' => [],
            'tanggal_mulai' => '2026-07-01',
            'jumlah_periode' => 2,
            'status' => 'direncanakan',
            'items' => [
                [
                    'tahapan_pembangunan_id' => $tanah->id,
                    'nama_pekerjaan' => 'RAB TANAH',
                    'target_progress' => 40,
                ],
                [
                    'tahapan_pembangunan_id' => $sarana->id,
                    'nama_pekerjaan' => 'RAB SARANA',
                    'target_progress' => 60,
                ],
            ],
        ])
        ->assertRedirect();

    expect(SiteSchedule::query()->where('perumahan_id', $perumahan->id)->count())->toBe(2);
    expect(SiteSchedule::query()->where('tahapan_pembangunan_id', $tanah->id)->first()->target_progress)->toBe(40.0);
});

test('time schedule unit dapat dibuat untuk banyak unit rumah sekaligus', function () {
    $user = User::factory()->create(['phone' => '081234567897']);
    giveScheduleCreatePermission($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-JDL2',
        'nama_cabang' => 'Cabang Jadwal Unit',
        'address' => 'Alamat',
        'phone' => '081234567896',
        'emaiil' => 'jadwal-unit@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-JDL2',
        'nama_perusahaan' => 'Perumahan Jadwal Unit',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 2,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
    $unitA = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '1',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);
    $unitB = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '2',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);
    $stageA = TahapanPembangunan::query()->create([
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unitA->id,
        'konteks' => 'unit',
        'nama_tahapan' => 'PEK. PERSIAPAN',
        'bobot_persen' => 10,
        'urutan' => 1,
        'status' => 'aktif',
    ]);
    TahapanPembangunan::query()->create([
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unitB->id,
        'konteks' => 'unit',
        'nama_tahapan' => 'PEK. PERSIAPAN',
        'bobot_persen' => 10,
        'urutan' => 1,
        'status' => 'aktif',
    ]);

    $this->actingAs($user)
        ->post(route('admin.site-schedule.store'), [
            'perumahan_id' => $perumahan->id,
            'detail_rumah_ids' => [$unitA->id, $unitB->id],
            'tanggal_mulai' => '2026-07-01',
            'jumlah_periode' => 4,
            'status' => 'direncanakan',
            'items' => [[
                'tahapan_pembangunan_id' => $stageA->id,
                'nama_pekerjaan' => 'PEK. PERSIAPAN',
                'target_progress' => 10,
            ]],
        ])
        ->assertRedirect();

    expect(SiteSchedule::query()->where('perumahan_id', $perumahan->id)->count())->toBe(2);
    expect(SiteSchedule::query()->where('detail_rumah_id', $unitA->id)->count())->toBe(1);
    expect(SiteSchedule::query()->where('detail_rumah_id', $unitB->id)->count())->toBe(1);
});
