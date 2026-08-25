<?php

use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\ApprovalWorkflowEffectService;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function manualFinancialTransaction(User $creator): TransaksiKeuangan
{
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-FIN-'.str()->random(5),
        'nama_cabang' => 'Cabang Finance',
        'address' => 'Alamat',
        'phone' => '0411000001',
        'emaiil' => str()->random(6).'@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $cash = ChartOfAccount::query()->create([
        'kode_akun' => '1-'.str()->random(5),
        'nama_akun' => 'Kas Uji',
        'kategori' => 'aset',
        'posisi_normal' => 'debit',
        'status' => 'aktif',
    ]);
    $expense = ChartOfAccount::query()->create([
        'kode_akun' => '6-'.str()->random(5),
        'nama_akun' => 'Beban Uji',
        'kategori' => 'beban_operasional',
        'posisi_normal' => 'debit',
        'status' => 'aktif',
    ]);
    $post = TipePost::query()->create([
        'nama_post' => 'Pengeluaran Manual Uji',
        'jenis' => 'pengeluaran',
        'debit_account_id' => $expense->id,
        'credit_account_id' => $cash->id,
        'status' => 'aktif',
        'record_status' => 'locked',
    ]);

    return TransaksiKeuangan::query()->create([
        'cabang_id' => $branch->id,
        'tipe_post_id' => $post->id,
        'source_type' => 'manual_finance',
        'tanggal' => now()->toDateString(),
        'nominal' => 125000,
        'keterangan' => 'Pengeluaran untuk pengujian approval',
        'status' => 'pending_approval',
        'record_status' => 'locked',
        'locked_at' => now(),
        'locked_by' => $creator->id,
        'user_id' => $creator->id,
    ]);
}

test('zero stage approval posts a manual transaction once', function () {
    $creator = User::factory()->create(['phone' => '081230000001']);
    ApprovalSetting::query()->updateOrCreate(
        ['module_key' => 'financial-transaction', 'action' => 'lock'],
        ['module_label' => 'Transaksi Kas & Bank Manual', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true],
    );
    $transaction = manualFinancialTransaction($creator);

    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($transaction, 'financial-transaction');
    app(ApprovalWorkflowEffectService::class)->approved($transaction->fresh(), $approval);

    expect($approval->status)->toBe('approved')
        ->and($transaction->fresh()->status)->toBe('posted')
        ->and($transaction->fresh()->journal_id)->not->toBeNull()
        ->and(Journal::query()->where('source_type', TransaksiKeuangan::class)->where('source_id', $transaction->id)->count())->toBe(1);
});

test('one to three approval stages only post after the final active role', function (int $stages) {
    $creator = User::factory()->create(['phone' => '08123100000'.$stages]);
    $roles = collect(range(1, $stages))->map(fn (int $step) => Role::create([
        'name' => "financial_reviewer_{$stages}_{$step}",
        'guard_name' => 'web',
    ]));
    $reviewers = $roles->map(function (Role $role, int $index) use ($stages) {
        $user = User::factory()->create(['phone' => '0822'.$stages.str_pad((string) $index, 7, '0', STR_PAD_LEFT)]);
        $user->assignRole($role);

        return $user;
    });
    ApprovalSetting::query()->updateOrCreate(
        ['module_key' => 'financial-transaction', 'action' => 'lock'],
        [
            'module_label' => 'Transaksi Kas & Bank Manual',
            'requires_approval' => true,
            'approval_stages' => $stages,
            'approver_role_ids' => [$roles->first()->id],
            'approval_steps' => $roles->values()->map(fn (Role $role, int $index) => ['step' => $index + 1, 'role_ids' => [$role->id]])->all(),
            'is_active' => true,
        ],
    );
    $transaction = manualFinancialTransaction($creator);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($transaction, 'financial-transaction');

    foreach ($reviewers as $index => $reviewer) {
        $this->actingAs($reviewer);
        app(ApprovalWorkflowService::class)->approve($approval->fresh());
        if ($index + 1 === $stages) {
            expect($transaction->fresh()->journal_id)->not->toBeNull();
        } else {
            expect($transaction->fresh()->journal_id)->toBeNull();
        }
    }

    expect($transaction->fresh()->status)->toBe('posted');
})->with([1, 2, 3]);

test('reject returns a manual transaction to draft without posting', function () {
    $creator = User::factory()->create(['phone' => '081230000009']);
    $role = Role::create(['name' => 'financial_rejector', 'guard_name' => 'web']);
    $reviewer = User::factory()->create(['phone' => '081230000010']);
    $reviewer->assignRole($role);
    ApprovalSetting::query()->updateOrCreate(
        ['module_key' => 'financial-transaction', 'action' => 'lock'],
        ['module_label' => 'Transaksi Kas & Bank Manual', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]], 'is_active' => true],
    );
    $transaction = manualFinancialTransaction($creator);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($transaction, 'financial-transaction');

    $this->actingAs($reviewer);
    app(ApprovalWorkflowService::class)->reject($approval, 'Bukti belum sesuai.');

    expect($transaction->fresh()->record_status)->toBe('draft')
        ->and($transaction->fresh()->status)->toBe('rejected')
        ->and($transaction->fresh()->journal_id)->toBeNull();
});
