<?php

use App\Models\ApprovalRequest;
use App\Models\BankBranch;
use App\Models\BankCreditProduct;
use App\Models\BankHousingPartnership;
use App\Models\BankKredit;
use App\Models\CabangPerusahaan;
use App\Models\DetailRumah;
use App\Models\DocumentRequirementSet;
use App\Models\DokumenCostumer;
use App\Models\Perumahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->branch = CabangPerusahaan::create(['kode_cabang' => 'OPT', 'nama_cabang' => 'Cabang Opsi', 'address' => '-', 'phone' => '-', 'emaiil' => 'opt@test.local', 'manager_name' => 'Manager', 'status' => 'aktif', 'record_status' => 'locked']);
    $this->housingA = Perumahan::create(['cabang_id' => $this->branch->id, 'nama_perusahaan' => 'Housing A', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 2, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif', 'record_status' => 'locked']);
    $this->housingB = Perumahan::create(['cabang_id' => $this->branch->id, 'nama_perusahaan' => 'Housing B', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 2, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif', 'record_status' => 'locked']);
    $this->unitA = DetailRumah::create(['perumahan_id' => $this->housingA->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif', 'status_penjualan' => 'tersedia', 'record_status' => 'locked']);
    $this->unitB = DetailRumah::create(['perumahan_id' => $this->housingB->id, 'kode_nlok' => 'B', 'nomor_rumah' => '01', 'tipe_rumah' => '45', 'luas_tanah' => 90, 'status' => 'aktif', 'status_penjualan' => 'tersedia', 'record_status' => 'locked']);
    $this->bankA = BankKredit::create(['kode_bank' => 'BA', 'nama_bank' => 'Bank Aktif', 'jenis_bank' => 'konvensional', 'status' => 'aktif', 'record_status' => 'locked']);
    $this->bankB = BankKredit::create(['kode_bank' => 'BB', 'nama_bank' => 'Bank Tanpa PKS', 'jenis_bank' => 'konvensional', 'status' => 'aktif', 'record_status' => 'locked']);
    $this->bankBranch = BankBranch::create(['bank_kredit_id' => $this->bankA->id, 'branch_code' => 'BA-01', 'branch_name' => 'Cabang Bank A', 'status' => 'aktif', 'record_status' => 'locked']);
    BankHousingPartnership::create(['bank_kredit_id' => $this->bankA->id, 'bank_branch_id' => $this->bankBranch->id, 'perumahan_id' => $this->housingA->id, 'agreement_number' => 'PKS-A', 'agreement_name' => 'PKS Housing A', 'effective_from' => today()->subDay(), 'effective_until' => today()->addYear(), 'status' => 'aktif', 'record_status' => 'locked']);
    $this->product = BankCreditProduct::create(['bank_kredit_id' => $this->bankA->id, 'bank_branch_id' => $this->bankBranch->id, 'product_code' => 'PROD-A', 'product_name' => 'Produk Housing A', 'product_type' => 'KPR', 'subsidy_type' => 'non_subsidi', 'scheme_type' => 'konvensional', 'minimum_ceiling' => 1, 'maximum_ceiling' => 500000000, 'minimum_down_payment' => 10000000, 'maximum_tenor_months' => 240, 'indicative_interest_margin' => 5, 'provision_fee' => 0, 'administration_fee' => 0, 'appraisal_fee' => 0, 'insurance_fee' => 0, 'notary_fee' => 0, 'disbursement_method' => 'sekaligus', 'effective_from' => today()->subDay(), 'effective_until' => today()->addYear(), 'status' => 'aktif', 'record_status' => 'locked']);
    $document = DokumenCostumer::create(['kode_dokumen' => 'KTP', 'nama_dokumen' => 'KTP Customer', 'kategori_pengajuan' => 'kpr_bank', 'wajib' => true, 'status' => 'aktif', 'record_status' => 'locked', 'locked_at' => now()]);
    $set = DocumentRequirementSet::create(['code' => 'TEST-PROD-A', 'name' => 'Dokumen Produk A', 'application_types' => ['kpr_bank'], 'status' => 'aktif', 'record_status' => 'locked', 'locked_at' => now()]);
    $set->banks()->attach($this->bankA->id);
    $set->products()->attach($this->product->id);
    $set->housings()->attach($this->housingA->id);
    $set->items()->create(['dokumen_costumer_id' => $document->id, 'party_scope' => 'customer', 'is_required' => true]);
    ApprovalRequest::create(['module_key' => 'document-requirement-set', 'module_label' => 'Paket Persyaratan Dokumen Pelanggan', 'action' => 'lock', 'model_type' => DocumentRequirementSet::class, 'model_id' => $set->id, 'status' => 'approved', 'current_step' => 1, 'total_steps' => 1, 'reviewed_at' => now()]);
    $role = Role::findOrCreate('marketing', 'web');
    $role->givePermissionTo(Permission::findOrCreate('booking.view', 'web'));
    $this->user = User::factory()->create(['phone' => '081200000001']);
    $this->user->assignRole($role);
    $this->user->perumahans()->attach($this->housingA->id);
    $this->actingAs($this->user)->withSession(['active_perumahan_id' => $this->housingA->id]);
});

test('opsi unit mengikuti scope perumahan dan tujuan transaksi', function () {
    $this->getJson(route('admin.options.units', ['housing_project_id' => $this->housingA->id, 'purpose' => 'reservation']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', (string) $this->unitA->id);
    $this->getJson(route('admin.options.units', ['housing_project_id' => $this->housingB->id, 'purpose' => 'reservation']))->assertForbidden();
});

test('draft tetap ada di sumber tetapi tidak bocor ke opsi modul lain', function () {
    $draft = DetailRumah::create(['perumahan_id' => $this->housingA->id, 'kode_nlok' => 'D', 'nomor_rumah' => '99', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif', 'status_penjualan' => 'tersedia', 'record_status' => 'draft']);
    expect(DetailRumah::query()->find($draft->id))->not->toBeNull();
    $this->getJson(route('admin.options.units', ['housing_project_id' => $this->housingA->id, 'purpose' => 'spr']))->assertOk()->assertJsonMissing(['value' => (string) $draft->id]);
});

test('bank cabang produk dan dokumen hanya berasal dari pks aktif perumahan', function () {
    $this->getJson(route('admin.options.credit-banks', ['housing_project_id' => $this->housingA->id]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', (string) $this->bankA->id);
    $this->getJson(route('admin.options.bank-branches', ['credit_bank_id' => $this->bankA->id, 'housing_project_id' => $this->housingA->id]))->assertOk()->assertJsonPath('data.0.value', (string) $this->bankBranch->id);
    $this->getJson(route('admin.options.credit-products', ['credit_bank_id' => $this->bankA->id, 'credit_bank_branch_id' => $this->bankBranch->id, 'housing_project_id' => $this->housingA->id]))->assertOk()->assertJsonPath('data.0.value', (string) $this->product->id);
    $this->getJson(route('admin.options.credit-products', ['credit_bank_id' => $this->bankB->id, 'housing_project_id' => $this->housingA->id]))->assertStatus(422);
    $this->getJson(route('admin.options.document-requirements', ['credit_product_id' => $this->product->id, 'housing_project_id' => $this->housingA->id, 'process' => 'kpr']))->assertOk()->assertJsonPath('data.0.code', 'KTP');
});

test('endpoint opsi menolak user tanpa permission', function () {
    $user = User::factory()->create(['phone' => '081200000002']);
    $this->actingAs($user)->getJson(route('admin.options.credit-banks', ['housing_project_id' => $this->housingA->id]))->assertForbidden();
});
