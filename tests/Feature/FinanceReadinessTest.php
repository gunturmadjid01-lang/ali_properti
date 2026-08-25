<?php

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function financeReadinessUser(): User
{
    $role = Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create(['phone' => '081238880001']);
    $user->assignRole($role);
    return $user;
}

function financeReadinessJournal(string $status, float $amount): Journal
{
    $cash = ChartOfAccount::query()->firstOrCreate(['kode_akun' => ChartOfAccount::KAS_BANK], ['nama_akun' => 'Kas dan Bank', 'kategori' => 'aset', 'posisi_normal' => 'debit', 'status' => 'aktif']);
    $revenue = ChartOfAccount::query()->firstOrCreate(['kode_akun' => ChartOfAccount::PENDAPATAN_UNIT], ['nama_akun' => 'Pendapatan Unit', 'kategori' => 'pendapatan', 'posisi_normal' => 'kredit', 'status' => 'aktif']);
    $journal = Journal::query()->create(['nomor_jurnal' => strtoupper($status).'-'.str()->uuid(), 'tanggal' => now(), 'type' => 'manual', 'record_status' => $status, 'total_debit' => $amount, 'total_kredit' => $amount, 'keterangan' => 'Uji kesiapan laporan']);
    $journal->details()->createMany([['chart_of_account_id' => $cash->id, 'debit' => $amount, 'kredit' => 0], ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'kredit' => $amount]]);
    return $journal;
}

test('all financial statements exclude draft and locked journals', function () {
    $user = financeReadinessUser();
    financeReadinessJournal('posted', 1000000);
    financeReadinessJournal('draft', 9000000);
    financeReadinessJournal('locked', 8000000);

    $this->actingAs($user)->get('/admin/keuangan/neraca-saldo')->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.total_debit', 1000000)->where('data.total_credit', 1000000)->where('data.balanced', true));
    $this->get('/admin/keuangan/laba-rugi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.revenue', 1000000)->where('data.net_profit', 1000000));
    $this->get('/admin/keuangan/neraca')->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.assets', 1000000)->where('data.liabilities_equity', 1000000)->where('data.balanced', true));
    $this->get('/admin/keuangan/arus-kas')->assertOk()->assertInertia(fn (Assert $page) => $page->where('data.cash_in', 1000000)->where('data.ending_balance', 1000000)->where('data.groups.0.type', 'operating'));
});

test('journal listing is paginated without silently truncating records', function () {
    $user = financeReadinessUser();
    foreach (range(1, 55) as $index) financeReadinessJournal('posted', $index * 1000);
    $this->actingAs($user)->get('/admin/keuangan/jurnal-umum')->assertOk()->assertInertia(fn (Assert $page) => $page->has('data.rows', 50)->where('data.pagination.total', 55)->where('data.pagination.from', 1)->where('data.pagination.to', 50));
});
