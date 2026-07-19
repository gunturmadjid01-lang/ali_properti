<?php

use App\Models\BankKprDisbursement;
use App\Models\BankKprFinancing;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\KprSubmission;
use App\Models\PaymentSchedule;
use App\Services\BankKprFinancialService;
use App\Services\SalesPaymentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
function bankKprAccounts(): void
{
    foreach ([[ChartOfAccount::KAS_BANK, 'Kas Bank', 'aset', 'debit'], [ChartOfAccount::PIUTANG_CUSTOMER, 'Piutang Customer', 'aset', 'debit'], [ChartOfAccount::UANG_MUKA_CUSTOMER, 'Uang Muka Customer', 'liabilitas', 'kredit']] as [$c,$n,$k,$p]) {
        ChartOfAccount::updateOrCreate(['kode_akun' => $c], ['nama_akun' => $n, 'kategori' => $k, 'posisi_normal' => $p, 'status' => 'aktif']);
    }
}
test('approval struktur kpr bank membuat tagihan internal dan pencairan diposting idempoten', function () {
    bankKprAccounts();
    $spr = approvedWorkflowSpr('kpr_bank');
    $trx = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
    $submission = KprSubmission::where('spr_id', $spr->id)->first();
    $f = BankKprFinancing::create(['kpr_submission_id' => $submission->id, 'sale_price' => 300000000, 'approved_limit' => 250000000, 'down_payment' => 25000000, 'shortfall' => 25000000, 'booking_fee' => 0, 'developer_fee' => 1000000, 'notary_fee' => 2000000, 'expected_disbursement_date' => '2026-03-01', 'record_status' => 'locked']);
    app(BankKprFinancialService::class)->approveFinancing($f);
    app(BankKprFinancialService::class)->approveFinancing($f->fresh());
    expect(PaymentSchedule::where('sales_transaction_id', $trx->id)->where('type', 'bank_disbursement')->count())->toBe(1)->and(PaymentSchedule::where('sales_transaction_id', $trx->id)->count())->toBe(5);
    $d = BankKprDisbursement::create(['disbursement_no' => 'DISB/TEST/1', 'kpr_submission_id' => $submission->id, 'disbursement_date' => '2026-03-01', 'amount' => 100000000, 'bank_reference' => 'BANK-001', 'record_status' => 'locked']);
    app(BankKprFinancialService::class)->approveDisbursement($d);
    app(BankKprFinancialService::class)->approveDisbursement($d->fresh());
    expect($d->fresh()->status)->toBe('posted')->and((float) PaymentSchedule::where('type', 'bank_disbursement')->value('paid_amount'))->toBe(100000000.0)->and(Journal::where('type', 'customer_receipt')->count())->toBe(1);
});
