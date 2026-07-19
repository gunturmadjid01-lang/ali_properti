<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CustomerRefund;
use App\Models\CustomerRefundItem;
use App\Models\PaymentSchedule;
use App\Models\SalesResolutionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerRefundService
{
    public function ensureDraftForResolution(SalesResolutionRequest $resolution): ?CustomerRefund
    {
        if ($resolution->action !== 'close_lost' || $resolution->financial_treatment !== 'refund') return null;
        $eligible = $this->eligibleAmount($resolution->sales_transaction_id);

        return CustomerRefund::firstOrCreate(
            ['sales_resolution_request_id' => $resolution->id],
            ['refund_no' => 'RFD/'.now()->format('Y').'/'.str_pad((string) (CustomerRefund::withTrashed()->count() + 1), 7, '0', STR_PAD_LEFT), 'sales_transaction_id' => $resolution->sales_transaction_id, 'eligible_amount' => $eligible, 'refund_amount' => $eligible, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]
        );
    }

    public function eligibleAmount(int $transactionId, ?int $excludingRefundId = null): float
    {
        $paid = (float) PaymentSchedule::query()->where('sales_transaction_id', $transactionId)
            ->where(fn ($q) => $q->whereIn('type', ['booking_fee', 'down_payment'])->orWhere('description', 'like', '%Booking Fee%')->orWhere('description', 'like', '%Uang Muka%'))
            ->sum('paid_amount');
        $refunded = (float) CustomerRefund::query()->where('sales_transaction_id', $transactionId)->where('status', 'posted')
            ->when($excludingRefundId, fn ($q) => $q->where('id', '!=', $excludingRefundId))->sum('refund_amount');

        return round(max(0, $paid - $refunded), 2);
    }

    public function approve(CustomerRefund $refund): void
    {
        DB::transaction(function () use ($refund): void {
            $refund = CustomerRefund::with(['resolution', 'salesTransaction', 'items'])->lockForUpdate()->findOrFail($refund->id);
            if ($refund->status === 'posted') return;
            if ($refund->resolution?->status !== 'approved' || $refund->resolution?->action !== 'close_lost' || $refund->resolution?->financial_treatment !== 'refund') {
                throw ValidationException::withMessages(['resolution' => 'Refund hanya dapat diposting dari penutupan penjualan yang telah disetujui.']);
            }
            $eligible = $this->eligibleAmount($refund->sales_transaction_id, $refund->id);
            $disposition = round((float) $refund->refund_amount + (float) $refund->penalty_amount, 2);
            if ($eligible <= 0 || abs($disposition - $eligible) > 0.005) {
                throw ValidationException::withMessages(['refund_amount' => 'Refund ditambah potongan harus sama dengan dana Booking Fee/DP yang masih dapat dikembalikan.']);
            }

            $remaining = (float) $refund->refund_amount;
            $schedules = PaymentSchedule::query()->where('sales_transaction_id', $refund->sales_transaction_id)
                ->where(fn ($q) => $q->whereIn('type', ['booking_fee', 'down_payment'])->orWhere('description', 'like', '%Booking Fee%')->orWhere('description', 'like', '%Uang Muka%'))
                ->where('paid_amount', '>', 0)->orderBy('issued_at')->orderBy('id')->lockForUpdate()->get();
            foreach ($schedules as $schedule) {
                if ($remaining <= 0) break;
                $amount = min($remaining, (float) $schedule->paid_amount);
                CustomerRefundItem::firstOrCreate(['customer_refund_id' => $refund->id, 'payment_schedule_id' => $schedule->id], ['amount' => $amount]);
                $newPaid = round((float) $schedule->paid_amount - $amount, 2);
                $schedule->update(['paid_amount' => $newPaid, 'status' => $newPaid <= 0 ? 'dibatalkan' : 'sebagian']);
                $remaining = round($remaining - $amount, 2);
            }
            if ($remaining > 0.005) throw ValidationException::withMessages(['refund_amount' => 'Alokasi dana Booking Fee/DP tidak mencukupi.']);

            PaymentSchedule::query()->where('sales_transaction_id', $refund->sales_transaction_id)->whereColumn('paid_amount', '<', 'amount')->update(['status' => 'dibatalkan']);
            $lines = [['account' => ChartOfAccount::UANG_MUKA_CUSTOMER, 'debit' => $eligible, 'kredit' => 0], ['account' => ChartOfAccount::KAS_BANK, 'debit' => 0, 'kredit' => (float) $refund->refund_amount]];
            if ((float) $refund->penalty_amount > 0) $lines[] = ['account' => ChartOfAccount::PENDAPATAN_ADMIN, 'debit' => 0, 'kredit' => (float) $refund->penalty_amount];
            $journal = app(AccountingService::class)->postJournal($refund, 'customer_refund', $refund->refund_date->toDateString(), $refund->salesTransaction?->perumahan_id, $refund->salesTransaction?->detail_rumah_id, "Refund {$refund->refund_no}", $lines, $refund->master_bank_id);
            $refund->update(['eligible_amount' => $eligible, 'status' => 'posted', 'approved_at' => now(), 'approved_by' => auth()->id(), 'journal_id' => $journal->id]);
            $refund->salesTransaction?->update(['status' => 'cancelled']);
        });
    }
}
