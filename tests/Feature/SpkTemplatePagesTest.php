<?php

use App\Models\CabangPerusahaan;
use App\Models\Perumahan;
use App\Models\SpkWorkTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('template pekerjaan spk memiliki halaman daftar form dan detail terpisah', function () {
    $user = User::factory()->create(['phone' => '081299991111']);
    $user->assignRole(Role::findOrCreate('owner', 'web'));
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        $user->givePermissionTo(Permission::findOrCreate("spk-template-perumahan.{$action}", 'web'));
    }
    $this->actingAs($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-TPL',
        'nama_cabang' => 'Cabang Template',
        'address' => 'Alamat cabang',
        'phone' => '081299991112',
        'emaiil' => 'template@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'nama_perusahaan' => 'Perumahan Template',
        'alamat' => 'Alamat proyek',
        'luas_lahan' => '1000',
        'jumlah_unit' => 10,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);

    $payload = [
        'perumahan_id' => $perumahan->id,
        'nama_template' => 'Upah Borongan Tipe 36',
        'catatan' => 'Template acuan SPK',
        'work_groups' => [[
            'judul_tahapan' => 'PEKERJAAN PONDASI',
            'items' => [[
                'nama_pekerjaan' => 'Pasang pondasi batu gunung',
                'harga_satuan' => 2500000,
            ]],
        ]],
    ];

    $this->get(route('admin.spk-template.index', ['context' => 'perumahan']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/SpkTemplate/Index'));

    $this->get(route('admin.spk-template.create', ['context' => 'perumahan']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/SpkTemplate/Form')->where('template', null));

    $this->post(route('admin.spk-template.store', ['context' => 'perumahan']), $payload)
        ->assertRedirect(route('admin.spk-template.index', ['context' => 'perumahan']));

    $template = SpkWorkTemplate::query()->with('groups.items')->firstOrFail();

    expect($template->groups)->toHaveCount(1)
        ->and($template->groups->first()->items)->toHaveCount(1)
        ->and((float) $template->groups->first()->items->first()->harga_satuan)->toBe(2500000.0);

    $this->get(route('admin.spk-template.show', $template))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SpkTemplate/Show')
            ->where('template.total_nilai', 2500000));

    $this->get(route('admin.spk-template.edit', $template))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SpkTemplate/Form')
            ->where('template.nama_template', 'Upah Borongan Tipe 36'));
});
