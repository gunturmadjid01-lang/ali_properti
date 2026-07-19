<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\ChartOfAccount;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeSalary;
use App\Models\Journal;
use App\Models\JobPosition;
use App\Models\PayrollBatch;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function payrollActor(string $role = 'keuangan'): User
{
    ChartOfAccount::firstOrCreate(['kode_akun' => ChartOfAccount::KAS_BANK], ['nama_akun' => 'Kas dan Bank', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif', 'is_system' => true]);
    ChartOfAccount::firstOrCreate(['kode_akun' => ChartOfAccount::BEBAN_GAJI], ['nama_akun' => 'Beban Gaji dan Tenaga Kerja', 'kategori' => 'beban_operasional', 'posisi_normal' => 'debit', 'status' => 'aktif', 'is_system' => true]);
    ChartOfAccount::firstOrCreate(['kode_akun' => '1-1200'], ['nama_akun' => 'Piutang Lain-lain', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif', 'is_system' => true]);
    ChartOfAccount::firstOrCreate(['kode_akun' => '2-2300'], ['nama_akun' => 'Hutang Operasional', 'kategori' => 'liabilitas', 'posisi_normal' => 'kredit', 'status' => 'aktif', 'is_system' => true]);
    Permission::findOrCreate('payroll.view'); Permission::findOrCreate('payroll.manage');
    $user = User::factory()->create(['phone' => fake()->unique()->numerify('0812########')]); $user->assignRole(Role::findOrCreate($role)); $user->givePermissionTo(['payroll.view', 'payroll.manage']); return $user;
}

function payrollEmployee(string $name): User
{
    $position = JobPosition::firstOrCreate(['normalized_name' => 'staf keuangan'], ['name' => 'Staf Keuangan', 'is_active' => true]);
    return User::factory()->create(['name' => $name, 'phone' => fake()->unique()->numerify('0813########'), 'employee_number' => 'PEG-'.str()->random(5), 'job_title' => $position->name, 'job_position_id' => $position->id, 'employment_status' => 'aktif']);
}

function payrollPayload(User ...$employees): array
{
    return ['period' => '2026-07', 'payment_date' => '2026-07-25', 'notes' => 'Payroll Juli', 'items' => collect($employees)->map(fn ($u, $i) => ['user_id' => $u->id, 'basic_salary' => 5000000 + ($i * 100000), 'fixed_allowance' => 500000, 'other_allowance' => 250000, 'deductions' => 100000, 'notes' => 'Slip '.$u->name])->all()];
}

test('role keuangan dapat membuat satu transaksi berisi banyak pegawai yang memiliki jabatan', function () {
    $finance = payrollActor(); $a = payrollEmployee('Pegawai A'); $b = payrollEmployee('Pegawai B');
    $this->actingAs($finance)->get('/admin/gaji-pegawai/create')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/EmployeeSalary/FormPage')->has('employees', 2));
    $this->post('/admin/gaji-pegawai', payrollPayload($a, $b))->assertRedirect()->assertSessionHasNoErrors();
    $batch = PayrollBatch::with('items')->firstOrFail();
    expect($batch->items)->toHaveCount(2)->and((float) $batch->total_net)->toBe(11400000.0)->and($batch->record_status)->toBe('draft');
});

test('user aktif tanpa jabatan tidak dapat dimasukkan ke penggajian', function () {
    $finance = payrollActor(); $employee = User::factory()->create(['phone' => '081400000001', 'employment_status' => 'aktif', 'job_position_id' => null]);
    $this->actingAs($finance)->post('/admin/gaji-pegawai', payrollPayload($employee))->assertSessionHasErrors('items.0.user_id');
    expect(PayrollBatch::count())->toBe(0);
});

test('nol tahap mengesahkan batch dan invoice memuat semua slip', function () {
    $finance = payrollActor(); $a = payrollEmployee('Andi Payroll'); $b = payrollEmployee('Budi Payroll');
    ApprovalSetting::updateOrCreate(['module_key'=>'employee-payroll','action'=>'lock'],['module_label'=>'Penggajian Pegawai','requires_approval'=>false,'approval_stages'=>0,'approver_role_ids'=>[],'approval_steps'=>[],'is_active'=>true]);
    $this->actingAs($finance)->post('/admin/gaji-pegawai', payrollPayload($a,$b)); $batch=PayrollBatch::firstOrFail();
    $this->post("/admin/gaji-pegawai/{$batch->id}/lock")->assertRedirect();
    expect($batch->fresh()->status)->toBe('approved')->and(ApprovalRequest::where('module_key','employee-payroll')->first()->status)->toBe('approved')->and(Journal::where(['source_type'=>PayrollBatch::class,'source_id'=>$batch->id,'type'=>'employee_payroll'])->count())->toBe(1);
    $this->get("/admin/gaji-pegawai/{$batch->id}/invoice")->assertOk()->assertSee('Andi Payroll')->assertSee('Budi Payroll')->assertDontSee('PREVIEW - BELUM FINAL');
});

test('approval bertahap membatasi reviewer dan reject mengembalikan transaksi ke draft', function () {
    $finance=payrollActor(); $employee=payrollEmployee('Pegawai Review'); $reviewRole=Role::findOrCreate('reviewer_payroll'); $reviewer=User::factory()->create(['phone'=>'081400000002']); $reviewer->assignRole($reviewRole); $outsider=User::factory()->create(['phone'=>'081400000003']);
    ApprovalSetting::updateOrCreate(['module_key'=>'employee-payroll','action'=>'lock'],['module_label'=>'Penggajian Pegawai','requires_approval'=>true,'approval_stages'=>1,'approver_role_ids'=>[$reviewRole->id],'approval_steps'=>[['step'=>1,'role_ids'=>[$reviewRole->id]]],'is_active'=>true]);
    $this->actingAs($finance)->post('/admin/gaji-pegawai',payrollPayload($employee)); $batch=PayrollBatch::firstOrFail(); $this->post("/admin/gaji-pegawai/{$batch->id}/lock"); $approval=ApprovalRequest::firstOrFail();
    $this->actingAs($outsider); expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeFalse();
    $this->actingAs($reviewer); expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeTrue(); app(ApprovalWorkflowService::class)->reject($approval,'Nominal perlu diperbaiki');
    expect($batch->fresh()->record_status)->toBe('draft')->and($batch->fresh()->status)->toBe('draft');
});

test('halaman data menampilkan statistik penggajian dan terpisah dari form', function () {
    $finance=payrollActor(); $employee=payrollEmployee('Pegawai Statistik');
    $this->actingAs($finance)->post('/admin/gaji-pegawai',payrollPayload($employee));
    $this->get('/admin/gaji-pegawai')->assertOk()->assertInertia(fn(Assert $p)=>$p->component('Admin/EmployeeSalary/Index')->where('statistics.transactions',1)->has('rows.data',1));
});

test('penggajian mengikuti satu sampai tiga tahap setting approval secara berurutan', function (int $stages) {
    $finance=payrollActor(); $employee=payrollEmployee('Pegawai '.$stages.' Tahap'); $roles=[]; $reviewers=[];
    for($step=1;$step<=$stages;$step++){ $roles[$step]=Role::findOrCreate("payroll_step_{$stages}_{$step}"); $reviewers[$step]=User::factory()->create(['phone'=>'082'.$stages.$step.str_pad((string)$step,7,'0')]); $reviewers[$step]->assignRole($roles[$step]); }
    ApprovalSetting::updateOrCreate(['module_key'=>'employee-payroll','action'=>'lock'],['module_label'=>'Penggajian Pegawai','requires_approval'=>true,'approval_stages'=>$stages,'approver_role_ids'=>[$roles[1]->id],'approval_steps'=>collect(range(1,$stages))->map(fn($step)=>['step'=>$step,'role_ids'=>[$roles[$step]->id]])->all(),'is_active'=>true]);
    $this->actingAs($finance)->post('/admin/gaji-pegawai',payrollPayload($employee)); $batch=PayrollBatch::firstOrFail(); $this->post("/admin/gaji-pegawai/{$batch->id}/lock"); $approval=ApprovalRequest::firstOrFail();
    foreach(range(1,$stages) as $step){ $this->actingAs($reviewers[$step]); expect(app(ApprovalWorkflowService::class)->canReview($approval->fresh()))->toBeTrue(); app(ApprovalWorkflowService::class)->approve($approval->fresh()); }
    expect($approval->fresh()->status)->toBe('approved')->and($batch->fresh()->status)->toBe('approved');
})->with([1,2,3]);

test('unlock membatalkan approval pending dan memungkinkan resubmit', function () {
    $finance=payrollActor(); $employee=payrollEmployee('Pegawai Unlock'); $role=Role::findOrCreate('payroll_unlock_reviewer');
    ApprovalSetting::updateOrCreate(['module_key'=>'employee-payroll','action'=>'lock'],['module_label'=>'Penggajian Pegawai','requires_approval'=>true,'approval_stages'=>1,'approver_role_ids'=>[$role->id],'approval_steps'=>[['step'=>1,'role_ids'=>[$role->id]]],'is_active'=>true]);
    $this->actingAs($finance)->post('/admin/gaji-pegawai',payrollPayload($employee)); $batch=PayrollBatch::firstOrFail(); $this->post("/admin/gaji-pegawai/{$batch->id}/lock"); $first=ApprovalRequest::firstOrFail();
    $this->post("/admin/gaji-pegawai/{$batch->id}/unlock")->assertRedirect(); expect($first->fresh()->status)->toBe('rejected')->and($batch->fresh()->record_status)->toBe('draft');
    $this->post("/admin/gaji-pegawai/{$batch->id}/lock"); expect(ApprovalRequest::count())->toBe(2)->and(ApprovalRequest::latest('id')->first()->status)->toBe('pending');
});

test('lookup transaksi otomatis mengambil daftar gaji aktif sesuai periode', function () {
    $finance=payrollActor();$employee=payrollEmployee('Pegawai Gaji Aktif');EmployeeSalary::create(['user_id'=>$employee->id,'basic_salary'=>7000000,'fixed_allowance'=>800000,'effective_from'=>'2026-01-01','is_active'=>true,'created_by'=>$finance->id]);
    $this->actingAs($finance)->getJson('/admin/gaji-pegawai/lookup/active-salaries?period=2026-07&user_ids[]='.$employee->id)->assertOk()->assertJsonPath('salaries.'.$employee->id.'.basic_salary',7000000)->assertJsonPath('salaries.'.$employee->id.'.fixed_allowance',800000);
});

test('statistik penggajian mengisi bulan kosong dengan nol', function () {
    $finance=payrollActor();$this->actingAs($finance)->get('/admin/gaji-pegawai?from_period=2026-01&to_period=2026-03')->assertOk()->assertInertia(fn(Assert $p)=>$p->has('trend',3)->where('trend.0.period','2026-01')->where('trend.0.total',0)->where('trend.1.total',0)->where('trend.2.total',0));
});

test('panjar approved otomatis memotong payroll periode tujuan dan masuk jurnal', function () {
    $finance=payrollActor();$employee=payrollEmployee('Pegawai Panjar');EmployeeSalary::create(['user_id'=>$employee->id,'basic_salary'=>5000000,'fixed_allowance'=>500000,'effective_from'=>'2026-01-01','is_active'=>true,'created_by'=>$finance->id]);ApprovalSetting::updateOrCreate(['module_key'=>'employee-advance','action'=>'lock'],['module_label'=>'Panjar Pegawai','requires_approval'=>false,'approval_stages'=>0,'approver_role_ids'=>[],'approval_steps'=>[],'is_active'=>true]);
    $this->actingAs($finance)->post('/admin/panjar-pegawai',['user_id'=>$employee->id,'advance_date'=>'2026-07-05','deduction_period'=>'2026-07','amount'=>1000000,'purpose'=>'Keperluan keluarga']);$advance=EmployeeAdvance::firstOrFail();$this->post('/admin/panjar-pegawai/'.$advance->id.'/lock');expect($advance->fresh()->status)->toBe('approved');ApprovalSetting::updateOrCreate(['module_key'=>'employee-payroll','action'=>'lock'],['module_label'=>'Penggajian Pegawai','requires_approval'=>false,'approval_stages'=>0,'approver_role_ids'=>[],'approval_steps'=>[],'is_active'=>true]);
    $this->post('/admin/gaji-pegawai',['period'=>'2026-07','payment_date'=>'2026-07-25','items'=>[['user_id'=>$employee->id,'basic_salary'=>5000000,'fixed_allowance'=>500000,'other_allowance'=>0,'deductions'=>0]]]);$batch=PayrollBatch::firstOrFail();$item=$batch->items()->firstOrFail();expect((float)$item->advance_deduction)->toBe(1000000.0)->and((float)$item->net_salary)->toBe(4500000.0)->and($advance->fresh()->allocation)->not->toBeNull();$this->post('/admin/gaji-pegawai/'.$batch->id.'/lock');expect(Journal::where('type','employee_advance')->count())->toBe(1)->and(Journal::where('type','employee_payroll')->count())->toBe(1);
});
