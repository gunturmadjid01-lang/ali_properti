<?php

use App\Models\ApprovalSetting;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function manualJournalDraft(User $creator): Journal
{
    $debit = ChartOfAccount::query()->create(['kode_akun' => '1-'.str()->random(5), 'nama_akun' => 'Kas Uji', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif']);
    $credit = ChartOfAccount::query()->create(['kode_akun' => '3-'.str()->random(5), 'nama_akun' => 'Modal Uji', 'kategori' => 'ekuitas', 'posisi_normal' => 'kredit', 'status' => 'aktif']);
    $journal = Journal::query()->create(['nomor_jurnal' => 'DRAFT-'.str()->uuid(), 'tanggal' => now(), 'type' => 'manual', 'record_status' => 'locked', 'total_debit' => 100000, 'total_kredit' => 100000, 'keterangan' => 'Uji approval jurnal', 'created_by' => $creator->id, 'locked_by' => $creator->id, 'locked_at' => now()]);
    $journal->details()->createMany([['chart_of_account_id' => $debit->id, 'debit' => 100000, 'kredit' => 0], ['chart_of_account_id' => $credit->id, 'debit' => 0, 'kredit' => 100000]]);
    return $journal;
}

test('zero stage manual journal is posted once with deterministic number', function () {
    $creator = User::factory()->create(['phone' => '081239000001']);
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'manual-journal', 'action' => 'lock'], ['module_label' => 'Jurnal Umum Manual', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true]);
    $journal = manualJournalDraft($creator);
    $this->actingAs($creator);
    app(ApprovalWorkflowService::class)->submitLocked($journal, 'manual-journal');

    $journal->refresh();
    expect($journal->record_status)->toBe('posted')
        ->and($journal->posted_at)->not->toBeNull()
        ->and($journal->nomor_jurnal)->toBe('JRN-MANUAL-'.$journal->tanggal->format('Ymd').'-'.str_pad((string) $journal->id, 8, '0', STR_PAD_LEFT));
});

test('manual journal only posts after final configured stage', function (int $stages) {
    $creator = User::factory()->create(['phone' => '08123910000'.$stages]);
    $roles = collect(range(1, $stages))->map(fn ($step) => Role::create(['name' => "journal_reviewer_{$stages}_{$step}", 'guard_name' => 'web']));
    $reviewers = $roles->map(function ($role, $step) use ($stages) { $user = User::factory()->create(['phone' => '08123'.$stages.str_pad((string) $step, 6, '0', STR_PAD_LEFT)]); $user->assignRole($role); return $user; });
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'manual-journal', 'action' => 'lock'], ['module_label' => 'Jurnal Umum Manual', 'requires_approval' => true, 'approval_stages' => $stages, 'approver_role_ids' => [$roles[0]->id], 'approval_steps' => $roles->map(fn ($role, $step) => ['step' => $step + 1, 'role_ids' => [$role->id]])->all(), 'is_active' => true]);
    $journal = manualJournalDraft($creator);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($journal, 'manual-journal');
    foreach ($reviewers as $step => $reviewer) {
        $this->actingAs($reviewer);
        app(ApprovalWorkflowService::class)->approve($approval->fresh());
        expect($journal->fresh()->record_status)->toBe($step + 1 === $stages ? 'posted' : 'locked');
    }
})->with([1, 2, 3]);

test('reject returns a manual journal to editable draft without posting', function () {
    $creator = User::factory()->create(['phone' => '081239200001']);
    $role = Role::create(['name' => 'journal_rejector', 'guard_name' => 'web']);
    $reviewer = User::factory()->create(['phone' => '081239200002']);
    $reviewer->assignRole($role);
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'manual-journal', 'action' => 'lock'], ['module_label' => 'Jurnal Umum Manual', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]], 'is_active' => true]);
    $journal = manualJournalDraft($creator);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($journal, 'manual-journal');
    $this->actingAs($reviewer);
    app(ApprovalWorkflowService::class)->reject($approval, 'Jurnal perlu dikoreksi.');
    expect($journal->fresh()->record_status)->toBe('draft')->and($journal->fresh()->posted_at)->toBeNull()->and($journal->fresh()->nomor_jurnal)->toStartWith('DRAFT-');
});
