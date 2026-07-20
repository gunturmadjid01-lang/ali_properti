<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\WaterBillingPeriod;
use App\Models\WaterPayment;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function waterFixture(): array
{
    $admin = User::factory()->create();
    $branch = CabangPerusahaan::create(['kode_cabang' => 'AIR', 'nama_cabang' => 'Cabang Air', 'address' => '-', 'phone' => '-', 'emaiil' => 'air@test.local', 'manager_name' => '-', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Air', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '1', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif']);
    $customer = Costumer::create(['kode_costumer' => 'CST-AIR', 'perumahan_id' => $housing->id, 'nama' => 'Pemilik Air', 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'AIR-1', 'status_perkawinan' => 'belum_menikah', 'alamat' => '-']);
    $owner = UnitOwnership::create(['detail_rumah_id' => $unit->id, 'costumer_id' => $customer->id, 'source_type' => 'legacy', 'acquisition_method' => 'data_lama', 'acquired_at' => '2020-01-01', 'owner_name' => $customer->nama, 'identity_type' => 'KTP', 'identity_number' => 'AIR-1', 'address' => '-', 'is_active' => true, 'record_status' => 'locked']);
    $period = WaterBillingPeriod::create(['perumahan_id' => $housing->id, 'period_code' => 'AIR-PER-1', 'period_name' => 'Juli 2026', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'due_date' => '2026-08-10', 'amount' => 75000, 'is_active' => true, 'record_status' => 'locked']);
    $payment = WaterPayment::create(['water_billing_period_id' => $period->id, 'unit_ownership_id' => $owner->id, 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'payment_no' => 'AIR-BYR-1', 'payment_date' => '2026-07-19', 'amount' => 75000, 'payment_method' => 'transfer', 'status' => 'pending_approval', 'record_status' => 'locked']);
    return compact('admin', 'housing', 'owner', 'period', 'payment');
}

test('pembayaran air mengikuti nol sampai tiga tahap approval dan side effect tetap satu kali', function (int $stages) {
    ['admin' => $admin, 'payment' => $payment] = waterFixture();
    $roles = collect(range(1, max(1, $stages)))->map(fn ($step) => Role::findOrCreate("water-review-{$stages}-{$step}", 'web'));
    ApprovalSetting::create(['module_key' => 'water-payment', 'module_label' => 'Pembayaran Air', 'action' => 'lock', 'requires_approval' => $stages > 0, 'approval_stages' => $stages, 'approver_role_ids' => $stages ? [$roles->first()->id] : [], 'approval_steps' => $stages ? collect(range(1, $stages))->map(fn ($step) => ['step' => $step, 'role_ids' => [$roles[$step - 1]->id]])->all() : [], 'is_active' => true]);
    $workflow = app(ApprovalWorkflowService::class);
    $this->actingAs($admin);
    $approval = $workflow->submitLocked($payment, 'water-payment');
    if ($stages === 0) {
        expect($payment->fresh()->status)->toBe('paid')->and($approval->status)->toBe('approved');
        return;
    }
    foreach (range(1, $stages) as $step) {
        $reviewer = User::factory()->create(); $reviewer->assignRole($roles[$step - 1]); $this->actingAs($reviewer);
        expect($workflow->canReview($approval->fresh()))->toBeTrue(); $workflow->approve($approval->fresh());
    }
    expect($payment->fresh()->status)->toBe('paid')->and(ApprovalRequest::where('model_id', $payment->id)->count())->toBe(1);
})->with([0, 1, 2, 3]);

test('approval periode membentuk kewajiban seluruh pemilik aktif secara idempoten', function () {
    ['admin' => $admin, 'period' => $period, 'payment' => $payment] = waterFixture();
    $payment->delete();
    ApprovalSetting::create(['module_key' => 'water-billing-period', 'module_label' => 'Periode Tagihan Air', 'action' => 'lock', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true]);
    $this->actingAs($admin); $workflow = app(ApprovalWorkflowService::class); $workflow->submitLocked($period, 'water-billing-period'); $workflow->submitLocked($period, 'water-billing-period');
    expect(WaterPayment::where('water_billing_period_id', $period->id)->count())->toBe(1)
        ->and(WaterPayment::where('water_billing_period_id', $period->id)->value('status'))->toBe('unpaid');
});

test('reject mengembalikan pembayaran ke status ditolak dan unlock membatalkan request pending', function () {
    ['admin' => $admin, 'payment' => $payment] = waterFixture();
    $role = Role::findOrCreate('water-review-reject', 'web');
    ApprovalSetting::create(['module_key' => 'water-payment', 'module_label' => 'Pembayaran Air', 'action' => 'lock', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]], 'is_active' => true]);
    $workflow = app(ApprovalWorkflowService::class); $this->actingAs($admin); $approval = $workflow->submitLocked($payment, 'water-payment');
    $reviewer = User::factory()->create(); $reviewer->assignRole($role); $this->actingAs($reviewer); $workflow->reject($approval, 'Bukti belum jelas');
    expect($payment->fresh()->status)->toBe('rejected')->and($payment->fresh()->record_status)->toBe('draft');
    $payment->update(['status' => 'pending_approval', 'record_status' => 'locked']); $this->actingAs($admin); $resubmitted = $workflow->submitLocked($payment, 'water-payment'); $workflow->cancelPendingLock($payment);
    expect($resubmitted->fresh()->status)->toBe('rejected');
});
