<?php

use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function settingApprovalSpr(string $code): Spr
{
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-'.$code, 'nama_cabang' => 'Cabang '.$code, 'address' => '-', 'phone' => '-', 'emaiil' => $code.'@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan '.$code, 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'harga_jual' => 300000000, 'status' => 'aktif', 'status_penjualan' => 'tersedia']);
    $customer = Costumer::create(['kode_costumer' => 'CST-'.$code, 'perumahan_id' => $housing->id, 'nama' => 'Customer '.$code, 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'NIK-'.$code, 'status_perkawinan' => 'menikah', 'alamat' => '-']);

    return Spr::create(['kode_spr' => 'SPR-'.$code, 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-07-16', 'metode_pembayaran' => 'cash', 'harga_jual' => 300000000, 'nilai_pengajuan_akhir' => 300000000, 'booking_fee' => 5000000, 'uang_muka' => 25000000, 'status' => Spr::STATUS_MENUNGGU_MANAGER, 'record_status' => 'locked']);
}

test('SPR auto approve mengikuti setting nol tahap dan menjalankan side effect final', function () {
    $user = User::factory()->create(['phone' => '081200000101']);
    $this->actingAs($user);
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'spr', 'action' => 'lock'], ['module_label' => 'Pengajuan SPR', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true]);
    $spr = settingApprovalSpr('AUTO');

    $approval = app(ApprovalWorkflowService::class)->submitLocked($spr, 'spr');

    expect($approval->status)->toBe('approved')
        ->and($spr->fresh()->status)->toBe(Spr::STATUS_DISETUJUI)
        ->and(SalesTransaction::where('spr_id', $spr->id)->count())->toBe(1)
        ->and($spr->detailRumah()->first()->status_penjualan)->toBe('booking');
});

test('SPR hanya dapat disetujui role pada tahap aktif setting approval', function () {
    $role = Role::findOrCreate('approver_spr_test', 'web');
    $approver = User::factory()->create(['phone' => '081200000102']);
    $outsider = User::factory()->create(['phone' => '081200000103']);
    $approver->assignRole($role);
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'spr', 'action' => 'lock'], ['module_label' => 'Pengajuan SPR', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]], 'is_active' => true]);
    $this->actingAs($outsider);
    $spr = settingApprovalSpr('ROLE');
    $approval = app(ApprovalWorkflowService::class)->submitLocked($spr, 'spr');

    expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeFalse();
    $this->actingAs($approver);
    expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeTrue();
    app(ApprovalWorkflowService::class)->approve($approval);

    expect($approval->fresh()->status)->toBe('approved')
        ->and(app(ApprovalWorkflowService::class)->canReview($approval->fresh()))->toBeFalse()
        ->and($spr->fresh()->status)->toBe(Spr::STATUS_DISETUJUI)
        ->and(SalesTransaction::where('spr_id', $spr->id)->count())->toBe(1);
});

test('daftar SPR menyediakan grafik status metode dan ringkasan keuangan sesuai filter', function () {
    $ownerRole = Role::findOrCreate('owner', 'web');
    $owner = User::factory()->create(['phone' => '081200000104']);
    $owner->assignRole($ownerRole);
    settingApprovalSpr('ANALYTICS')->update(['created_by' => $owner->id]);

    $this->actingAs($owner)->get('/admin/marketing/spr?payment_method=cash')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketing/Spr/Index')
            ->where('analytics.total', 1)
            ->where('analytics.financial.sales_value', 300000000)
            ->has('analytics.status', 1)
            ->has('analytics.methods', 1));
});
