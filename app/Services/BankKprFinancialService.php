<?php

namespace App\Services;

use App\Models\BankKprDisbursement;
use App\Models\BankKprFinancing;
use App\Models\CustomerReceipt;
use App\Models\PaymentSchedule;
use App\Models\SalesWorkflowHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankKprFinancialService
{
    public function approveFinancing(BankKprFinancing $f): void
    {
        DB::transaction(function () use ($f) {
            $f->loadMissing('submission.spr.salesTransaction');
            $trx = $f->submission?->spr?->salesTransaction;
            if (! $trx) {
                throw ValidationException::withMessages(['transaction' => 'Transaksi penjualan SPR belum tersedia.']);
            }$items = [['booking_fee', 'Booking Fee', $f->booking_fee, $f->sp3k_date ?: today()], ['down_payment', 'Uang Muka / DP', $f->down_payment, $f->sp3k_date ?: today()], ['shortfall', 'Kekurangan Plafon', $f->shortfall, $f->expected_disbursement_date ?: today()], ['developer_fee', 'Biaya Administrasi Developer', $f->developer_fee, $f->sp3k_date ?: today()], ['notary_fee', 'Biaya Notaris / Akad', $f->notary_fee, $f->sp3k_date ?: today()], ['bank_disbursement', 'Pencairan KPR Bank', $f->approved_limit, $f->expected_disbursement_date ?: today()]];
            foreach ($items as $i => [$type,$desc,$amount,$due]) {
                if ((float) $amount <= 0) {
                    continue;
                }$row = PaymentSchedule::firstOrCreate(['sales_transaction_id' => $trx->id, 'source_type' => $f::class, 'source_id' => $f->id, 'sequence' => $i + 1], ['type' => $type, 'description' => $desc, 'due_date' => $due, 'amount' => $amount, 'paid_amount' => 0, 'status' => 'belum_dibayar', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id()]);
                if (! $row->invoice_no) {
                    $row->update(['invoice_no' => 'INV/'.now()->format('Y').'/'.str_pad((string) $row->id, 7, '0', STR_PAD_LEFT), 'issued_at' => now()]);
                }app(AccountingService::class)->recordCustomerInvoice($row->fresh());
            }$f->submission->update(['nilai_pengajuan' => $f->approved_limit, 'status' => 'sp3k_keluar']);
            SalesWorkflowHistory::firstOrCreate(['sales_transaction_id' => $trx->id, 'process' => 'bank_kpr_financing_approved', 'notes' => 'Struktur pembiayaan KPR Bank disetujui.'], ['to_status' => 'sp3k_keluar', 'user_id' => auth()->id(), 'occurred_at' => now()]);
        });
    }

    public function approveDisbursement(BankKprDisbursement $d): void
    {
        DB::transaction(function () use ($d) {
            $d = BankKprDisbursement::with(['submission.spr.salesTransaction', 'bankAccount'])->lockForUpdate()->findOrFail($d->id);
            if ($d->status === 'posted') {
                return;
            }$trx = $d->submission?->spr?->salesTransaction;
            $invoice = PaymentSchedule::where('sales_transaction_id', $trx?->id)->where('type', 'bank_disbursement')->whereColumn('paid_amount', '<', 'amount')->orderBy('id')->first();
            if (! $trx || ! $invoice) {
                throw ValidationException::withMessages(['disbursement' => 'Tagihan pencairan bank resmi belum tersedia. Finalisasi struktur pembiayaan dahulu.']);
            }$remaining = (float) $invoice->amount - (float) $invoice->paid_amount;
            if ((float) $d->amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Pencairan melebihi sisa plafon yang belum diterima.']);
            }$receipt = CustomerReceipt::firstOrCreate(['receipt_no' => 'RCV-KPR/'.$d->disbursement_no], ['sales_transaction_id' => $trx->id, 'master_bank_id' => $d->master_bank_id, 'payment_date' => $d->disbursement_date, 'amount' => $d->amount, 'payment_method' => 'transfer', 'bank_reference' => $d->bank_reference, 'sender_bank' => $d->submission?->bank?->nama_bank, 'sender_name' => $d->submission?->bank?->nama_bank, 'proof_path' => $d->proof_path, 'proof_original_name' => $d->proof_original_name, 'notes' => 'Pencairan KPR Bank '.$d->disbursement_no, 'status' => 'pending_approval', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id(), 'created_by' => $d->created_by]);
            $receipt->allocations()->firstOrCreate(['payment_schedule_id' => $invoice->id], ['amount' => $d->amount, 'allocation_type' => 'invoice']);
            app(CustomerReceivableService::class)->approveReceipt($receipt);
            $d->update(['status' => 'posted', 'customer_receipt_id' => $receipt->id]);
            $d->submission->update(['status' => (float) $invoice->fresh()->paid_amount >= (float) $invoice->amount ? 'pencairan_lunas' : 'pencairan_sebagian']);
        });
    }
}
