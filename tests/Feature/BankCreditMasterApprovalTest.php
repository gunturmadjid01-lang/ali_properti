<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\BankKredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function bankCreditApprovalUser(array $permissions, ?Role $role = null): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    if ($role) {
        $user->assignRole($role);
    }

    return $user;
}

function bankCreditApprovalSetting(array $roles): ApprovalSetting
{
    return ApprovalSetting::query()->updateOrCreate(
        ['module_key' => 'bank-credit-master', 'action' => 'lock'],
        [
            'module_label' => 'Master Bank Kredit',
            'requires_approval' => count($roles) > 0,
            'approval_stages' => count($roles),
            'approver_role_ids' => isset($roles[0]) ? [$roles[0]->id] : [],
            'approval_steps' => collect($roles)->values()->map(fn (Role $role, int $index) => ['step' => $index + 1, 'role_ids' => [$role->id]])->all(),
            'is_active' => true,
        ],
    );
}

function draftBank(string $code): BankKredit
{
    return BankKredit::create([
        'kode_bank' => $code,
        'nama_bank' => 'Bank '.$code,
        'jenis_bank' => 'konvensional',
        'status' => 'aktif',
        'record_status' => 'draft',
    ]);
}

test('master bank kredit nol tahap langsung final dan terlihat sebagai approved pada tabel', function () {
    bankCreditApprovalSetting([]);
    $user = bankCreditApprovalUser(['bank-credit-master.view', 'bank-credit-master.submit']);
    $bank = draftBank('AUTO');

    $this->actingAs($user)->post(route('admin.bank-master.lock', $bank->id))->assertRedirect();

    expect($bank->fresh()->record_status)->toBe('locked')
        ->and(ApprovalRequest::query()->where('model_id', $bank->id)->sole()->status)->toBe(ApprovalRequest::STATUS_APPROVED)
        ->and(BankKredit::query()->finalized()->whereKey($bank)->exists())->toBeTrue();

    $this->get(route('admin.bank-master.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.submit', true)
        ->where('rows.data.0.record_status', 'locked')
        ->where('rows.data.0.approval_status', 'approved'));
});

test('master bank kredit mengikuti satu sampai tiga role tahap setting approval', function (int $stageCount) {
    $roles = collect(range(1, $stageCount))->map(fn (int $stage) => Role::findOrCreate("bank_master_approver_{$stageCount}_{$stage}", 'web'))->all();
    bankCreditApprovalSetting($roles);
    $submitter = bankCreditApprovalUser(['bank-credit-master.submit']);
    $bank = draftBank('STAGE-'.$stageCount);

    $this->actingAs($submitter)->post(route('admin.bank-master.lock', $bank->id))->assertRedirect();
    $approval = ApprovalRequest::query()->where('model_id', $bank->id)->sole();
    expect($approval->total_steps)->toBe($stageCount)->and($approval->status)->toBe('pending');

    foreach ($roles as $index => $role) {
        $approver = bankCreditApprovalUser(['bank-credit-master.view'], $role);
        $this->actingAs($approver)->get(route('admin.bank-master.index'))->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.approval_stage', ($index + 1).'/'.$stageCount)
            ->where('rows.data.0.can_review', true));
        $this->post(route('admin.bank-master.review', [$bank->id, 'approve']))->assertRedirect();
    }

    expect($approval->fresh()->status)->toBe('approved');
})->with([1, 2, 3]);

test('reject mengembalikan bank ke draft dan unlock dapat diajukan ulang', function () {
    $role = Role::findOrCreate('bank_master_reviewer', 'web');
    bankCreditApprovalSetting([$role]);
    $submitter = bankCreditApprovalUser(['bank-credit-master.submit', 'bank-credit-master.update']);
    $reviewer = bankCreditApprovalUser([], $role);
    $bank = draftBank('RETRY');

    $this->actingAs($submitter)->post(route('admin.bank-master.lock', $bank->id));
    $first = ApprovalRequest::query()->sole();
    $this->actingAs($reviewer)->post(route('admin.bank-master.review', [$bank->id, 'reject']), ['note' => 'Perbaiki data bank'])->assertRedirect();
    expect($bank->fresh()->record_status)->toBe('draft')->and($first->fresh()->status)->toBe('rejected');

    $this->actingAs($submitter)->post(route('admin.bank-master.lock', $bank->id));
    $second = ApprovalRequest::query()->latest('id')->firstOrFail();
    $this->post(route('admin.bank-master.unlock', $bank->id))->assertRedirect();

    expect($second->fresh()->status)->toBe('rejected')
        ->and($bank->fresh()->record_status)->toBe('draft')
        ->and(ApprovalRequest::query()->count())->toBe(2);
});

test('master bank kredit locked tidak dapat diedit atau dihapus', function () {
    $user = bankCreditApprovalUser(['bank-credit-master.update', 'bank-credit-master.delete']);
    $bank = draftBank('LOCKED');
    $bank->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $user->id]);

    $this->actingAs($user)->get(route('admin.bank-master.edit', $bank))->assertStatus(422);
    $this->delete(route('admin.bank-master.destroy', $bank))->assertStatus(422);
    expect(BankKredit::query()->whereKey($bank)->exists())->toBeTrue();
});
