<?php

use App\Models\BankCreditProduct;
use App\Models\BankKredit;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\KprSubmission;
use App\Models\Perumahan;
use App\Models\Spr;
use App\Models\User;
use App\Services\BankCreditProductService;
use App\Services\KprProductSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('perubahan produk membuat versi baru tanpa mengubah snapshot kpr lama', function () {
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-BANK', 'nama_cabang' => 'Cabang Test', 'address' => '-', 'phone' => '-', 'emaiil' => 'bank@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Bank Test', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '1', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif']);
    $customer = Costumer::create(['kode_costumer' => 'CST-BANK', 'perumahan_id' => $housing->id, 'nama' => 'Customer Bank', 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'BANK-1', 'status_perkawinan' => 'menikah', 'alamat' => '-']);
    $bank = BankKredit::create(['kode_bank' => 'BNK-T', 'nama_bank' => 'Bank Test', 'jenis_bank' => 'konvensional', 'status' => 'aktif']);
    $product = BankCreditProduct::create([
        'bank_kredit_id' => $bank->id, 'product_code' => 'KPR-T', 'product_name' => 'KPR Test', 'product_type' => 'KPR', 'subsidy_type' => 'non_subsidi', 'scheme_type' => 'konvensional',
        'minimum_ceiling' => 100000000, 'maximum_ceiling' => 500000000, 'minimum_down_payment' => 10000000, 'maximum_tenor_months' => 240, 'indicative_interest_margin' => 5,
        'provision_fee' => 1000000, 'administration_fee' => 500000, 'appraisal_fee' => 250000, 'insurance_fee' => 750000, 'notary_fee' => 2000000,
        'disbursement_method' => 'sekaligus', 'effective_from' => '2026-01-01', 'status' => 'aktif',
    ]);
    $service = app(BankCreditProductService::class);
    $service->createVersion($product);
    $spr = Spr::create(['kode_spr' => 'SPR-BANK', 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-01-02', 'metode_pembayaran' => 'kpr_bank', 'bank_kredit_id' => $bank->id, 'harga_jual' => 300000000, 'status' => Spr::STATUS_DISETUJUI]);
    $submission = KprSubmission::create(['kode_kpr' => 'KPR-BANK', 'spr_id' => $spr->id, 'bank_kredit_id' => $bank->id, 'tanggal_pengajuan' => '2026-01-03', 'nilai_pengajuan' => 250000000, 'status' => 'pengumpulan_dokumen']);
    app(KprProductSnapshotService::class)->apply($submission, $product);

    $service->updateWithVersion($product, ['indicative_interest_margin' => 7]);
    $submission->refresh();

    expect($product->fresh()->current_version)->toBe(2)
        ->and($product->versions()->count())->toBe(2)
        ->and((float) $submission->bank_product_snapshot['indicative_interest_margin'])->toBe(5.0)
        ->and((int) $submission->bank_product_snapshot['version_number'])->toBe(1)
        ->and((float) $product->fresh()->indicative_interest_margin)->toBe(7.0);
});

test('owner dengan permission dapat membuka enam halaman bank yang berbeda', function () {
    $permissions = [
        'bank-credit-master.view', 'bank-branch.view', 'bank-credit-product.view',
        'bank-housing-partnership.view', 'bank-document-requirement.view', 'bank-partnership-history.view',
    ];
    $role = Role::findOrCreate('owner', 'web');
    $role->givePermissionTo(collect($permissions)->map(fn ($name) => Permission::findOrCreate($name, 'web')));
    $user = User::factory()->create(['phone' => '081234567890']);
    $user->assignRole($role);

    $pages = [
        '/admin/bank-kredit' => 'Admin/Bank/Master/Index',
        '/admin/cabang-bank' => 'Admin/Bank/Branch/Index',
        '/admin/produk-kredit-bank' => 'Admin/Bank/Product/Index',
        '/admin/kerja-sama-bank' => 'Admin/Bank/Partnership/Index',
        '/admin/paket-persyaratan-dokumen' => 'Admin/DocumentRequirements/Index',
        '/admin/riwayat-kerja-sama-bank' => 'Admin/Bank/PartnershipHistory/Index',
    ];

    foreach ($pages as $url => $component) {
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component($component));
    }
});
