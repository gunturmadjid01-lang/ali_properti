<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function receivableStatisticsUser(): User
{
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create(['phone' => '081299990001']);
    $user->assignRole('super_admin');

    return $user;
}

test('statistik bulanan tetap menampilkan dua belas bulan bernilai nol ketika data kosong', function () {
    $this->actingAs(receivableStatisticsUser())
        ->get('/admin/keuangan/piutang?period=monthly&year=2026')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Receivables/Index')
            ->where('statistics.period', 'monthly')
            ->has('statistics.buckets', 12)
            ->where('statistics.buckets.0.label', 'Januari 2026')
            ->where('statistics.buckets.0.bill', 0)
            ->where('statistics.buckets.0.paid', 0)
            ->where('statistics.buckets.0.remaining', 0)
            ->where('statistics.buckets.11.label', 'Desember 2026'));
});

test('statistik harian dan tahunan membentuk seluruh bucket walaupun data kosong', function () {
    $user = receivableStatisticsUser();

    $this->actingAs($user)
        ->get('/admin/keuangan/piutang?period=daily&year=2028&month=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('statistics.buckets', 29)
            ->where('statistics.buckets.28.label', 'Selasa, 29 Februari 2028')
            ->where('statistics.buckets.28.bill', 0));

    $this->get('/admin/keuangan/piutang?period=yearly&year=2026')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('statistics.buckets', 5)
            ->where('statistics.buckets.0.label', '2022')
            ->where('statistics.buckets.4.label', '2026')
            ->where('statistics.buckets.4.remaining', 0));
});
