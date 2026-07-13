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
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function pettyCashUser(string $role, string $phone): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create(['phone' => $phone]);
    $user->assignRole($role);

    return $user;
}

test('authorized roles can open separated petty cash modules', function (string $role) {
    $user = pettyCashUser($role, '08123'.random_int(100000, 999999));

    $this->actingAs($user)
        ->get('/admin/kas-kecil/saldo')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PettyCash/Index')
            ->where('section', 'saldo')
            ->has('accounts'));
})->with(['owner', 'manager', 'keuangan']);

test('formation stays pending and approval proof increases balance once', function () {
    Storage::fake('public');
    $owner = pettyCashUser('owner', '081230000001');

    $this->actingAs($owner)->post('/admin/kas-kecil/rekening', [
        'name' => 'Kas Kecil Utama',
        'target_amount' => 10000000,
        'minimum_balance' => 1000000,
        'request_date' => '2026-07-13',
        'request_notes' => 'Pembentukan awal',
        'request_proof' => UploadedFile::fake()->create('penarikan.pdf', 100, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $account = PettyCashAccount::query()->firstOrFail();
    $funding = PettyCashFunding::query()->firstOrFail();
    expect((float) $account->balance)->toBe(0.0)
        ->and($funding->status)->toBe('pending');

    $this->post("/admin/kas-kecil/pengisian/{$funding->id}/setujui", [])
        ->assertSessionHasErrors('approval_proof');

    $this->post("/admin/kas-kecil/pengisian/{$funding->id}/setujui", [
        'approval_proof' => UploadedFile::fake()->create('transfer.pdf', 120, 'application/pdf'),
        'approval_notes' => 'Transfer berhasil',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect((float) $account->fresh()->balance)->toBe(10000000.0)
        ->and($funding->fresh()->status)->toBe('approved')
        ->and($account->ledgers()->count())->toBe(1)
        ->and(Journal::query()->where('source_type', PettyCashFunding::class)->where('source_id', $funding->id)->exists())->toBeTrue();
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
