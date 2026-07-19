<?php

use App\Models\CashInstallmentScheme;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->user = User::factory()->create(['phone' => '081200001122']);
    $this->user->givePermissionTo(collect(['sales.transactions.view', 'cash-installment.schemes.view', 'cash-installment.schemes.create', 'cash-installment.scheme-steps.view', 'cash-installment.contracts.view', 'developer-kpr.products.view', 'developer-kpr.applications.view'])->map(fn ($p) => Permission::findOrCreate($p, 'web')));
    $this->actingAs($this->user);
});
test('menu utama penjualan terintegrasi dapat dibuka dengan permission per halaman', function () {
    foreach (['transactions', 'schemes', 'scheme-steps', 'contracts', 'developer-products', 'developer-applications'] as $section) {
        $this->get(route('admin.integrated-sales.index', $section))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/OperationsModule/Index')->where('section', $section));
    }
});

test('statistik dan chart transaksi mengikuti data serta filter aktif', function () {
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-CHART', 'nama_cabang' => 'Cabang Chart', 'address' => '-', 'phone' => '-', 'emaiil' => 'chart@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::withoutEvents(fn () => Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Chart', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 2, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']));
    $customer = Costumer::create(['kode_costumer' => 'CST-CHART', 'perumahan_id' => $housing->id, 'nama' => 'Customer Chart', 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'NIK-CHART', 'status_perkawinan' => 'menikah', 'alamat' => '-']);

    foreach ([['01', 'cash', 200000000], ['02', 'kpr_bank', 300000000]] as $index => [$number, $method, $value]) {
        $unit = DetailRumah::withoutEvents(fn () => DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => $number, 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif']));
        $spr = Spr::create(['kode_spr' => 'SPR-CHART-'.$number, 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-07-01', 'metode_pembayaran' => $method, 'harga_jual' => $value, 'status' => Spr::STATUS_DISETUJUI]);
        SalesTransaction::create(['spr_id' => $spr->id, 'transaction_no' => 'TRX-CHART-'.$number, 'costumer_id' => $customer->id, 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'payment_method' => $method, 'sale_price_snapshot' => $value, 'party_snapshot' => [], 'payment_snapshot' => [], 'status' => 'active', 'approved_at' => "2026-0".(7 + $index).'-01 10:00:00']);
    }

    $this->get(route('admin.integrated-sales.index', ['section' => 'transactions', 'payment_method' => 'kpr_bank']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('analytics.summary.total', 1)
            ->where('analytics.summary.active', 1)
            ->where('analytics.summary.sales_value', 300000000)
            ->where('analytics.summary.average_value', 300000000)
            ->has('analytics.trend', 1)
            ->has('analytics.methods', 1)
            ->has('analytics.statuses', 1)
            ->has('analytics.housing', 1)
            ->where('analytics.methods.0.key', 'kpr_bank'));
});
test('halaman master skema menggunakan wizard khusus bukan form crud mentah', function () {
    $this->get(route('admin.integrated-sales.create', 'schemes'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/IntegratedSales/MasterWizard')->where('section', 'schemes')->has('options.housing'));
});
test('detail skema membuka halaman khusus dengan tab proses bukan tabel generik', function () {
    $scheme = CashInstallmentScheme::create(['code' => 'CB-DETAIL', 'name' => 'Skema Detail', 'minimum_booking_fee' => 1000000, 'minimum_dp' => 10000000, 'installment_count' => 12, 'maximum_tenor_months' => 12, 'interval_type' => 'monthly', 'grace_period_days' => 7, 'penalty_method' => 'fixed', 'penalty_value' => 10000, 'effective_from' => '2026-01-01', 'status' => 'aktif']);
    $this->get(route('admin.integrated-sales.show', ['schemes', $scheme->id]))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/IntegratedSales/Show')->where('kind', 'cash-scheme')->where('record.heading', 'CB-DETAIL — Skema Detail')->has('tabs', 7));
});
test('detail penjualan terintegrasi menyediakan preview dan cetak ERP', function () {
    $scheme = CashInstallmentScheme::create(['code' => 'CB-PRINT', 'name' => 'Skema Cetak', 'minimum_booking_fee' => 1000000, 'minimum_dp' => 10000000, 'installment_count' => 12, 'maximum_tenor_months' => 12, 'interval_type' => 'monthly', 'grace_period_days' => 7, 'penalty_method' => 'fixed', 'penalty_value' => 10000, 'effective_from' => '2026-01-01', 'status' => 'aktif']);
    $this->get(route('admin.integrated-sales.preview', ['schemes', $scheme->id]))->assertOk()->assertSee('Ringkasan Utama')->assertSee('CB-PRINT');
    $this->get(route('admin.integrated-sales.print', ['schemes', $scheme->id]))->assertOk()->assertSee('window.print()', false);
});
test('permission satu halaman tidak membuka halaman lainnya', function () {
    $this->user->syncPermissions([Permission::findOrCreate('cash-installment.schemes.view', 'web')]);
    $this->get(route('admin.integrated-sales.index', 'schemes'))->assertOk();
    $this->get(route('admin.integrated-sales.index', 'developer-products'))->assertForbidden();
});

test('halaman proses otomatis tidak menampilkan tombol tambah edit hapus dan route create tidak error', function () {
    $this->user->givePermissionTo(Permission::findOrCreate('developer-kpr.applications.create', 'web'));
    $this->get(route('admin.integrated-sales.index', 'developer-applications'))->assertOk()->assertInertia(fn ($page) => $page->where('permissions.create', false)->where('permissions.update', false)->where('permissions.delete', false));
    $this->get(route('admin.integrated-sales.create', 'developer-applications'))->assertRedirect(route('admin.integrated-sales.index', 'developer-applications'));
});

test('seluruh halaman cash bertahap dan kpr developer pada sidebar dapat dibuka', function () {
    $sections = [
        'schemes', 'scheme-detail', 'scheme-housing', 'scheme-steps', 'scheme-fees', 'scheme-requirements', 'scheme-documents', 'scheme-versions', 'scheme-history', 'scheme-reports', 'contracts', 'contract-detail', 'approvals', 'schedules', 'billings', 'arrears', 'payment-history', 'settlements', 'restructuring', 'cancellations', 'reports',
        'developer-products', 'developer-product-detail', 'developer-product-housing', 'developer-financing-terms', 'developer-margins', 'developer-fees', 'developer-requirements', 'developer-documents', 'developer-risk-approval', 'developer-penalties', 'developer-early-settlement', 'developer-product-versions', 'developer-product-history', 'developer-product-reports', 'developer-applications', 'developer-application-detail', 'developer-affordability-analysis', 'developer-document-validation', 'developer-internal-approval', 'developer-contracts', 'developer-schedules', 'developer-receivables', 'developer-arrears', 'developer-payments', 'developer-restructuring', 'developer-cancellations', 'developer-reports',
    ];
    $this->user->givePermissionTo(Permission::query()->where(fn ($q) => $q->where('name', 'like', 'cash-installment.%.view')->orWhere('name', 'like', 'developer-kpr.%.view'))->get());
    foreach ($sections as $section) {
        $this->get(route('admin.integrated-sales.index', $section))->assertOk();
    }
});

test('seluruh halaman proses kpr bank pada sidebar dapat dibuka', function () {
    $sections = ['bank-applications', 'bank-application-detail', 'bank-document-validation', 'bank-slik', 'bank-appraisal', 'bank-decision', 'bank-sp3k', 'bank-contract-preparation', 'bank-contract-schedule', 'bank-contract-execution', 'bank-disbursement', 'bank-change', 'bank-rejections', 'bank-reports'];
    $this->user->givePermissionTo(Permission::query()->where('name', 'like', 'bank-kpr.%.view')->get());
    foreach ($sections as $section) {
        $this->get(route('admin.integrated-sales.index',$section))->assertOk();
    }
});
