<?php

use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\MasterBank;
use App\Models\Perumahan;
use App\Models\TipePost;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function financeContext(): array
{
    $user = User::factory()->create(['phone' => '081234567899']);
    $user->assignRole(Role::findOrCreate('keuangan', 'web'));
    $branch = CabangPerusahaan::query()->create([
        'kode_cabang' => 'CB-FIN',
        'nama_cabang' => 'Cabang Finance',
        'address' => 'Alamat',
        'phone' => '0800000001',
        'emaiil' => 'finance@example.test',
        'manager_name' => 'Manager',
        'status' => 'aktif',
    ]);
    $property = Perumahan::query()->create([
        'cabang_id' => $branch->id,
        'kode_proyek' => 'PRJ-FIN',
        'nama_perusahaan' => 'Perumahan Finance',
        'alamat' => 'Alamat',
        'luas_lahan' => '1000',
        'jumlah_unit' => 1,
        'tanggal_mulai' => now()->toDateString(),
        'status' => 'aktif',
    ]);
    $user->perumahans()->attach($property->id);

    return [$user, $property];
}

test('jurnal manual wajib balance', function () {
    [$user, $property] = financeContext();
    $cash = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->firstOrFail();
    $revenue = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::PENDAPATAN_UNIT)->firstOrFail();

    $this->actingAs($user)
        ->post('/admin/keuangan/jurnal-umum', [
            'tanggal' => now()->toDateString(),
            'perumahan_id' => $property->id,
            'keterangan' => 'Jurnal tidak balance',
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 1000000, 'kredit' => 0],
                ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'kredit' => 900000],
            ],
        ])
        ->assertStatus(422);

    expect(Journal::query()->count())->toBe(0);
});

test('jurnal balance tampil pada neraca saldo dan laba rugi', function () {
    [$user, $property] = financeContext();
    $cash = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::KAS_BANK)->firstOrFail();
    $revenue = ChartOfAccount::query()->where('kode_akun', ChartOfAccount::PENDAPATAN_UNIT)->firstOrFail();

    $this->actingAs($user)
        ->post('/admin/keuangan/jurnal-umum', [
            'tanggal' => now()->toDateString(),
            'perumahan_id' => $property->id,
            'keterangan' => 'Penjualan unit test',
            'lines' => [
                ['chart_of_account_id' => $cash->id, 'debit' => 1000000, 'kredit' => 0],
                ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'kredit' => 1000000],
            ],
        ])
        ->assertRedirect();

    expect(Journal::query()->count())->toBe(1)
        ->and((float) Journal::query()->value('total_debit'))->toBe(1000000.0)
        ->and((float) Journal::query()->value('total_kredit'))->toBe(1000000.0);

    $this->actingAs($user)
        ->get('/admin/keuangan/neraca-saldo?perumahan_id='.$property->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Finance/Index')
            ->where('data.balanced', true)
            ->where('data.total_debit', 1000000)
            ->where('data.total_credit', 1000000));

    $this->actingAs($user)
        ->get('/admin/keuangan/laba-rugi?perumahan_id='.$property->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Finance/Index')
            ->where('data.revenue', 1000000)
            ->where('data.net_profit', 1000000));

    $this->actingAs($user)
        ->get('/admin/keuangan/neraca?perumahan_id='.$property->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Finance/Index')
            ->where('data.assets', 1000000)
            ->where('data.liabilities_equity', 1000000)
            ->where('data.balanced', true));

    $this->actingAs($user)
        ->get('/admin/keuangan/arus-kas?perumahan_id='.$property->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Finance/Index')
            ->where('data.cash_in', 1000000)
            ->where('data.ending_balance', 1000000));
});

test('input modal owner membuat transaksi kas dan jurnal otomatis', function () {
    [$user, $property] = financeContext();
    $bank = MasterBank::query()->create([
        'perumahan_id' => $property->id,
        'kode_bank' => 'CASH-FIN',
        'nama_bank' => 'Kas Proyek',
        'nomor_rekening' => 'KAS-001',
        'nama_rekening' => 'Kas Proyek',
        'status' => 'aktif',
    ]);
    $post = TipePost::query()->where('nama_post', 'Setoran Modal Owner')->firstOrFail();

    $this->actingAs($user)
        ->post('/admin/keuangan/transaksi-kas-bank', [
            'perumahan_id' => $property->id,
            'master_bank_id' => $bank->id,
            'tipe_post_id' => $post->id,
            'tanggal' => now()->toDateString(),
            'nominal' => 250000000,
            'nomor_referensi' => 'MODAL-001',
            'keterangan' => 'Setoran modal awal owner',
        ])
        ->assertRedirect();

    $transaction = TransaksiKeuangan::query()->firstOrFail();
    expect((float) $transaction->nominal)->toBe(250000000.0)
        ->and($transaction->journal_id)->not->toBeNull()
        ->and((float) $transaction->journal->total_debit)->toBe(250000000.0)
        ->and((float) $transaction->journal->total_kredit)->toBe(250000000.0);
});
