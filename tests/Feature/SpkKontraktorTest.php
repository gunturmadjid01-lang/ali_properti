<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\DetailPerumahanHpp;
use App\Models\KelompokHpp;
use App\Models\Kontraktor;
use App\Models\Perumahan;
use App\Models\PerumahanHpp;
use App\Models\SpkKontraktor;
use App\Models\SpkKontraktorItem;
use App\Models\SpkKontraktorPayment;
use App\Models\TahapanPembangunan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('spk tukang sendiri tersimpan tanpa membuat data kontraktor internal', function () {
    $user = User::factory()->create(['phone' => '081234567870']);
    $user->givePermissionTo(Permission::findOrCreate('spk-kontraktor.create', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('spk-kontraktor.view', 'web'));
    $this->actingAs($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-SPK',
        'nama_cabang' => 'Cabang SPK',
        'address' => 'Alamat',
        'phone' => '081234567871',
        'emaiil' => 'spk@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-SPK',
        'nama_perusahaan' => 'Perumahan SPK',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
    $user->perumahans()->attach($perumahan->id);

    $unit = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '1',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);

    $tahap = TahapanPembangunan::query()->create([
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'konteks' => 'unit',
        'nama_tahapan' => 'PEKERJAAN STRUKTUR',
        'bobot_persen' => 20,
        'urutan' => 1,
        'status' => 'aktif',
    ]);

    $payload = [
        'sumber_tenaga_kerja' => 'tukang_owner',
        'kontraktor_id' => '',
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'judul_pekerjaan' => 'Pekerjaan Struktur Unit 1',
        'jenis_pekerjaan' => 'rumah',
        'tanggal_spk' => now()->toDateString(),
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_selesai' => now()->addDays(14)->toDateString(),
        'nilai_kontrak' => 50000,
        'metode_pembayaran' => 'cash',
        'approval_role' => 'manajer',
        'lingkup_pekerjaan' => 'Lingkup kerja SPK',
        'catatan' => 'Catatan SPK',
        'status' => 'draft',
        'work_groups' => [[
            'judul_tahapan' => 'PEKERJAAN STRUKTUR',
            'items' => [
                [
                    'nama_pekerjaan' => 'Pasang Bata Merah',
                    'volume' => 10,
                    'satuan' => 'buah',
                    'harga_satuan' => 5000,
                ],
                [
                    'nama_pekerjaan' => 'Plester Dinding',
                    'volume' => 5,
                    'satuan' => 'm2',
                    'harga_satuan' => 4000,
                ],
            ],
        ]],
        'additions' => [],
        'payments' => [[
            'tanggal_jatuh_tempo' => now()->toDateString(),
            'nominal' => 50000,
            'tahapan_pembangunan_id' => $tahap->id,
            'spk_kontraktor_item_id' => '',
            'pekerjaan' => 'PEKERJAAN STRUKTUR - Pasang Bata Merah',
            'progress_diakui' => 100,
            'keterangan' => 'Termin lunas',
        ]],
    ];

    $this->post(route('admin.spk-kontraktor.store'), $payload)->assertRedirect();

    $spk = SpkKontraktor::query()->with(['kontraktor', 'items', 'payments'])->firstOrFail();
    $item = SpkKontraktorItem::query()->where('spk_kontraktor_id', $spk->id)->firstOrFail();
    $payment = SpkKontraktorPayment::query()->where('spk_kontraktor_id', $spk->id)->firstOrFail();

    expect($spk->sumber_tenaga_kerja)->toBe('tukang_owner')
        ->and($spk->kontraktor_id)->toBeNull()
        ->and(Kontraktor::withTrashed()->where('kode_kontraktor', 'INTERNAL-TAKANG')->exists())->toBeFalse()
        ->and($spk->nilai_kontrak_dasar)->toBe(9000.0)
        ->and($spk->nilai_kontrak)->toBe(9000.0)
        ->and($item->nama_tahap_pekerjaan)->toBe('PEKERJAAN STRUKTUR')
        ->and($item->nama_pekerjaan)->toBe('Pasang Bata Merah')
        ->and($payment->pekerjaan)->toBeNull()
        ->and($payment->spk_kontraktor_item_id)->toBeNull();

    $this->get(route('admin.spk-kontraktor.show', $spk))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SpkKontraktor/Show')
            ->where('spk.nomor_spk', $spk->nomor_spk)
            ->where('spk.group_count', 1)
            ->where('spk.item_count', 2)
            ->where('spk.payment_count', 1));
});

test('spk yang disetujui menambah item baru di rab perumahan bagian iv', function () {
    $user = User::factory()->create(['phone' => '081234567871']);
    $role = Role::findOrCreate('manajer_pimpro', 'web');
    $user->assignRole($role);
    $user->givePermissionTo(Permission::findOrCreate('spk-kontraktor.create', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('spk-kontraktor.approve', 'web'));
    $this->actingAs($user);

    $cabang = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-SPK-APV',
        'nama_cabang' => 'Cabang SPK Approve',
        'address' => 'Alamat',
        'phone' => '081234567872',
        'emaiil' => 'spk-approve@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);

    $perumahan = Perumahan::query()->create([
        'cabang_id' => $cabang->id,
        'kode_proyek' => 'PRJ-SPK-APV',
        'nama_perusahaan' => 'Perumahan SPK Approve',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);

    KelompokHpp::query()->create([
        'nama_hpp' => 'Biaya Subkontraktor',
        'kategori' => 'tenaga_kerja',
        'status' => 'aktif',
    ]);

    $unit = DetailRumah::query()->create([
        'perumahan_id' => $perumahan->id,
        'kode_nlok' => 'A',
        'nomor_rumah' => '2',
        'tipe_rumah' => '36',
        'luas_tanah' => '78',
        'status' => 'aktif',
    ]);

    $tahap = TahapanPembangunan::query()->create([
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'konteks' => 'unit',
        'nama_tahapan' => 'PEKERJAAN STRUKTUR',
        'bobot_persen' => 20,
        'urutan' => 1,
        'status' => 'aktif',
    ]);

    $this->post(route('admin.spk-kontraktor.store'), [
        'sumber_tenaga_kerja' => 'tukang_owner',
        'kontraktor_id' => '',
        'perumahan_id' => $perumahan->id,
        'detail_rumah_id' => $unit->id,
        'judul_pekerjaan' => 'Pekerjaan Struktur Unit 2',
        'jenis_pekerjaan' => 'rumah',
        'tanggal_spk' => now()->toDateString(),
        'tanggal_mulai' => now()->toDateString(),
        'tanggal_selesai' => now()->addDays(14)->toDateString(),
        'nilai_kontrak' => 70000,
        'metode_pembayaran' => 'cash',
        'approval_role' => 'manajer',
        'lingkup_pekerjaan' => 'Lingkup kerja SPK',
        'catatan' => 'Catatan SPK',
        'status' => 'draft',
        'work_groups' => [[
            'judul_tahapan' => 'PEKERJAAN STRUKTUR',
            'items' => [[
                'nama_pekerjaan' => 'Pasang Bata Merah',
                'volume' => 10,
                'satuan' => 'buah',
                'harga_satuan' => 5000,
            ]],
        ]],
        'additions' => [],
        'payments' => [[
            'tanggal_jatuh_tempo' => now()->toDateString(),
            'nominal' => 70000,
            'tahapan_pembangunan_id' => $tahap->id,
            'spk_kontraktor_item_id' => '',
            'pekerjaan' => 'PEKERJAAN STRUKTUR - Pasang Bata Merah',
            'progress_diakui' => 0,
            'keterangan' => 'Termin lunas',
        ]],
    ])->assertRedirect();

    $spk = SpkKontraktor::query()->firstOrFail();

    $this->post(route('admin.spk-kontraktor.approve', $spk->id))
        ->assertRedirect();

    $hpp = PerumahanHpp::query()->where('perumahan_id', $perumahan->id)->firstOrFail();
    $item = DetailPerumahanHpp::query()
        ->where('perumahan_hpp_id', $hpp->id)
        ->where('nama_pekerjaan', 'SPK '.$spk->nomor_spk.' - Pekerjaan Rumah')
        ->firstOrFail();

    expect((float) $item->jumlah_rab)->toBe(5000.0)
        ->and((float) $item->harga_satuan)->toBe(5000.0)
        ->and((float) $item->volume)->toBe(1.0);
});
