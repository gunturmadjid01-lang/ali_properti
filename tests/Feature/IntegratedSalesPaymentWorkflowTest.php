<?php

use App\Models\ApprovalRequest;
use App\Models\CabangPerusahaan;
use App\Models\CashInstallmentContract;
use App\Models\ChartOfAccount;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\DeveloperKprApplication;
use App\Models\DocumentRequirementSet;
use App\Models\DokumenCostumer;
use App\Models\KprSubmission;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\SalesProcessStep;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Services\CustomerDocumentRequirementService;
use App\Services\SalesPaymentWorkflowService;
use App\Services\SalesProcessService;
use App\Support\SalesProcessDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([[ChartOfAccount::PIUTANG_CUSTOMER, 'Piutang Customer', 'aset', 'debit'], [ChartOfAccount::UANG_MUKA_CUSTOMER, 'Uang Muka Customer', 'liabilitas', 'kredit']] as [$code, $name, $category, $normal]) {
        ChartOfAccount::updateOrCreate(['kode_akun' => $code], ['nama_akun' => $name, 'kategori' => $category, 'posisi_normal' => $normal, 'status' => 'aktif']);
    }
});

function approvedWorkflowSpr(string $method): Spr
{
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-'.$method, 'nama_cabang' => 'Cabang '.$method, 'address' => '-', 'phone' => '-', 'emaiil' => $method.'@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan '.$method, 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif']);
    $customer = Costumer::create(['kode_costumer' => 'CST-'.$method, 'perumahan_id' => $housing->id, 'nama' => 'Customer '.$method, 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'NIK-'.$method, 'status_perkawinan' => 'menikah', 'alamat' => '-']);

    return Spr::create(['kode_spr' => 'SPR-'.$method, 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-01-02', 'metode_pembayaran' => $method, 'harga_jual' => 300000000, 'booking_fee' => 5000000, 'uang_muka' => 25000000, 'jumlah_termin' => 12, 'tanggal_jatuh_tempo_angsuran' => '2026-02-01', 'kpr_tenor_bulan' => 24, 'status' => Spr::STATUS_DISETUJUI]);
}

test('workflow cash idempoten dan tidak menganggap booking fee sebagai pembayaran aktual', function () {
    $spr = approvedWorkflowSpr('cash');
    $service = app(SalesPaymentWorkflowService::class);
    $first = $service->processApprovedSpr($spr);
    $second = $service->processApprovedSpr($spr);
    expect($first->id)->toBe($second->id)->and(SalesTransaction::count())->toBe(1)
        ->and($spr->cashSale()->first()->total_dibayar)->toEqual(0.0)
        ->and(PaymentSchedule::where('sales_transaction_id', $first->id)->where('type', 'down_payment')->count())->toBe(1);
});

test('workflow membuat proses khusus sesuai metode pembayaran tanpa pilihan ulang context', function (string $method, string $model) {
    $spr = approvedWorkflowSpr($method);
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
    expect($model::where('sales_transaction_id', $transaction->id)->count())->toBe(1)
        ->and($transaction->costumer_id)->toBe($spr->costumer_id)
        ->and($transaction->detail_rumah_id)->toBe($spr->detail_rumah_id)
        ->and($transaction->party_snapshot['customer_name'])->toBe('Customer '.$method);
})->with([
    'cash bertahap' => ['cash_bertahap', CashInstallmentContract::class],
    'kpr developer' => ['kpr_developer', DeveloperKprApplication::class],
]);

test('workflow kpr bank tetap membuat pengajuan bank yang terkait ke spr', function () {
    $spr = approvedWorkflowSpr('kpr_bank');
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
    expect(KprSubmission::where('spr_id', $spr->id)->count())->toBe(1)->and($transaction->payment_method)->toBe('kpr_bank')
        ->and(SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'slik')->exists())->toBeTrue()
        ->and(SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'move_in')->exists())->toBeTrue();
});

test('approval spr membuat tagihan uang muka satu kali dan pokok cash bertahap tidak menghitung uang muka dua kali', function () {
    $spr = approvedWorkflowSpr('cash_bertahap');
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
    app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr->fresh());

    expect(PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'down_payment')->count())->toBe(1)
        ->and((float) PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'down_payment')->value('amount'))->toBe(25000000.0)
        ->and((float) $transaction->cashInstallmentContract->contract_value)->toBe(270000000.0);
});

test('setiap metode memiliki proses berurutan sampai customer menempati unit', function (string $method) {
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr(approvedWorkflowSpr($method));
    $steps = SalesProcessStep::where('sales_transaction_id', $transaction->id)->orderBy('sequence')->get();
    expect($steps->first()->status)->toBe('available')->and($steps->where('code', 'construction')->count())->toBe(1)
        ->and($steps->where('code', 'customer_handover')->count())->toBe(1)->and($steps->where('code', 'move_in')->count())->toBe(1)
        ->and($steps->last()->code)->toBe('completed');
})->with(['cash bertahap' => ['cash_bertahap'], 'kpr developer' => ['kpr_developer'], 'kpr bank' => ['kpr_bank']]);

test('approval final tahapan membuka proses berikutnya dan serah terima sampai huni bersifat idempoten', function () {
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr(approvedWorkflowSpr('cash_bertahap'));
    $handover = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'customer_handover')->firstOrFail();
    SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('sequence', '<', $handover->sequence)->update(['status' => 'completed']);
    app(SalesProcessService::class)->approve($handover);
    app(SalesProcessService::class)->approve($handover->fresh());
    expect($transaction->housingUnit->fresh()->status_penjualan)->toBe('terjual')
        ->and(UnitOwnership::where('detail_rumah_id', $transaction->detail_rumah_id)->count())->toBe(1);
    $moveIn = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'move_in')->firstOrFail();
    app(SalesProcessService::class)->approve($moveIn);
    app(SalesProcessService::class)->approve($moveIn->fresh());
    expect($transaction->housingUnit->fresh()->status_penjualan)->toBe('ditempati')
        ->and(UnitOwnership::where('detail_rumah_id', $transaction->detail_rumah_id)->count())->toBe(1);
});

test('tagihan cash bertahap baru dibuat setelah tahap penandatanganan kontrak disetujui', function () {
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr(approvedWorkflowSpr('cash_bertahap'));
    $review = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'contract_review')->firstOrFail();
    app(SalesProcessService::class)->approve($review);
    expect(PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'termin')->count())->toBe(0);

    $contract = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'contract_signing')->firstOrFail();
    app(SalesProcessService::class)->approve($contract);
    $count = PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'termin')->count();
    app(SalesProcessService::class)->approve($contract->fresh());

    expect($count)->toBeGreaterThan(0)
        ->and(PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'termin')->count())->toBe($count)
        ->and((float) PaymentSchedule::where('sales_transaction_id', $transaction->id)->sum('amount'))->toBe(295000000.0);
});

test('tagihan kpr developer dibuat saat persetujuan pembiayaan developer disetujui', function () {
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr(approvedWorkflowSpr('kpr_developer'));
    foreach (['affordability_analysis', 'document_validation'] as $code) {
        $step = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', $code)->firstOrFail();
        app(SalesProcessService::class)->approve($step);
    }
    expect(PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'angsuran')->count())->toBe(0);

    $approval = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'internal_approval')->firstOrFail();
    app(SalesProcessService::class)->approve($approval);
    $count = PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'angsuran')->count();
    app(SalesProcessService::class)->approve($approval->fresh());

    expect($count)->toBeGreaterThan(0)
        ->and(PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'angsuran')->count())->toBe($count);
});

test('tahap operasional tidak dapat difinalisasi sebelum field checklist dan dokumen wajib lengkap', function () {
    Storage::fake('public');
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create(['phone' => '081200001234']);
    $user->assignRole('super_admin');
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr(approvedWorkflowSpr('cash_bertahap'));
    $step = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'contract_review')->firstOrFail();
    $this->actingAs($user)->post("/admin/penjualan-terintegrasi/tahapan/{$step->id}/lock")->assertSessionHasErrors('completion');
    $definition = SalesProcessDefinitions::get($step->code);
    $metadata = [];
    foreach ($definition['fields'] as $field) {
        $metadata[$field['name']] = match ($field['type']) {
            'date' => '2026-01-10','datetime' => '2026-01-10T10:00','number','currency' => 1,'select' => array_key_first($field['options']),default => 'Data lengkap'
        };
    }
    $checklist = collect($definition['checklist'])->mapWithKeys(fn ($item) => [$item['key'] => true])->all();
    foreach (collect($definition['documents'])->where('required', true) as $document) {
        $this->post("/admin/penjualan-terintegrasi/tahapan/{$step->id}/dokumen", ['document_type' => $document['type'], 'document_number' => 'DOC-001', 'document_date' => '2026-01-10', 'file' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')])->assertRedirect();
    }
    $this->post("/admin/penjualan-terintegrasi/tahapan/{$step->id}", ['_method' => 'put', 'finalize' => true, 'actual_date' => '2026-01-10', 'notes' => 'Pemeriksaan kontrak lengkap.', 'metadata' => $metadata, 'checklist' => $checklist])
        ->assertRedirect()
        ->assertSessionHas('success', 'Tahap disetujui dan tahap berikutnya dibuka.');
    expect($step->fresh()->status)->toBe('completed')->and(SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'contract_signing')->value('status'))->toBe('available');
});

test('tahap operasional tidak dapat disimpan ketika data wajib belum lengkap', function () {
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create(['phone' => '081200001235']);
    $user->assignRole('super_admin');
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr(approvedWorkflowSpr('cash_bertahap'));
    $step = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'contract_review')->firstOrFail();

    $this->actingAs($user)->put("/admin/penjualan-terintegrasi/tahapan/{$step->id}", [
        'actual_date' => '',
        'notes' => '',
        'metadata' => ['installment_count' => 0],
        'checklist' => [],
    ])->assertSessionHasErrors([
        'completion',
        'actual_date',
        'notes',
        'metadata.contract_number',
        'checklist.price_match',
        'documents.contract_draft',
    ]);

    expect($step->fresh()->status)->toBe('available')
        ->and($step->fresh()->actual_date)->toBeNull()
        ->and($step->fresh()->notes)->toBeNull();
});

test('definisi proses kritis memiliki input dan dokumen bisnis khusus', function (string $code, string $field, string $document) {
    $definition = SalesProcessDefinitions::get($code);
    expect(collect($definition['fields'])->pluck('name'))->toContain($field)->and(collect($definition['documents'])->pluck('type'))->toContain($document)->and(collect($definition['checklist'])->where('required', true)->count())->toBeGreaterThan(0);
})->with([
    'SLIK' => ['slik', 'collectibility', 'slik_result'], 'Appraisal' => ['appraisal', 'market_value', 'appraisal_report'], 'SP3K' => ['sp3k', 'sp3k_number', 'sp3k'], 'Akad' => ['contract_signing', 'contract_number', 'signed_contract'], 'QC' => ['quality_inspection', 'critical_defects', 'qc_report'], 'BAST' => ['customer_handover', 'handover_number', 'bast'], 'Mulai Huni' => ['move_in', 'occupancy_date', 'occupancy_confirmation'],
]);

test('seluruh field tanggal dan jam proses menggunakan tipe datetime yang seragam', function () {
    foreach (['contract_signing', 'internal_handover', 'customer_handover'] as $code) {
        $dateTimeFields = collect(SalesProcessDefinitions::get($code)['fields'])->where('type', 'datetime');
        expect($dateTimeFields)->not->toBeEmpty();
        $dateTimeFields->each(fn (array $field) => expect($field['name'])->toEndWith('_datetime'));
    }
});

test('tahap pemeriksaan dokumen tidak mewajibkan upload ulang dokumen customer', function (string $code) {
    $documents = collect(SalesProcessDefinitions::get($code)['documents']);

    expect($documents->where('required', true))->toBeEmpty()
        ->and($documents->pluck('type')->all())->not->toContain('identity', 'family_card', 'income_document', 'bank_statement');
})->with(['pengumpulan dokumen' => ['document_collection'], 'validasi dokumen' => ['document_validation']]);

test('paket dokumen pembiayaan muncul di tahap terkait dan bukan di SPR', function () {
    $spr = approvedWorkflowSpr('kpr_bank');
    $spr->costumer->update(['employment_category' => 'pns', 'status_perkawinan' => 'menikah']);
    $ktp = DokumenCostumer::create(['kode_dokumen' => 'KTP', 'nama_dokumen' => 'KTP Customer', 'kategori_pengajuan' => 'kpr_bank', 'wajib' => true, 'status' => 'aktif', 'record_status' => 'locked']);
    $sk = DokumenCostumer::create(['kode_dokumen' => 'SK-PNS', 'nama_dokumen' => 'SK Pengangkatan PNS', 'kategori_pengajuan' => 'kpr_bank', 'wajib' => true, 'status' => 'aktif', 'record_status' => 'locked']);
    $nikah = DokumenCostumer::create(['kode_dokumen' => 'BUKU-NIKAH', 'nama_dokumen' => 'Buku Nikah', 'kategori_pengajuan' => 'kpr_bank', 'wajib' => true, 'status' => 'aktif', 'record_status' => 'locked']);
    $set = DocumentRequirementSet::create(['code' => 'KPR-PNS', 'name' => 'KPR PNS Menikah', 'application_types' => ['kpr_bank'], 'status' => 'aktif', 'record_status' => 'locked']);
    $set->items()->createMany([
        ['dokumen_costumer_id' => $ktp->id, 'employment_categories' => [], 'marital_statuses' => [], 'party_scope' => 'customer', 'is_required' => true, 'sort_order' => 1],
        ['dokumen_costumer_id' => $sk->id, 'employment_categories' => ['pns'], 'marital_statuses' => [], 'party_scope' => 'customer', 'is_required' => true, 'sort_order' => 2],
        ['dokumen_costumer_id' => $nikah->id, 'employment_categories' => [], 'marital_statuses' => ['menikah'], 'party_scope' => 'both', 'is_required' => true, 'sort_order' => 3],
    ]);
    ApprovalRequest::create(['module_key' => 'document-requirement-set', 'module_label' => 'Paket Persyaratan Dokumen Pelanggan', 'action' => 'lock', 'model_type' => DocumentRequirementSet::class, 'model_id' => $set->id, 'before_data' => [], 'after_data' => [], 'status' => 'approved', 'current_step' => 1, 'total_steps' => 1]);
    $transaction = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr->fresh());
    $stage = SalesProcessStep::where('sales_transaction_id', $transaction->id)->where('code', 'document_collection')->firstOrFail();
    expect(app(CustomerDocumentRequirementService::class)->forSpr($spr->fresh()))->toBeEmpty();
    $result = app(CustomerDocumentRequirementService::class)->forStage($stage);
    expect($result->pluck('code')->all())->toContain('KTP', 'SK-PNS', 'BUKU-NIKAH')
        ->and($result->firstWhere('code', 'BUKU-NIKAH')['party_scope'])->toBe('both');
});
