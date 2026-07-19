<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CustomerCharge;
use App\Models\PaymentSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerChargeService
{
    public function approve(CustomerCharge $charge): void
    {
        DB::transaction(function () use ($charge) {
            $charge = CustomerCharge::with('salesTransaction')->lockForUpdate()->findOrFail($charge->id);
            if ($charge->status === 'posted') {
                return;
            }

            $invoice = PaymentSchedule::firstOrCreate(
                ['source_type' => $charge->getMorphClass(), 'source_id' => $charge->id, 'sequence' => 1],
                ['sales_transaction_id' => $charge->sales_transaction_id, 'type' => $charge->charge_type, 'description' => $charge->description, 'issued_at' => $charge->charge_date, 'due_date' => $charge->due_date, 'amount' => $charge->amount, 'paid_amount' => 0, 'status' => 'belum_dibayar', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id()]
            );
            if (! $invoice->invoice_no) {
                $invoice->update(['invoice_no' => 'INV-TAMBAHAN/'.now()->format('Y').'/'.str_pad((string) $invoice->id, 7, '0', STR_PAD_LEFT)]);
            }

            $creditAccount = $charge->charge_type === 'customer_advance' ? ChartOfAccount::KAS_BANK : ChartOfAccount::PENDAPATAN_ADMIN;
            $journal = app(AccountingService::class)->postJournal($charge, 'customer_charge', $charge->charge_date->toDateString(), $charge->salesTransaction?->perumahan_id, $charge->salesTransaction?->detail_rumah_id, "{$charge->charge_no} - {$charge->description}", [
                ['account' => ChartOfAccount::PIUTANG_CUSTOMER, 'debit' => $charge->amount, 'kredit' => 0],
                ['account' => $creditAccount, 'debit' => 0, 'kredit' => $charge->amount],
            ]);
            $charge->update(['status' => 'posted', 'approved_at' => now(), 'approved_by' => auth()->id(), 'journal_id' => $journal->id]);
        });
    }

    public function reverse(CustomerCharge $charge): void
    {
        DB::transaction(function () use ($charge) {
            $charge = CustomerCharge::with(['salesTransaction', 'invoice'])->lockForUpdate()->findOrFail($charge->id);
            if ($charge->status === 'reversed') {
                return;
            }
            if (! $charge->invoice || (float) $charge->invoice->paid_amount > 0) {
                throw ValidationException::withMessages(['reversal' => 'Tagihan yang sudah memiliki pembayaran tidak dapat direversal. Gunakan koreksi penerimaan terlebih dahulu.']);
            }

            $debitAccount = $charge->charge_type === 'customer_advance' ? ChartOfAccount::KAS_BANK : ChartOfAccount::PENDAPATAN_ADMIN;
            $journal = app(AccountingService::class)->postJournal($charge, 'customer_charge_reversal', now()->toDateString(), $charge->salesTransaction?->perumahan_id, $charge->salesTransaction?->detail_rumah_id, "Reversal {$charge->charge_no}: {$charge->reversal_reason}", [
                ['account' => $debitAccount, 'debit' => $charge->amount, 'kredit' => 0],
                ['account' => ChartOfAccount::PIUTANG_CUSTOMER, 'debit' => 0, 'kredit' => $charge->amount],
            ]);
            $charge->invoice->update(['amount' => 0, 'status' => 'dibatalkan']);
            $charge->update(['status' => 'reversed', 'reversal_status' => 'approved', 'reversed_at' => now(), 'reversed_by' => auth()->id(), 'reversal_journal_id' => $journal->id]);
        });
    }
}
