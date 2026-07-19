<?php

use App\Models\ApprovalSetting;
use App\Models\CashInstallmentContract;
use App\Models\ChartOfAccount;
use App\Models\CustomerReceipt;
use App\Models\Journal;
use App\Models\PaymentSchedule;
use App\Services\ApprovalWorkflowService;
use App\Services\CustomerReceivableService;
use App\Services\SalesPaymentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedReceiptAccounts(): void
{
    foreach ([[ChartOfAccount::KAS_BANK, 'Kas Bank', 'aset', 'debit'], [ChartOfAccount::PIUTANG_CUSTOMER, 'Piutang Customer', 'aset', 'debit'], [ChartOfAccount::UANG_MUKA_CUSTOMER, 'Deposit Customer', 'liabilitas', 'kredit']] as [$code,$name,$cat,$normal]) {
        ChartOfAccount::updateOrCreate(['kode_akun' => $code], ['nama_akun' => $name, 'kategori' => $cat, 'posisi_normal' => $normal, 'status' => 'aktif']);
    }
}

test('penerimaan baru memposting piutang dan jurnal setelah approval final serta idempoten', function () {
    seedReceiptAccounts();
    $spr = approvedWorkflowSpr('cash_bertahap');
    $trx = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
    $contract = CashInstallmentContract::where('sales_transaction_id', $trx->id)->first();
    app(CustomerReceivableService::class)->finalizeSchedule($contract);
    $invoice = PaymentSchedule::first();
    $receipt = CustomerReceipt::create(['receipt_no' => 'RCV/TEST/1', 'sales_transaction_id' => $trx->id, 'payment_date' => '2026-02-01', 'amount' => 1000000, 'payment_method' => 'transfer', 'status' => 'draft', 'record_status' => 'locked']);
    $receipt->allocations()->create(['payment_schedule_id' => $invoice->id, 'amount' => 1000000, 'allocation_type' => 'invoice']);
    expect((float) $invoice->fresh()->paid_amount)->toBe(0.0);
    app(CustomerReceivableService::class)->approveReceipt($receipt);
    app(CustomerReceivableService::class)->approveReceipt($receipt->fresh());
    expect((float) $invoice->fresh()->paid_amount)->toBe(1000000.0)->and($receipt->fresh()->status)->toBe('posted')->and(Journal::where('type', 'customer_receipt')->count())->toBe(1);
});

test('approval nol tahap auto approve penerimaan', function () {
    seedReceiptAccounts();
    $spr = approvedWorkflowSpr('cash_bertahap');
    $trx = app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
    $contract = CashInstallmentContract::where('sales_transaction_id', $trx->id)->first();
    app(CustomerReceivableService::class)->finalizeSchedule($contract);
    $invoice = PaymentSchedule::first();
    ApprovalSetting::where('module_key', 'customer-receipt')->update(['approval_stages' => 0, 'requires_approval' => false]);
    $receipt = CustomerReceipt::create(['receipt_no' => 'RCV/TEST/AUTO', 'sales_transaction_id' => $trx->id, 'payment_date' => '2026-02-01', 'amount' => 500000, 'payment_method' => 'cash', 'record_status' => 'locked']);
    $receipt->allocations()->create(['payment_schedule_id' => $invoice->id, 'amount' => 500000]);
    app(ApprovalWorkflowService::class)->submitLocked($receipt, 'customer-receipt');
    expect($receipt->fresh()->status)->toBe('posted');
});
