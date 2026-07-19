<?php

namespace App\Services;

use App\Models\DetailRumah;
use App\Models\HousingReservation;
use App\Models\SalesProcessStep;
use App\Models\Spr;
use App\Models\PaymentSchedule;
use App\Models\CustomerReceipt;
use App\Models\ChartOfAccount;
use App\Models\PettyCashAccount;
use App\Models\PettyCashLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HousingReservationService
{
    public function create(array $data): HousingReservation
    {
        return DB::transaction(function () use ($data) {
            DetailRumah::query()->findOrFail($data['detail_rumah_id']);
            $reservation = HousingReservation::create([
                ...$data,
                'reservation_no' => 'RSV/'.now()->format('Y').'/'.str_pad((string) ((int) HousingReservation::max('id') + 1), 6, '0', STR_PAD_LEFT),
                'invoice_no' => 'INV-BF/'.now()->format('Y').'/'.str_pad((string) ((int) HousingReservation::max('id') + 1), 6, '0', STR_PAD_LEFT),
                'reserved_at' => now(), 'payment_due_at' => null, 'status' => 'draft', 'payment_status' => 'received_pending_approval', 'record_status' => 'draft',
                'created_by' => auth()->id(), 'updated_by' => auth()->id(),
            ]);
            return $reservation;
        });
    }

    public function updateDraft(HousingReservation $reservation, array $data): HousingReservation
    {
        $reservation->update([...$data, 'updated_by' => auth()->id()]);
        return $reservation->fresh();
    }

    public function lock(HousingReservation $reservation): HousingReservation
    {
        return DB::transaction(function () use ($reservation) {
            $row = HousingReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $unit = DetailRumah::query()->lockForUpdate()->findOrFail($row->detail_rumah_id);
            if (! in_array($unit->status_penjualan, ['tersedia', 'available'], true)) {
                throw ValidationException::withMessages(['detail_rumah_id' => 'Unit sudah tidak tersedia untuk direservasi.']);
            }
            $row->update(['record_status' => 'locked', 'status' => 'pending_approval', 'payment_approval_status' => null, 'locked_at' => now(), 'locked_by' => auth()->id(), 'updated_by' => auth()->id()]);
            $unit->update(['status_penjualan' => 'booking', 'booking_at' => now()]);
            return $row->fresh();
        });
    }

    public function finalize(HousingReservation $reservation): HousingReservation
    {
        return DB::transaction(function () use ($reservation) {
            $row = HousingReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $schedule = PaymentSchedule::firstOrCreate(
                ['housing_reservation_id' => $row->id],
                ['source_type' => HousingReservation::class, 'source_id' => $row->id, 'sequence' => 1, 'invoice_no' => $row->invoice_no, 'type' => 'booking_fee', 'description' => 'Booking Fee Reservasi '.$row->reservation_no, 'issued_at' => $row->payment_submitted_at, 'due_date' => $row->payment_submitted_at, 'amount' => $row->booking_fee, 'paid_amount' => 0, 'status' => 'belum_dibayar', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id()]
            );
            app(AccountingService::class)->recordCustomerInvoice($schedule);
            return $row->fresh();
        });
    }

    public function markPaid(HousingReservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $row = HousingReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($row->payment_status === 'paid') {
                $row->update([
                    'status' => 'active',
                    'payment_approval_status' => 'approved',
                    'updated_by' => auth()->id() ?? $row->updated_by,
                ]);

                return;
            }
            if (in_array($row->status, ['cancelled', 'customer_cancelled', 'expired'], true)) {
                throw ValidationException::withMessages(['status' => 'Reservasi tidak sedang menunggu pembayaran.']);
            }
            $schedule = $row->paymentSchedule()->lockForUpdate()->firstOrFail();
            $receipt = CustomerReceipt::firstOrCreate(['receipt_no' => 'RCV-RSV/'.$row->id], ['housing_reservation_id' => $row->id, 'master_bank_id' => $row->fund_master_bank_id, 'payment_date' => $row->payment_submitted_at, 'amount' => $row->booking_fee, 'payment_method' => $row->payment_channel, 'receipt_purpose' => 'booking_fee', 'bank_reference' => $row->payment_bank_reference, 'sender_name' => $row->payment_sender_name, 'proof_path' => $row->payment_proof_path, 'proof_original_name' => $row->payment_proof_original_name, 'notes' => $row->payment_notes, 'status' => 'posted', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $row->locked_by, 'approved_at' => now(), 'approved_by' => auth()->id(), 'created_by' => $row->created_by, 'updated_by' => auth()->id()]);
            $receipt->allocations()->firstOrCreate(['payment_schedule_id' => $schedule->id], ['amount' => $row->booking_fee, 'allocation_type' => 'invoice', 'notes' => 'Pelunasan Booking Fee reservasi.']);
            $cashAccount = $row->payment_channel === 'cash' ? ChartOfAccount::KAS_KECIL : ChartOfAccount::KAS_BANK;
            $journal = app(AccountingService::class)->postJournal($receipt, 'reservation_booking_fee_receipt', now()->toDateString(), $row->unit?->perumahan_id, $row->detail_rumah_id, 'Penerimaan Booking Fee '.$row->reservation_no, [['account' => $cashAccount, 'debit' => $row->booking_fee, 'kredit' => 0], ['account' => ChartOfAccount::PIUTANG_CUSTOMER, 'debit' => 0, 'kredit' => $row->booking_fee]], $row->payment_channel === 'transfer' ? $row->fund_master_bank_id : null);
            $receipt->update(['journal_id' => $journal->id]);
            $schedule->update(['paid_amount' => $row->booking_fee, 'status' => 'lunas']);
            if ($row->payment_channel === 'cash') {
                $account = PettyCashAccount::query()->lockForUpdate()->findOrFail($row->petty_cash_account_id);
                $ledger = PettyCashLedger::query()->firstOrCreate(
                    ['source_type' => HousingReservation::class, 'source_id' => $row->id],
                    ['petty_cash_account_id' => $account->id, 'transaction_date' => $row->payment_submitted_at, 'direction' => 'in', 'amount' => $row->booking_fee, 'balance_after' => (float) $account->balance + (float) $row->booking_fee, 'description' => 'Penerimaan Booking Fee '.$row->reservation_no, 'created_by' => $row->created_by],
                );
                if ($ledger->wasRecentlyCreated) {
                    $account->update(['balance' => $ledger->balance_after, 'updated_by' => auth()->id()]);
                }
            }
            $row->update(['status' => 'active', 'paid_amount' => $row->booking_fee, 'paid_at' => now(), 'payment_status' => 'paid', 'payment_approval_status' => 'approved', 'fund_received_at' => $row->fund_received_at ?? now(), 'fund_received_by' => $row->fund_received_by ?? auth()->id(), 'fund_custody_status' => $row->payment_channel === 'cash' ? 'in_marketing_petty_cash' : 'in_company_bank', 'finance_verification_notes' => $row->finance_verification_notes ?: 'Disetujui melalui Setting Approval reservasi.', 'updated_by' => auth()->id()]);
        });
    }

    public function cancel(HousingReservation $reservation, string $reason, string $type = 'internal'): void
    {
        DB::transaction(function () use ($reservation, $reason, $type) {
            $row = HousingReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($type !== 'automatic' && $row->record_status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Reservasi yang sudah dikunci tidak dapat dibatalkan dari daftar reservasi. Gunakan proses penanganan penjualan yang sesuai.']);
            }
            if ($row->spr_id || in_array($row->status, ['completed', 'cancelled', 'customer_cancelled', 'expired'], true)) {
                throw ValidationException::withMessages(['status' => 'Reservasi yang sudah masuk SPR harus dibatalkan melalui proses penanganan penjualan.']);
            }
            $status = match ($type) { 'customer' => 'customer_cancelled', 'automatic' => 'expired', default => 'cancelled' };
            $row->update(['status' => $status, 'cancellation_type' => $type, 'cancelled_at' => now(), 'cancelled_by' => $type === 'automatic' ? null : auth()->id(), 'cancellation_reason' => $reason, 'updated_by' => auth()->id()]);
            DetailRumah::query()->whereKey($row->detail_rumah_id)->where('status_penjualan', 'booking')->update(['status_penjualan' => 'tersedia', 'booking_spr_id' => null, 'booking_at' => null]);
            $row->paymentSchedule()->where('paid_amount', 0)->update(['status' => 'dibatalkan', 'record_status' => 'draft']);
        });
    }

    public function linkToSpr(HousingReservation $reservation, Spr $spr): void
    {
        $reservation->update(['spr_id' => $spr->id, 'status' => 'spr_created', 'process_stage' => 'Draft SPR', 'updated_by' => auth()->id()]);
    }

    public function sprApproved(Spr $spr): void
    {
        $reservation = $spr->housingReservation;
        $reservation?->update(['status' => 'sales_process', 'process_stage' => 'SPR Disetujui', 'updated_by' => auth()->id()]);
        if ($reservation && $spr->salesTransaction) {
            $reservation->paymentSchedule()->update(['sales_transaction_id' => $spr->salesTransaction->id]);
            $reservation->receipts()->update(['sales_transaction_id' => $spr->salesTransaction->id]);
        }
    }

    public function syncProcessStep(SalesProcessStep $step): void
    {
        $reservation = $step->salesTransaction?->spr?->housingReservation;
        if (! $reservation) return;
        $attributes = ['status' => 'sales_process', 'process_stage' => $step->label, 'updated_by' => auth()->id()];
        if ($step->code === 'customer_handover') $attributes['status'] = 'handover';
        if ($step->code === 'move_in') $attributes['status'] = 'occupied';
        if ($step->code === 'completed') $attributes = [...$attributes, 'status' => 'completed', 'completed_at' => now()];
        $reservation->update($attributes);
    }
}
