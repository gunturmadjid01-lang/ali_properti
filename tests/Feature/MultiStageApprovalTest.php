<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('approval dua tahap hanya menerapkan data setelah tahap terakhir', function () {
    $firstRole = Role::create(['name' => 'approver_satu', 'guard_name' => 'web']);
    $secondRole = Role::create(['name' => 'approver_dua', 'guard_name' => 'web']);
    $firstUser = User::factory()->create(['phone' => '081111111111']);
    $secondUser = User::factory()->create(['phone' => '082222222222']);
    $firstUser->assignRole($firstRole);
    $secondUser->assignRole($secondRole);

    ApprovalSetting::create([
        'module_key' => 'supplier',
        'module_label' => 'Supplier',
        'action' => 'create',
        'requires_approval' => true,
        'approval_stages' => 2,
        'approver_role_ids' => [$firstRole->id],
        'approval_steps' => [
            ['step' => 1, 'role_ids' => [$firstRole->id]],
            ['step' => 2, 'role_ids' => [$secondRole->id]],
        ],
        'is_active' => true,
    ]);

    $approval = ApprovalRequest::create([
        'module_key' => 'supplier',
        'module_label' => 'Supplier',
        'action' => 'create',
        'model_type' => Supplier::class,
        'after_data' => ['kode_supplier' => 'SUP-TEST', 'nama_supplier' => 'Supplier Test', 'status' => 'aktif'],
        'status' => ApprovalRequest::STATUS_PENDING,
        'current_step' => 1,
        'total_steps' => 2,
        'step_history' => [],
    ]);

    $this->actingAs($firstUser);
    app(ApprovalWorkflowService::class)->approve($approval);

    expect(Supplier::query()->where('kode_supplier', 'SUP-TEST')->exists())->toBeFalse()
        ->and($approval->fresh()->current_step)->toBe(2)
        ->and($approval->fresh()->status)->toBe(ApprovalRequest::STATUS_PENDING);

    $this->actingAs($secondUser);
    app(ApprovalWorkflowService::class)->approve($approval->fresh());

    expect(Supplier::query()->where('kode_supplier', 'SUP-TEST')->exists())->toBeTrue()
        ->and($approval->fresh()->status)->toBe(ApprovalRequest::STATUS_APPROVED)
        ->and($approval->fresh()->step_history)->toHaveCount(2);
});

test('role tahap berikutnya tidak dapat mendahului tahap aktif', function () {
    $firstRole = Role::create(['name' => 'approver_awal', 'guard_name' => 'web']);
    $secondRole = Role::create(['name' => 'approver_akhir', 'guard_name' => 'web']);
    $secondUser = User::factory()->create(['phone' => '083333333333']);
    $secondUser->assignRole($secondRole);

    $setting = ApprovalSetting::create([
        'module_key' => 'supplier', 'module_label' => 'Supplier', 'action' => 'create',
        'requires_approval' => true, 'approval_stages' => 2,
        'approver_role_ids' => [$firstRole->id],
        'approval_steps' => [
            ['step' => 1, 'role_ids' => [$firstRole->id]],
            ['step' => 2, 'role_ids' => [$secondRole->id]],
        ],
        'is_active' => true,
    ]);
    $approval = ApprovalRequest::create([
        'module_key' => 'supplier', 'module_label' => 'Supplier', 'action' => 'create',
        'model_type' => Supplier::class, 'after_data' => [], 'status' => 'pending',
        'current_step' => 1, 'total_steps' => 2,
    ]);

    $this->actingAs($secondUser);

    expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeFalse();
});

test('lock final membuat approval dua tahap dan reject mengembalikan data ke draft', function () {
    $firstRole = Role::create(['name' => 'lock_approver_awal', 'guard_name' => 'web']);
    $secondRole = Role::create(['name' => 'lock_approver_akhir', 'guard_name' => 'web']);
    $creator = User::factory()->create(['phone' => '084444444441']);
    $first = User::factory()->create(['phone' => '084444444442']);
    $second = User::factory()->create(['phone' => '084444444443']);
    $first->assignRole($firstRole);
    $second->assignRole($secondRole);
    ApprovalSetting::create(['module_key' => 'supplier', 'module_label' => 'Supplier', 'action' => 'lock', 'requires_approval' => true, 'approval_stages' => 2, 'approver_role_ids' => [$firstRole->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$firstRole->id]], ['step' => 2, 'role_ids' => [$secondRole->id]]], 'is_active' => true]);
    $supplier = Supplier::create(['kode_supplier' => 'SUP-LOCK', 'nama_supplier' => 'Supplier Lock', 'status' => 'aktif', 'record_status' => 'locked', 'locked_by' => $creator->id, 'locked_at' => now()]);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($supplier);
    expect($approval->status)->toBe('pending')->and($approval->total_steps)->toBe(2)->and($supplier->fresh()->record_status)->toBe('locked');
    $this->actingAs($first);
    app(ApprovalWorkflowService::class)->approve($approval);
    expect($approval->fresh()->current_step)->toBe(2)->and($approval->fresh()->status)->toBe('pending');
    $this->actingAs($second);
    app(ApprovalWorkflowService::class)->reject($approval->fresh(), 'Perlu koreksi');
    expect($approval->fresh()->status)->toBe('rejected')->and($supplier->fresh()->record_status)->toBe('draft')->and($supplier->fresh()->locked_at)->toBeNull();
});

test('setting nol tahap melakukan auto approval ketika data dilock', function () {
    $user = User::factory()->create(['phone' => '084444444444']);
    ApprovalSetting::create(['module_key' => 'supplier', 'module_label' => 'Supplier', 'action' => 'lock', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true]);
    $supplier = Supplier::create(['kode_supplier' => 'SUP-AUTO', 'nama_supplier' => 'Supplier Auto', 'status' => 'aktif', 'record_status' => 'locked', 'locked_by' => $user->id, 'locked_at' => now()]);
    $this->actingAs($user);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($supplier);
    expect($approval->status)->toBe('approved')->and($approval->step_history[0]['decision'])->toBe('auto_approved');
});

test('request lock mempertahankan role reviewer saat setting berubah', function () {
    $originalRole = Role::create(['name' => 'reviewer_saat_lock', 'guard_name' => 'web']);
    $newRole = Role::create(['name' => 'reviewer_setting_baru', 'guard_name' => 'web']);
    $creator = User::factory()->create(['phone' => '084444444445']);
    $originalReviewer = User::factory()->create(['phone' => '084444444446']);
    $newReviewer = User::factory()->create(['phone' => '084444444447']);
    $originalReviewer->assignRole($originalRole);
    $newReviewer->assignRole($newRole);

    $setting = ApprovalSetting::create([
        'module_key' => 'supplier', 'module_label' => 'Supplier', 'action' => 'lock',
        'requires_approval' => true, 'approval_stages' => 1,
        'approver_role_ids' => [$originalRole->id],
        'approval_steps' => [['step' => 1, 'role_ids' => [$originalRole->id]]],
        'is_active' => true,
    ]);
    $supplier = Supplier::create([
        'kode_supplier' => 'SUP-SNAPSHOT', 'nama_supplier' => 'Supplier Snapshot',
        'status' => 'aktif', 'record_status' => 'locked',
        'locked_by' => $creator->id, 'locked_at' => now(),
    ]);

    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($supplier);
    $setting->update([
        'approval_stages' => 2,
        'approver_role_ids' => [$newRole->id],
        'approval_steps' => [
            ['step' => 1, 'role_ids' => [$newRole->id]],
            ['step' => 2, 'role_ids' => [$originalRole->id]],
        ],
    ]);

    $this->actingAs($newReviewer);
    expect(app(ApprovalWorkflowService::class)->canReview($approval->fresh()))->toBeFalse();

    $this->actingAs($originalReviewer);
    expect(app(ApprovalWorkflowService::class)->canReview($approval->fresh()))->toBeTrue()
        ->and($approval->fresh()->total_steps)->toBe(1);
});

test('request yang belum direview mengikuti perubahan setting', function () {
    $oldRole = Role::create(['name' => 'reviewer_lama', 'guard_name' => 'web']);
    $firstRole = Role::create(['name' => 'reviewer_baru_satu', 'guard_name' => 'web']);
    $secondRole = Role::create(['name' => 'reviewer_baru_dua', 'guard_name' => 'web']);
    $creator = User::factory()->create(['phone' => '084444444448']);
    $firstReviewer = User::factory()->create(['phone' => '084444444449']);
    $firstReviewer->assignRole($firstRole);
    $setting = ApprovalSetting::create([
        'module_key' => 'supplier', 'module_label' => 'Supplier', 'action' => 'lock',
        'requires_approval' => true, 'approval_stages' => 1,
        'approver_role_ids' => [$oldRole->id],
        'approval_steps' => [['step' => 1, 'role_ids' => [$oldRole->id]]],
        'is_active' => true,
    ]);
    $supplier = Supplier::create([
        'kode_supplier' => 'SUP-RECONFIG', 'nama_supplier' => 'Supplier Reconfig',
        'status' => 'aktif', 'record_status' => 'locked',
        'locked_by' => $creator->id, 'locked_at' => now(),
    ]);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($supplier);

    $setting->update([
        'approval_stages' => 2,
        'approver_role_ids' => [$firstRole->id],
        'approval_steps' => [
            ['step' => 1, 'role_ids' => [$firstRole->id]],
            ['step' => 2, 'role_ids' => [$secondRole->id]],
        ],
    ]);
    app(ApprovalWorkflowService::class)->reconfigureUntouchedPending($setting->fresh());

    $this->actingAs($firstReviewer);
    expect($approval->fresh()->total_steps)->toBe(2)
        ->and(app(ApprovalWorkflowService::class)->canReview($approval->fresh()))->toBeTrue();
});
