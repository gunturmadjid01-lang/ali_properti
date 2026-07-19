<?php

use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\HppRealisasi;
use App\Models\Journal;
use App\Models\Perumahan;
use App\Models\PettyCashAccount;
use App\Models\PettyCashExpense;
use App\Models\PettyCashFunding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function pettyCashUser(string $role, string $phone): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create(['phone' => $phone]);
    $user->assignRole($role);

    return $user;
}

test('owner has default access to petty cash modules', function (string $role) {
    $user = pettyCashUser($role, '08123'.random_int(100000, 999999));

    $this->actingAs($user)
        ->get('/admin/kas-kecil/saldo')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PettyCash/Index')
            ->where('section', 'saldo')
            ->has('accounts'));
})->with(['owner']);

test('every new user automatically receives an empty personal petty cash account', function (string $role) {
    $user = pettyCashUser($role, '08124'.random_int(100000, 999999));

    $account = PettyCashAccount::query()->where('assigned_user_id', $user->id)->sole();

    expect((float) $account->balance)->toBe(0.0)
        ->and((float) $account->target_amount)->toBe(0.0)
        ->and($account->name)->toBe('Kas Kecil - '.$user->name);

    $this->actingAs($user)->get('/admin/kas-kecil/saldo')->assertOk();
})->with(['manager', 'keuangan', 'petugas']);

test('first funding approval completes before finance disbursement increases balance once', function () {
    Storage::fake('public');
    $owner = pettyCashUser('owner', '081230000001');
    $finance = pettyCashUser('keuangan', '081230000004');
    $finance->givePermissionTo(Permission::findOrCreate('petty-cash.disburse', 'web'));

    $account = $owner->pettyCashAccounts()->sole();
    $this->actingAs($owner)->post('/admin/kas-kecil/pengisian', [
        'petty_cash_account_id' => $account->id,
        'amount' => 10000000,
        'request_date' => '2026-07-13',
        'request_notes' => 'Pengisian saldo awal',
        'status' => 'pending',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $funding = PettyCashFunding::query()->firstOrFail();
    expect((float) $account->balance)->toBe(0.0)
        ->and($funding->status)->toBe('approved');

    $this->actingAs($finance)->post("/admin/kas-kecil/pengisian/{$funding->id}/cairkan", [])
        ->assertSessionHasErrors('approval_proof');

    $this->post("/admin/kas-kecil/pengisian/{$funding->id}/cairkan", [
        'approval_proof' => UploadedFile::fake()->create('transfer.pdf', 120, 'application/pdf'),
        'approval_notes' => 'Transfer berhasil',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect((float) $account->fresh()->balance)->toBe(10000000.0)
        ->and($funding->fresh()->status)->toBe('disbursed')
        ->and($account->ledgers()->count())->toBe(1)
        ->and(Journal::query()->where('source_type', PettyCashFunding::class)->where('source_id', $funding->id)->exists())->toBeTrue();
});

test('petty cash accounts and transactions are isolated per assigned user', function () {
    Storage::fake('public');
    $first = pettyCashUser('keuangan', '081230000011');
    $second = pettyCashUser('keuangan', '081230000012');
    $firstAccount = PettyCashAccount::query()->create(['code' => 'KK-U1', 'name' => 'Kas User 1', 'assigned_user_id' => $first->id, 'balance' => 500000, 'target_amount' => 500000, 'minimum_balance' => 50000, 'created_by' => $first->id]);
    $secondAccount = PettyCashAccount::query()->create(['code' => 'KK-U2', 'name' => 'Kas User 2', 'assigned_user_id' => $second->id, 'balance' => 500000, 'target_amount' => 500000, 'minimum_balance' => 50000, 'created_by' => $second->id]);

    $this->actingAs($first)->get('/admin/kas-kecil/saldo')->assertInertia(fn (Assert $page) => $page
        ->has('accounts', 1)->where('accounts.0.id', $firstAccount->id)->where('accounts.0.assigned_user', $first->name));

    $this->actingAs($first)->post('/admin/kas-kecil/pengeluaran', [
        'petty_cash_account_id' => $secondAccount->id, 'expense_date' => '2026-07-16', 'category' => 'atk',
        'amount' => 10000, 'description' => 'Tidak boleh memakai kas user lain',
        'proof' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    expect((float) $secondAccount->fresh()->balance)->toBe(500000.0);
});

test('any assigned user receives petty cash access without finance role', function () {
    $owner = pettyCashUser('owner', '081230000021');
    $staff = pettyCashUser('petugas', '081230000022');
    $account = PettyCashAccount::query()->create([
        'code' => 'KK-PETUGAS', 'name' => 'Kas Petugas', 'assigned_user_id' => $staff->id,
        'balance' => 100000, 'target_amount' => 100000, 'minimum_balance' => 10000, 'created_by' => $owner->id,
    ]);

    $this->actingAs($staff)->get('/admin/kas-kecil/saldo')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('petty-cash.view'))
        ->has('accounts', 1)->where('accounts.0.id', $account->id)
        ->where('permissions.can_create', true)->where('permissions.can_create_account', false));
});

test('construction expense is automatically assigned to unit hpp and reduces balance', function () {
    Storage::fake('public');
    $finance = pettyCashUser('keuangan', '081230000002');
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-T', 'nama_cabang' => 'Cabang Test', 'address' => 'Alamat', 'phone' => '0411000000',
        'emaiil' => 'cabang@example.test', 'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $project = Perumahan::query()->create([
        'cabang_id' => $branch->id, 'kode_proyek' => 'PRJ-T', 'nama_perusahaan' => 'Griya Test',
        'alamat' => 'Alamat proyek', 'luas_lahan' => '1000', 'jumlah_unit' => 1,
        'tanggal_mulai' => '2026-01-01', 'status' => 'aktif',
    ]);
    $unit = DetailRumah::query()->create([
        'perumahan_id' => $project->id, 'kode_nlok' => 'A', 'nomor_rumah' => '05',
        'luas_tanah' => '72', 'harga_jual' => 350000000, 'status' => 'aktif',
    ]);
    $account = PettyCashAccount::query()->create([
        'code' => 'KK-TEST', 'name' => 'Kas Test', 'target_amount' => 1000000,
        'balance' => 1000000, 'minimum_balance' => 100000, 'created_by' => $finance->id,
    ]);

    $this->actingAs($finance)->post('/admin/kas-kecil/pengeluaran', [
        'petty_cash_account_id' => $account->id,
        'expense_date' => '2026-07-13',
        'category' => 'material',
        'perumahan_id' => $project->id,
        'detail_rumah_id' => $unit->id,
        'amount' => 120000,
        'description' => 'Pembelian semen tambahan',
        'proof' => UploadedFile::fake()->create('nota.pdf', 90, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $expense = PettyCashExpense::query()->firstOrFail();
    expect($expense->cost_type)->toBe('unit_hpp')
        ->and((float) $account->fresh()->balance)->toBe(880000.0)
        ->and(HppRealisasi::query()->where('source_type', PettyCashExpense::class)->where('source_id', $expense->id)->exists())->toBeTrue()
        ->and(Journal::query()->where('source_type', PettyCashExpense::class)->where('source_id', $expense->id)->exists())->toBeTrue();

    $this->post('/admin/kas-kecil/pengeluaran', [
        'petty_cash_account_id' => $account->id,
        'expense_date' => '2026-07-13',
        'category' => 'pekerjaan_proyek',
        'perumahan_id' => $project->id,
        'amount' => 80000,
        'description' => 'Perbaikan gerbang kawasan',
        'proof' => UploadedFile::fake()->create('nota-gerbang.pdf', 90, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $projectExpense = PettyCashExpense::query()->latest('id')->firstOrFail();
    expect($projectExpense->cost_type)->toBe('project_hpp')
        ->and($projectExpense->detail_rumah_id)->toBeNull()
        ->and((float) $account->fresh()->balance)->toBe(800000.0)
        ->and(HppRealisasi::query()->where('source_type', PettyCashExpense::class)->where('source_id', $projectExpense->id)->where('perumahan_id', $project->id)->exists())->toBeTrue();
});

test('office expense remains operational and does not create hpp realization', function () {
    Storage::fake('public');
    $finance = pettyCashUser('keuangan', '081230000003');
    $account = PettyCashAccount::query()->create([
        'code' => 'KK-OPS', 'name' => 'Kas Operasional', 'target_amount' => 500000,
        'balance' => 500000, 'minimum_balance' => 50000, 'created_by' => $finance->id,
    ]);

    $this->actingAs($finance)->post('/admin/kas-kecil/pengeluaran', [
        'petty_cash_account_id' => $account->id,
        'expense_date' => '2026-07-13',
        'category' => 'atk',
        'amount' => 125000,
        'description' => 'Pembelian kertas dan tinta printer',
        'proof' => UploadedFile::fake()->create('nota-atk.pdf', 80, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $expense = PettyCashExpense::query()->firstOrFail();
    expect($expense->cost_type)->toBe('operational')
        ->and((float) $account->fresh()->balance)->toBe(375000.0)
        ->and(HppRealisasi::query()->count())->toBe(0)
        ->and(Journal::query()->where('source_type', PettyCashExpense::class)->where('source_id', $expense->id)->exists())->toBeTrue();
});
