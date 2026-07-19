<?php

use App\Http\Requests\Admin\DokumenCostumer\StoreDokumenCostumerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('form dokumen pelanggan menyediakan kategori sesuai alur penjualan', function () {
    $user = User::factory()->create(['phone' => '081299990001']);

    $this->actingAs($user)
        ->get(route('admin.management.master-dokumen-customer.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Management/Components/SeparatedManagementFormPage')
            ->where('options.categoryOptions', [
                ['value' => 'spr', 'label' => 'SPR'],
                ['value' => 'cash_bertahap', 'label' => 'Cash Bertahap'],
                ['value' => 'kpr_bank', 'label' => 'KPR Bank'],
                ['value' => 'kpr_developer', 'label' => 'KPR Developer'],
            ]));
});

test('validasi menerima kategori baru dan menolak kategori lama', function () {
    $request = new StoreDokumenCostumerRequest;
    $base = ['nama_dokumen' => 'Slip Gaji', 'wajib' => true, 'status' => 'aktif'];

    foreach (['spr', 'cash_bertahap', 'kpr_bank', 'kpr_developer'] as $category) {
        expect(Validator::make([...$base, 'kategori_pengajuan' => $category], $request->rules())->passes())->toBeTrue();
    }

    foreach (['umum', 'kpr', 'cash', 'bertahap'] as $category) {
        expect(Validator::make([...$base, 'kategori_pengajuan' => $category], $request->rules())->fails())->toBeTrue();
    }
});
