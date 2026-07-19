<?php

namespace App\Services;

use App\Models\CashInstallmentContract;
use App\Models\CashSale;
use App\Models\ChartOfAccount;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptAllocation;
use App\Models\DeveloperKprApplication;
use App\Models\Journal;
use App\Models\PaymentSchedule;
use App\Models\SalesWorkflowHistory;
use App\Models\Spr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerReceivableService
{
    public function createDownPaymentInvoice(Spr $spr): void
    {
        DB::transaction(function () use ($spr): void {
            $spr->loadMissing(['salesTransaction', 'housingReservation.paymentSchedule']);
            $transaction = $spr->salesTransaction;
            if (! $transaction) return;
            $bookingCredit = $spr->booking_fee_includes_dp ? (float) $spr->booking_fee : 0;
            $amount = max(0, (float) $spr->uang_muka - $bookingCredit);
            $this->invoice($transaction, $spr, 1, 'Uang Muka', $spr->tanggal_jatuh_tempo_dp ?: $spr->tanggal_spr, $amount, 'down_payment');
            $this->allocateApprovedDeposits($transaction->id);
        });
    }

    public function finalizeSchedule(CashInstallmentContract|DeveloperKprApplication|CashSale $source): void
    {
        DB::transaction(function () use ($source) {
            if ($source instanceof CashSale) {
                $source->loadMissing('spr.salesTransaction');
            } else {
                $source->loadMissing('salesTransaction');
            }
            $transaction = $source instanceof CashSale ? $source->spr?->salesTransaction : $source->salesTransaction;
            if (! $transaction) {
                return;
            }
            if ($source instanceof CashSale) {
                $spr = $source->spr;
                $upfront = $spr->booking_fee_includes_dp ? max((float) $spr->booking_fee, (float) $spr->uang_muka) : (float) $spr->booking_fee + (float) $spr->uang_muka;
                $this->invoice($transaction, $source, 1, 'Pelunasan', $spr->tanggal_jatuh_tempo_angsuran ?: $spr->tanggal_jatuh_tempo_dp ?: $spr->tanggal_spr, max(0, (float) $source->total_tagihan - $upfront));
            } elseif ($source instanceof CashInstallmentContract) {
                $steps = collect($source->scheme_snapshot['steps'] ?? [])->sortBy('sequence')->values();
                $count = max(1, $steps->count() ?: (int) ($source->scheme_snapshot['installment_count'] ?? 1));
                $remaining = (int) round((float) $source->contract_value * 100);
                $allocated = 0;
                for ($i = 1; $i <= $count; $i++) {
                    $step = $steps->get($i - 1, []);
                    $type = $step['calculation_type'] ?? 'equal';
                    $value = (float) ($step['value'] ?? 0);
                    $cents = match ($type) {
                        'fixed' => (int) round($value * 100),'percentage_sale','percentage_final' => (int) round($remaining * $value / 100),'remaining' => $remaining - $allocated,default => intdiv($remaining, $count) + ($i <= $remaining % $count ? 1 : 0)
                    };
                    $allocated += $cents;
                    $this->invoice($transaction, $source, $i, $step['name'] ?? "Termin {$i}", $source->start_date->copy()->addMonths((int) ($step['due_offset_months'] ?? $i - 1)), $cents / 100);
                }
                $source->update(['status' => 'active']);
            } else {
                $count = max(1, (int) $source->tenor_months);
                $principal = (int) round((float) $source->financing_amount * 100);
                $installment = (int) round((float) $source->estimated_installment * 100);
                for ($i = 1; $i <= $count; $i++) {
                    $this->invoice($transaction, $source, $i, "Angsuran {$i}", now()->startOfDay()->addMonths($i), ($i === $count ? max(0, $principal - $installment * ($count - 1)) : $installment) / 100);
                }
                $source->update(['status' => 'approved']);
            }
            $this->allocateApprovedDeposits($transaction->id);
            SalesWorkflowHistory::firstOrCreate(['sales_transaction_id' => $transaction->id, 'process' => 'billing_schedule_approved', 'notes' => 'Jadwal resmi '.$source::class.' #'.$source->id.' dibuat setelah approval final.'], ['to_status' => 'active', 'user_id' => auth()->id(), 'occurred_at' => now()]);
        });
    }

    private function allocateApprovedDeposits(int $transactionId): void
    {
        $deposits = CustomerReceiptAllocation::query()->with('receipt')->whereNull('payment_schedule_id')
            ->where('allocation_type', 'deposit')->whereHas('receipt', fn ($q) => $q->where('sales_transaction_id', $transactionId)->where('status', 'posted'))
            ->orderBy('id')->lockForUpdate()->get();
        $schedules = PaymentSchedule::query()->where('sales_transaction_id', $transactionId)->where('record_status', 'locked')
            ->whereColumn('paid_amount', '<', 'amount')->orderBy('due_date')->orderBy('sequence')->lockForUpdate()->get();

        foreach ($deposits as $deposit) {
            $available = (float) $deposit->amount;
            $purpose = $deposit->receipt?->receipt_purpose;
            $ordered = $schedules->sortBy(fn ($schedule) => match ($purpose) {
                'booking_fee' => str_contains(strtolower($schedule->description), 'booking') ? 0 : 1,
                'down_payment' => str_contains(strtolower($schedule->description), 'uang muka') ? 0 : 1,
                default => 0,
            });
            foreach ($ordered as $schedule) {
                $remaining = max(0, (float) $schedule->amount - (float) $schedule->paid_amount);
                if ($available <= 0 || $remaining <= 0) {
                    continue;
                }
                $amount = min($available, $remaining);
                if (abs($amount - (float) $deposit->amount) < 0.005) {
                    $allocation = $deposit;
                    $allocation->update(['payment_schedule_id' => $schedule->id, 'allocation_type' => 'invoice', 'notes' => 'Deposit dialokasikan otomatis setelah jadwal resmi disetujui.']);
                } else {
                    $deposit->decrement('amount', $amount);
                    $allocation = $deposit->receipt->allocations()->create(['payment_schedule_id' => $schedule->id, 'amount' => $amount, 'allocation_type' => 'invoice', 'notes' => 'Deposit dialokasikan otomatis setelah jadwal resmi disetujui.']);
                }
                $schedule->increment('paid_amount', $amount);
                $schedule->refresh()->update(['status' => (float) $schedule->paid_amount >= (float) $schedule->amount ? 'lunas' : 'sebagian']);
                app(AccountingService::class)->postJournal($allocation, 'customer_deposit_allocation', now()->toDateString(), $deposit->receipt->salesTransaction->perumahan_id, $deposit->receipt->salesTransaction->detail_rumah_id, "Alokasi deposit {$deposit->receipt->receipt_no} ke {$schedule->invoice_no}", [
                    ['account' => ChartOfAccount::UANG_MUKA_CUSTOMER, 'debit' => $amount, 'kredit' => 0],
                    ['account' => ChartOfAccount::PIUTANG_CUSTOMER, 'debit' => 0, 'kredit' => $amount],
                ]);
                $available -= $amount;
            }
        }
    }

    private function invoice($transaction, $source, int $sequence, string $description, $dueDate, float $amount, ?string $invoiceType = null): void
    {
        if ($amount <= 0) {
            return;
        }
        if (str_contains(strtolower($description), 'booking') && $transaction->spr?->housingReservation?->paymentSchedule) {
            $transaction->spr->housingReservation->paymentSchedule->update(['sales_transaction_id' => $transaction->id]);
            return;
        }
        $row = PaymentSchedule::firstOrCreate(['sales_transaction_id' => $transaction->id, 'source_type' => $source::class, 'source_id' => $source->id, 'sequence' => $sequence], [
            'type' => $invoiceType ?? ($source instanceof CashInstallmentContract ? 'termin' : ($source instanceof CashSale ? 'cash' : 'angsuran')), 'description' => $description, 'due_date' => $dueDate, 'amount' => $amount, 'paid_amount' => 0, 'status' => 'belum_dibayar', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id(),
        ]);
        if (! $row->invoice_no) {
            $row->update(['invoice_no' => 'INV/'.now()->format('Y').'/'.str_pad((string) $row->id, 7, '0', STR_PAD_LEFT), 'issued_at' => now()]);
        }
        app(AccountingService::class)->recordCustomerInvoice($row->fresh());
    }

    public function approveReceipt(CustomerReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $receipt = CustomerReceipt::query()->with(['allocations.schedule', 'salesTransaction.housingProject'])->lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status === 'posted') {
                return;
            }
            $allocated = round((float) $receipt->allocations->whereNotNull('payment_schedule_id')->sum('amount'), 2);
            if ($allocated > (float) $receipt->amount) {
                throw ValidationException::withMessages(['allocations' => 'Alokasi melebihi nominal penerimaan.']);
            }
            foreach ($receipt->allocations->whereNotNull('payment_schedule_id')->groupBy('payment_schedule_id') as $group) {
                $schedule = PaymentSchedule::query()->lockForUpdate()->findOrFail($group->first()->payment_schedule_id);
                abort_unless((int) $schedule->sales_transaction_id === (int) $receipt->sales_transaction_id, 422, 'Tagihan bukan milik transaksi yang dipilih.');
                $paymentAmount = round((float) $group->sum('amount'), 2);
                $remaining = round(max(0, (float) $schedule->amount - (float) $schedule->paid_amount), 2);
                if ($paymentAmount > $remaining) {
                    throw ValidationException::withMessages(['allocations' => "Alokasi ke invoice {$schedule->invoice_no} melebihi sisa tagihan ".number_format($remaining, 0, ',', '.')]);
                }
                $newPaid = (float) $schedule->paid_amount + $paymentAmount;
                $schedule->update(['paid_amount' => $newPaid, 'status' => $newPaid >= (float) $schedule->amount ? 'lunas' : 'sebagian']);
            }
            $journal = $this->receiptJournal($receipt, $allocated);
            $receipt->update(['status' => 'posted', 'approved_at' => now(), 'approved_by' => auth()->id(), 'journal_id' => $journal->id]);
            SalesWorkflowHistory::firstOrCreate(['sales_transaction_id' => $receipt->sales_transaction_id, 'process' => 'customer_receipt_posted', 'notes' => "Penerimaan {$receipt->receipt_no} disetujui dan diposting."], ['to_status' => 'posted', 'user_id' => auth()->id(), 'occurred_at' => now()]);
        });
    }

    private function receiptJournal(CustomerReceipt $receipt, float $allocated): Journal
    {
        $deposit = max(0, round((float) $receipt->amount - $allocated, 2));
        $lines = [['account' => ChartOfAccount::KAS_BANK, 'debit' => $receipt->amount, 'kredit' => 0]];
        if ($allocated > 0) {
            $lines[] = ['account' => ChartOfAccount::PIUTANG_CUSTOMER, 'debit' => 0, 'kredit' => $allocated];
        }
        if ($deposit > 0) {
            $lines[] = ['account' => ChartOfAccount::UANG_MUKA_CUSTOMER, 'debit' => 0, 'kredit' => $deposit];
        }

        return app(AccountingService::class)->postJournal($receipt, 'customer_receipt', $receipt->payment_date->toDateString(), $receipt->salesTransaction->perumahan_id, $receipt->salesTransaction->detail_rumah_id, "Penerimaan customer {$receipt->receipt_no}", $lines, $receipt->master_bank_id);
    }
}
