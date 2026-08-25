<?php

use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\FieldDefect;
use App\Models\InternalHandover;
use App\Models\Perumahan;
use App\Models\QualityInspection;
use App\Models\SafetyReport;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function fieldUnitContext(): array
{
    $branch = CabangPerusahaan::query()->create(['kode_cabang' => 'CB-FIELD', 'nama_cabang' => 'Cabang Lapangan', 'address' => 'Alamat', 'phone' => '041199991', 'emaiil' => 'field@example.test', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::query()->create(['cabang_id' => $branch->id, 'kode_proyek' => 'PRJ-FIELD', 'nama_perusahaan' => 'Perumahan Lapangan', 'alamat' => 'Alamat', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => now(), 'status' => 'aktif', 'record_status' => 'locked']);
    $unit = DetailRumah::query()->create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01', 'tipe_rumah' => '36', 'luas_bangunan' => 36, 'luas_tanah' => 72, 'harga_jual' => 1, 'status_penjualan' => 'tersedia', 'status_pembangunan' => 'belum_dibangun', 'progress_terakhir' => 0, 'status' => 'aktif', 'record_status' => 'locked']);
    return [$housing, $unit];
}

test('quality inspection follows active multi stage roles and creates one defect only after final approval', function () {
    [$housing, $unit] = fieldUnitContext();
    $creator = User::factory()->create(['phone' => '081237700001']);
    $roles = collect([1, 2])->map(fn ($step) => Role::create(['name' => "qc_reviewer_{$step}", 'guard_name' => 'web']));
    $reviewers = $roles->map(function ($role, $step) { $user = User::factory()->create(['phone' => '08123770000'.($step + 2)]); $user->assignRole($role); return $user; });
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'quality-inspection', 'action' => 'lock'], ['module_label' => 'Kontrol Kualitas', 'requires_approval' => true, 'approval_stages' => 2, 'approver_role_ids' => [$roles[0]->id], 'approval_steps' => $roles->map(fn ($role, $step) => ['step' => $step + 1, 'role_ids' => [$role->id]])->all(), 'is_active' => true]);
    $inspection = QualityInspection::query()->create(['kode_inspeksi' => 'QC-001', 'tanggal' => now(), 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'hasil' => 'defect', 'item_pemeriksaan' => 'Dinding', 'temuan' => 'Retak', 'tindakan_perbaikan' => 'Perbaiki', 'status' => 'terbuka', 'approval_status' => 'menunggu_approval_manager', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $creator->id, 'created_by' => $creator->id]);
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($inspection, 'quality-inspection');
    $this->actingAs($reviewers[0]); app(ApprovalWorkflowService::class)->approve($approval->fresh());
    expect(FieldDefect::query()->count())->toBe(0)->and($inspection->fresh()->approval_status)->not->toBe('approved');
    $this->actingAs($reviewers[1]); app(ApprovalWorkflowService::class)->approve($approval->fresh());
    expect($inspection->fresh()->approval_status)->toBe('approved')->and(FieldDefect::query()->where('quality_inspection_id', $inspection->id)->count())->toBe(1);
    app(\App\Services\ApprovalWorkflowEffectService::class)->approved($inspection->fresh(), $approval->fresh());
    expect(FieldDefect::query()->where('quality_inspection_id', $inspection->id)->count())->toBe(1);
});

test('rejected safety report returns to draft for correction', function () {
    [$housing, $unit] = fieldUnitContext();
    $creator = User::factory()->create(['phone' => '081237710001']);
    $role = Role::create(['name' => 'safety_reviewer', 'guard_name' => 'web']);
    $reviewer = User::factory()->create(['phone' => '081237710002']); $reviewer->assignRole($role);
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'field-safety', 'action' => 'lock'], ['module_label' => 'Lapangan K3', 'requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]], 'is_active' => true]);
    $report = SafetyReport::query()->create(['kode_k3' => 'K3-001', 'tanggal' => now(), 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'kategori' => 'unsafe_action', 'tingkat_risiko' => 'tinggi', 'temuan' => 'Tanpa APD', 'tindakan' => 'Wajib APD', 'status' => 'open', 'approval_status' => 'menunggu_approval_manager', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $creator->id, 'created_by' => $creator->id]);
    $this->actingAs($creator); $approval = app(ApprovalWorkflowService::class)->submitLocked($report, 'field-safety');
    $this->actingAs($reviewer); app(ApprovalWorkflowService::class)->reject($approval, 'Bukti APD belum lengkap.');
    expect($report->fresh()->record_status)->toBe('draft')->and($report->fresh()->approval_status)->toBe('rejected')->and($report->fresh()->approved_at)->toBeNull();
});

test('final internal handover approval updates linked unit once', function () {
    [$housing, $unit] = fieldUnitContext();
    $creator = User::factory()->create(['phone' => '081237720001']);
    ApprovalSetting::query()->updateOrCreate(['module_key' => 'field-handover', 'action' => 'lock'], ['module_label' => 'Serah Terima Internal', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true]);
    $handover = InternalHandover::query()->create(['kode_serah_terima' => 'STI-001', 'tanggal' => now(), 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'progress_unit' => 100, 'kondisi_bangunan' => 'baik', 'checklist' => 'Lengkap', 'status' => 'siap', 'approval_status' => 'menunggu_approval_manager', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $creator->id, 'created_by' => $creator->id]);
    $this->actingAs($creator); app(ApprovalWorkflowService::class)->submitLocked($handover, 'field-handover');
    expect($handover->fresh()->approval_status)->toBe('approved')->and($unit->fresh()->status_pembangunan)->toBe('selesai')->and((float) $unit->fresh()->progress_terakhir)->toBe(100.0);
});

test('workspace is available to pengawas and preserves selected context', function () {
    [$housing, $unit] = fieldUnitContext();
    Permission::findOrCreate('field-supervision.view', 'web');
    $role = Role::findOrCreate('pengawas', 'web'); $role->givePermissionTo('field-supervision.view');
    $user = User::factory()->create(['phone' => '081237730001']); $user->assignRole($role);
    $this->actingAs($user)->get('/admin/pengawasan?perumahan_id='.$housing->id.'&detail_rumah_id='.$unit->id)->assertOk()->assertInertia(fn ($page) => $page->component('Admin/FieldWorkspace/Index')->where('context.perumahan_id', (string) $housing->id)->where('context.detail_rumah_id', (string) $unit->id)->has('cards', 10));
});
