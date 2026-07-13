<?php

use App\Models\CabangPerusahaan;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\TipePostSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([ChartOfAccountSeeder::class, TipePostSeeder::class]);
});

function financeTransactionContext(): array
{
    $permissions = collect(['keuangan.view', 'keuangan.create'])
        ->map(fn (string $name) => Permission::findOrCreate($name, 'web'));
    $role = Role::findOrCreate('keuangan', 'web');
    $role->syncPermissions($permissions);
    $user = User::factory()->create(['phone' => '081239990001']);
    $user->assignRole($role);
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-SPLIT', 'nama_cabang' => 'Cabang Split', 'address' => 'Alamat',
        'phone' => '0411000002', 'emaiil' => 'split@example.test', 'manager_name' => 'Manager', 'status' => 'aktif',
    ]);
    $property = Perumahan::query()->create([
        'cabang_id' => $branch->id, 'kode_proyek' => 'PRJ-SPLIT', 'nama_perusahaan' => 'Perumahan Split',
        'alamat' => 'Alamat proyek', 'luas_lahan' => '1000', 'jumlah_unit' => 1,
        'tanggal_mulai' => '2026-01-01', 'status' => 'aktif',
    ]);
    $user->perumahans()->attach($property->id);
    $bank = MasterBank::query()->create([
        'perumahan_id' => $property->id, 'kode_bank' => 'BANK-SPLIT', 'nama_bank' => 'Bank Operasional',
        'nomor_rekening' => '100200300', 'nama_rekening' => 'PT Test', 'status' => 'aktif',
    ]);

    return [$user, $property, $bank];
}

test('income and expense pages only expose their own transaction types', function () {
    [$user] = financeTransactionContext();

    $this->actingAs($user)->get('/admin/keuangan/pemasukan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Finance/Index')
            ->where('section', 'pemasukan')
            ->where('options.postTypes', fn ($rows) => $rows->isNotEmpty() && $rows->every(fn ($row) => $row['jenis'] === 'pemasukan')));

    $this->get('/admin/keuangan/pengeluaran')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('section', 'pengeluaran')
            ->where('options.postTypes', fn ($rows) => $rows->isNotEmpty() && $rows->every(fn ($row) => $row['jenis'] === 'pengeluaran')));
});

test('separate endpoints reject a transaction type from the opposite flow', function () {
    [$user, $property, $bank] = financeTransactionContext();
    $incomePost = TipePost::query()->where('jenis', 'pemasukan')->firstOrFail();
    $expensePost = TipePost::query()->where('jenis', 'pengeluaran')->firstOrFail();
    $payload = [
        'perumahan_id' => $property->id,
        'master_bank_id' => $bank->id,
        'tanggal' => '2026-07-13',
        'nominal' => 1000000,
        'nomor_referensi' => 'TRX-SPLIT',
        'keterangan' => 'Transaksi pemisahan halaman',
    ];

    $this->actingAs($user)->post('/admin/keuangan/pemasukan', [
        ...$payload,
        'tipe_post_id' => $expensePost->id,
    ])->assertNotFound();
    expect(TransaksiKeuangan::query()->count())->toBe(0);

    $this->post('/admin/keuangan/pemasukan', [
        ...$payload,
        'tipe_post_id' => $incomePost->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(TransaksiKeuangan::query()->count())->toBe(1)
        ->and(TransaksiKeuangan::query()->first()->tipePost->jenis)->toBe('pemasukan');
});

test('legacy combined page redirects to income page', function () {
    [$user] = financeTransactionContext();

    $this->actingAs($user)
        ->get('/admin/keuangan/transaksi-kas-bank')
        ->assertRedirect('/admin/keuangan/pemasukan');
});
