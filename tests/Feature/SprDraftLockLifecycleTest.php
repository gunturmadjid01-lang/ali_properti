<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\Spr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function sprLifecycleData(): array
{
    $branch = CabangPerusahaan::create([
        'kode_cabang' => 'CB-SPR-DRAFT', 'nama_cabang' => 'Cabang SPR Draft', 'address' => '-',
        'phone' => '-', 'emaiil' => 'spr-draft@test.local', 'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $housing = Perumahan::create([
        'cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan SPR Draft', 'alamat' => '-',
        'luas_lahan' => 1000, 'jumlah_unit' => 2, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif',
    ]);
    $unit = DetailRumah::create([
        'perumahan_id' => $housing->id, 'kode_nlok' => 'D', 'nomor_rumah' => '01',
        'tipe_rumah' => '36', 'luas_tanah' => 72, 'harga_jual' => 300000000,
        'status' => 'aktif', 'status_penjualan' => 'tersedia',
    ]);
    $creator = User::factory()->create(['phone' => '081299900001']);
    $manager = User::factory()->create(['phone' => '081299900002']);
    $owner = User::factory()->create(['phone' => '081299900004']);
    $outsider = User::factory()->create(['phone' => '081299900003']);
    $creator->givePermissionTo([
        Permission::findOrCreate('booking.create', 'web'),
        Permission::findOrCreate('booking.update', 'web'),
        Permission::findOrCreate('booking.view', 'web'),
    ]);
    $manager->givePermissionTo(Permission::findOrCreate('booking.update', 'web'));
    $managerRole = Role::findOrCreate('manager', 'web');
    $manager->assignRole($managerRole);
    $owner->assignRole(Role::findOrCreate('owner', 'web'));
    $customer = Costumer::create([
        'kode_costumer' => 'CST-SPR-DRAFT', 'created_by' => $creator->id, 'perumahan_id' => $housing->id,
        'nama' => 'Customer Draft', 'jenis_kelamin' => 'laki-laki', 'jenis_identitas' => 'ktp',
        'no_identitas' => 'NIK-SPR-DRAFT', 'status_perkawinan' => 'menikah', 'alamat' => '-',
    ]);
    ApprovalSetting::query()->updateOrCreate([
        'module_key' => 'spr', 'action' => 'lock',
    ], [
        'module_label' => 'Pengajuan SPR',
        'requires_approval' => true, 'approval_stages' => 1,
        'approver_role_ids' => [$managerRole->id],
        'approval_steps' => [['step' => 1, 'role_ids' => [$managerRole->id]]],
        'is_active' => true,
    ]);

    return compact('creator', 'manager', 'owner', 'outsider', 'customer', 'unit');
}

test('SPR mengikuti lifecycle draft privat lock approval dan unlock', function () {
    ['creator' => $creator, 'manager' => $manager, 'owner' => $owner, 'outsider' => $outsider, 'customer' => $customer, 'unit' => $unit] = sprLifecycleData();

    $this->actingAs($creator)->post(route('admin.marketing.spr.store'), [
        'costumer_id' => $customer->id,
        'detail_rumah_id' => $unit->id,
        'tanggal_spr' => '2026-07-16',
        'metode_pembayaran' => 'cash',
        'harga_jual' => 300000000,
        'booking_fee' => 5000000,
        'booking_fee_includes_dp' => false,
        'uang_muka' => 0,
        'nilai_pengajuan_kpr' => 0,
        'penambahan_tanah' => 0,
        'harga_penambahan_tanah' => 0,
        'harga_penambahan_lain_lain' => 0,
        'berkas' => [],
    ])->assertRedirect(route('admin.marketing.spr.index'))
        ->assertSessionHasNoErrors();

    $spr = Spr::query()->sole();
    expect($spr->status)->toBe(Spr::STATUS_DRAFT)
        ->and($spr->record_status)->toBe('draft')
        ->and($spr->locked_at)->toBeNull()
        ->and(ApprovalRequest::query()->count())->toBe(0);

    $this->actingAs($creator)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.can_edit', true));
    $this->actingAs($creator)->get(route('admin.marketing.spr.show', $spr->id))->assertOk();
    $this->actingAs($manager)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->actingAs($manager)->get(route('admin.marketing.spr.index', ['search' => $spr->kode_spr]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->actingAs($manager)->get(route('admin.marketing.spr.show', $spr->id))->assertNotFound();
    $this->actingAs($outsider)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->actingAs($outsider)->get(route('admin.marketing.spr.show', $spr->id))->assertNotFound();

    $this->actingAs($creator)->post(route('admin.marketing.spr.lock', $spr->id))
        ->assertRedirect()->assertSessionHasNoErrors();
    $spr->refresh();
    $approval = ApprovalRequest::query()->sole();
    expect($spr->record_status)->toBe('locked')
        ->and($spr->status)->toBe(Spr::STATUS_MENUNGGU_APPROVAL)
        ->and($approval->status)->toBe(ApprovalRequest::STATUS_PENDING);

    $this->actingAs($manager)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.can_review_approval', true)
        ->where('rows.data.0.can_unlock', true));
    $this->actingAs($owner)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.can_review_approval', false)
        ->where('rows.data.0.can_unlock', true));
    $this->actingAs($manager)->get(route('admin.marketing.spr.show', $spr->id))->assertOk();
    $this->actingAs($outsider)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->actingAs($outsider)->get(route('admin.marketing.spr.show', $spr->id))->assertNotFound();
    $this->actingAs($creator)->get(route('admin.marketing.spr.edit', $spr->id))->assertNotFound();
    $this->actingAs($manager)->get(route('admin.marketing.spr.edit', $spr->id))->assertNotFound();

    $this->actingAs($manager)->post(route('admin.marketing.spr.unlock', $spr->id))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($spr->fresh()->record_status)->toBe('draft')
        ->and($spr->fresh()->status)->toBe(Spr::STATUS_DRAFT)
        ->and($approval->fresh()->status)->toBe(ApprovalRequest::STATUS_REJECTED);

    $this->actingAs($manager)->get(route('admin.marketing.spr.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->actingAs($creator)->get(route('admin.marketing.spr.edit', $spr->id))->assertOk();

    $this->actingAs($creator)->post(route('admin.marketing.spr.lock', $spr->id))
        ->assertRedirect()->assertSessionHasNoErrors();
    $resubmittedApproval = ApprovalRequest::query()->latest('id')->firstOrFail();
    expect(ApprovalRequest::query()->count())->toBe(2)
        ->and($resubmittedApproval->status)->toBe(ApprovalRequest::STATUS_PENDING)
        ->and($spr->fresh()->record_status)->toBe('locked');

    $this->actingAs($owner)->post(route('admin.marketing.spr.unlock', $spr->id))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($spr->fresh()->record_status)->toBe('draft')
        ->and($spr->fresh()->status)->toBe(Spr::STATUS_DRAFT)
        ->and($resubmittedApproval->fresh()->status)->toBe(ApprovalRequest::STATUS_REJECTED);
});
