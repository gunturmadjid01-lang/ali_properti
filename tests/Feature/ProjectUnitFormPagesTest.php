<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function projectFormBranch(string $code = 'CB-FORM'): CabangPerusahaan
{
    return CabangPerusahaan::query()->create([
        'kode_cabang' => $code,
        'nama_cabang' => 'Cabang '.$code,
        'address' => '-',
        'phone' => '-',
        'emaiil' => strtolower($code).'@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
}

test('perumahan uses separate create edit pages and generates project code automatically', function () {
    $user = User::factory()->create(['phone' => '081111111111']);
    $branch = projectFormBranch();

    $this->actingAs($user)
        ->get('/admin/management/perumahan/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/Perumahan/FormPage')
            ->where('method', 'post')
            ->where('projectCode', null));

    $this->post('/admin/management/perumahan', [
        'cabang_id' => $branch->id,
        'nama_perusahaan' => 'Perumahan Form Test',
        'alamat' => 'Alamat proyek',
        'luas_lahan' => '1000',
        'jumlah_unit' => 10,
        'tanggal_mulai' => '2026-07-01',
        'status' => 'aktif',
    ])->assertRedirect('/admin/management/perumahan');

    $project = Perumahan::query()->where('nama_perusahaan', 'Perumahan Form Test')->firstOrFail();
    expect($project->kode_proyek)->toMatch('/^PRJ-\d{5}$/');

    $this->get('/admin/management/perumahan/'.$project->id.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/Perumahan/FormPage')
            ->where('method', 'put')
            ->where('projectCode', $project->kode_proyek));
});

test('unit uses separate create edit pages and stores bulk units', function () {
    $user = User::factory()->create(['phone' => '081111111112']);
    foreach (['detail-rumah.create', 'detail-rumah.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo(['detail-rumah.create', 'detail-rumah.update']);
    $branch = projectFormBranch('CB-UNIT');
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id,
        'nama_perusahaan' => 'Perumahan Unit Test',
        'alamat' => '-',
        'luas_lahan' => '1000',
        'jumlah_unit' => 10,
        'tanggal_mulai' => '2026-07-01',
        'status' => 'aktif',
    ]);

    $this->actingAs($user)
        ->get('/admin/unit-rumah/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UnitRumah/Form')
            ->where('editing', false));

    $this->post('/admin/unit-rumah', [
        'perumahan_id' => $project->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '01',
        'jumlah_unit' => 2,
        'luas_tanah' => '78',
        'harga_jual' => 250000000,
        'status_penjualan' => 'tersedia',
        'status_pembangunan' => 'kapling',
        'progress_terakhir' => 0,
        'status' => 'aktif',
    ])->assertRedirect('/admin/unit-rumah');

    expect(DetailRumah::query()->where('perumahan_id', $project->id)->count())->toBe(2);
    $unit = DetailRumah::query()->where('perumahan_id', $project->id)->firstOrFail();

    $this->get('/admin/unit-rumah/'.$unit->id.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UnitRumah/Form')
            ->where('editing', true)
            ->where('initialData.nomor_rumah', $unit->nomor_rumah));
});

test('unit ownership can be filtered by branch and project', function () {
    $user = User::factory()->create(['phone' => '081111111113']);
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    $firstBranch = projectFormBranch('CB-OWN-A');
    $secondBranch = projectFormBranch('CB-OWN-B');

    $projects = collect([$firstBranch, $secondBranch])->map(function (CabangPerusahaan $branch, int $index) {
        $project = Perumahan::query()->create([
            'cabang_id' => $branch->id,
            'nama_perusahaan' => 'Proyek Pemilik '.($index + 1),
            'alamat' => '-', 'luas_lahan' => '500', 'jumlah_unit' => 1,
            'tanggal_mulai' => '2026-07-01', 'status' => 'aktif',
        ]);
        $unit = DetailRumah::query()->create([
            'perumahan_id' => $project->id, 'kode_nlok' => 'A', 'nomor_rumah' => '0'.($index + 1),
            'luas_tanah' => '78', 'status' => 'aktif',
        ]);
        UnitOwnership::query()->create([
            'detail_rumah_id' => $unit->id, 'source_type' => 'legacy',
            'owner_name' => 'Pemilik '.($index + 1), 'identity_type' => 'KTP',
            'identity_number' => '7371000'.($index + 1), 'address' => '-',
            'acquisition_method' => 'data_lama', 'acquired_at' => '2020-01-01', 'is_active' => true,
        ]);

        return $project;
    });

    $target = $projects->first();
    $this->actingAs($user)
        ->get('/admin/pemilik-unit?status=active&cabang_id='.$firstBranch->id.'&perumahan_id='.$target->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UnitOwnership/Index')
            ->where('filters.cabang_id', (string) $firstBranch->id)
            ->where('filters.perumahan_id', (string) $target->id)
            ->where('rows.total', 1)
            ->where('rows.data.0.project', $target->nama_perusahaan));
});

test('ready units are hidden from hpp unit list and ownership is absent from kapling table', function () {
    $user = User::factory()->create(['phone' => '081111111117']);
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    $branch = projectFormBranch('CB-HPP-READY');
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id,
        'nama_perusahaan' => 'Proyek Filter Ready',
        'alamat' => '-',
        'luas_lahan' => '500',
        'jumlah_unit' => 2,
        'tanggal_mulai' => '2026-07-01',
        'status' => 'aktif',
    ]);

    DetailRumah::query()->create([
        'perumahan_id' => $project->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '01',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status_pembangunan' => 'sedang_dibangun',
        'status' => 'aktif',
    ]);
    DetailRumah::query()->create([
        'perumahan_id' => $project->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '02',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status_pembangunan' => 'selesai',
        'status' => 'aktif',
    ]);

    $this->actingAs($user)
        ->get('/admin/hpp-unit-rumah')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UnitRumah/HppIndex')
            ->where('rows.total', 1)
            ->where('rows.data.0.nomor_rumah', '01')
            ->has('options.hppUnitTargets', 1));

    $this->get('/admin/unit-rumah')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UnitRumah/Index')
            ->missing('rows.data.0.pemilik'));
});

test('ready units are hidden from spk options and perumahan detail has no unit form options', function () {
    $user = User::factory()->create(['phone' => '081111111118']);
    $user->givePermissionTo(Permission::findOrCreate('spk-kontraktor.view', 'web'));
    $branch = projectFormBranch('CB-SPK-READY');
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id,
        'nama_perusahaan' => 'Proyek SPK Filter Ready',
        'alamat' => '-',
        'luas_lahan' => '500',
        'jumlah_unit' => 2,
        'tanggal_mulai' => '2026-07-01',
        'status' => 'aktif',
    ]);

    $activeUnit = DetailRumah::query()->create([
        'perumahan_id' => $project->id,
        'kode_nlok' => 'B',
        'nomor_rumah' => '01',
        'tipe_rumah' => '45',
        'luas_tanah' => '90',
        'status_pembangunan' => 'sedang_dibangun',
        'status' => 'aktif',
    ]);
    DetailRumah::query()->create([
        'perumahan_id' => $project->id,
        'kode_nlok' => 'B',
        'nomor_rumah' => '02',
        'tipe_rumah' => '45',
        'luas_tanah' => '90',
        'status_pembangunan' => 'selesai',
        'status' => 'aktif',
    ]);

    $this->actingAs($user)
        ->get('/admin/spk-kontraktor')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SpkKontraktor/Index')
            ->has('options.detailRumahs', 1)
            ->where('options.detailRumahs.0.value', (string) $activeUnit->id));

    $this->get('/admin/management/perumahan/'.$project->id.'/detail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/Perumahan/Detail')
            ->missing('options'));
});
