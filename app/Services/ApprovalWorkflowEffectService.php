<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BankKprDisbursement;
use App\Models\BankKprFinancing;
use App\Models\CashInstallmentContract;
use App\Models\CashSale;
use App\Models\CustomerCharge;
use App\Models\CustomerReceipt;
use App\Models\CustomerRefund;
use App\Models\DetailRumah;
use App\Models\HousingReservation;
use App\Models\DeveloperKprApplication;
use App\Models\PettyCashFunding;
use App\Models\PettyCashDeposit;
use App\Models\PayrollBatch;
use App\Models\EmployeeAdvance;
use App\Models\SalesProcessStep;
use App\Models\SalesResolutionRequest;
use App\Models\Spr;
use App\Models\SprApproval;
use App\Models\WaterPayment;
use App\Models\WaterBillingPeriod;
use App\Models\UnitOwnership;
use App\Services\Marketing\MarketingLeadStatusService;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowEffectService
{
    public function submitted(Model $model, ApprovalRequest $request): void
    {
        if ($model instanceof WaterPayment && $request->status === ApprovalRequest::STATUS_PENDING) {
            $model->update(['status' => 'pending_approval']);
        }
        if ($model instanceof PettyCashFunding && $request->status === ApprovalRequest::STATUS_PENDING) {
            $model->update(['status' => PettyCashFunding::PENDING]);
        }
        if ($model instanceof PettyCashDeposit && $request->status === ApprovalRequest::STATUS_PENDING) {
            $model->update(['status' => 'pending']);
        }
        if ($model instanceof CustomerReceipt && $request->status === ApprovalRequest::STATUS_PENDING) {
            $model->update(['status' => 'pending_approval']);
        }
        if ($model instanceof CustomerCharge && $request->status === ApprovalRequest::STATUS_PENDING) {
            $request->module_key === 'customer-charge-reversal'
                ? $model->update(['reversal_status' => 'pending_approval'])
                : $model->update(['status' => 'pending_approval']);
        }
        if ($model instanceof HousingReservation && $request->module_key === 'housing-reservation' && $request->status === ApprovalRequest::STATUS_PENDING) {
            $model->update(['status' => 'pending_approval', 'payment_approval_status' => 'pending']);
        }
        if ($model instanceof Spr && $request->status === ApprovalRequest::STATUS_PENDING) {
            $model->forceFill(['status' => Spr::STATUS_MENUNGGU_APPROVAL])->save();
        }
    }

    public function approved(Model $model, ApprovalRequest $request): void
    {
        if ($model instanceof WaterBillingPeriod) {
            UnitOwnership::query()->where('is_active', true)
                ->whereHas('detailRumah', fn ($query) => $query->where('perumahan_id', $model->perumahan_id))
                ->each(function (UnitOwnership $owner) use ($model): void {
                    WaterPayment::query()->firstOrCreate(
                        ['water_billing_period_id' => $model->id, 'unit_ownership_id' => $owner->id],
                        ['perumahan_id' => $model->perumahan_id, 'detail_rumah_id' => $owner->detail_rumah_id, 'payment_no' => $model->period_code.'-'.$owner->id, 'amount' => $model->amount, 'status' => 'unpaid', 'record_status' => 'draft', 'created_by' => auth()->id(), 'updated_by' => auth()->id()],
                    );
                });
            return;
        }
        if ($model instanceof WaterPayment) {
            $model->update(['status' => 'paid']);
            return;
        }
        if ($model instanceof HousingReservation && $request->module_key === 'housing-reservation') {
            app(HousingReservationService::class)->finalize($model);
            app(HousingReservationService::class)->markPaid($model->fresh());
            return;
        }
        if ($model instanceof EmployeeAdvance) {
            app(AccountingService::class)->recordEmployeeAdvance($model);
            $model->update(['status' => 'approved']);

            return;
        }
        if ($model instanceof PayrollBatch) {
            app(AccountingService::class)->recordEmployeePayroll($model);
            $model->update(['status' => 'approved']);

            return;
        }
        if ($model instanceof PettyCashFunding) {
            $model->update(['status' => PettyCashFunding::APPROVED]);

            return;
        }
        if ($model instanceof PettyCashDeposit) {
            app(PettyCashService::class)->approveDeposit($model, auth()->id());

            return;
        }
        if ($model instanceof SalesProcessStep) {
            app(SalesProcessService::class)->approve($model);

            return;
        }
        if ($model instanceof SalesResolutionRequest) {
            $this->applySalesResolution($model);

            return;
        }
        if ($model instanceof CustomerReceipt) {
            app(CustomerReceivableService::class)->approveReceipt($model);

            return;
        }
        if ($model instanceof CustomerRefund) {
            app(CustomerRefundService::class)->approve($model);
            return;
        }
        if ($model instanceof CustomerCharge) {
            $request->module_key === 'customer-charge-reversal'
                ? app(CustomerChargeService::class)->reverse($model)
                : app(CustomerChargeService::class)->approve($model);

            return;
        }
        if ($model instanceof CashSale) {
            if ($model->spr) {
                app(CustomerReceivableService::class)->createDownPaymentInvoice($model->spr);
            }
            app(CustomerReceivableService::class)->finalizeSchedule($model);

            return;
        }
        if ($model instanceof BankKprFinancing) {
            app(BankKprFinancialService::class)->approveFinancing($model);

            return;
        }
        if ($model instanceof BankKprDisbursement) {
            app(BankKprFinancialService::class)->approveDisbursement($model);

            return;
        }
        if (! $model instanceof Spr) {
            return;
        }

        SprApproval::query()->create([
            'spr_id' => $model->id,
            'user_id' => auth()->id(),
            'level' => 'setting_approval_step_'.$request->current_step,
            'status' => 'disetujui',
            'catatan' => 'Disetujui melalui Setting Approval.',
            'approved_at' => now(),
        ]);
        $model->forceFill([
            'status' => Spr::STATUS_DISETUJUI,
            'booking_expires_at' => $model->booking_expires_at ?? now()->addDays(7),
        ])->save();
        DetailRumah::query()->whereKey($model->detail_rumah_id)->update([
            'status_penjualan' => 'booking',
            'booking_spr_id' => $model->id,
            'booking_at' => now(),
        ]);
        app(MarketingLeadStatusService::class)->markSpr($model, MarketingLeadStatusService::SPR);
        app(SalesPaymentWorkflowService::class)->processApprovedSpr($model->fresh(), auth()->id());
        app(HousingReservationService::class)->sprApproved($model->fresh());
    }

    private function applySalesResolution(SalesResolutionRequest $resolution): void
    {
        if ($resolution->applied_at) {
            return;
        }

        $transaction = $resolution->salesTransaction()->lockForUpdate()->firstOrFail();
        $activeSubmission = $transaction->paymentSubmissions()->where('status', 'in_progress')->latest('attempt_no')->first();
        $activeSubmission?->update([
            'status' => $resolution->action === 'retry_stage' ? 'continued' : 'ended',
            'outcome' => $resolution->action === 'retry_stage' ? 'retry_approved' : 'not_approved',
            'failure_category' => $resolution->failure_category,
            'failure_reason' => $resolution->failure_reason,
            'ended_at' => now(),
        ]);

        if ($resolution->action === 'close_lost') {
            $transaction->update(['status' => 'closed_lost', 'outcome' => 'failed', 'failure_stage' => $resolution->failed_stage, 'failure_category' => $resolution->failure_category, 'failure_reason' => $resolution->failure_reason, 'closed_at' => now()]);
            DetailRumah::whereKey($transaction->detail_rumah_id)->update(['status_penjualan' => 'tersedia', 'booking_spr_id' => null, 'booking_at' => null]);
            app(CustomerRefundService::class)->ensureDraftForResolution($resolution);
        } elseif ($resolution->action === 'retry_stage') {
            $transaction->update(['status' => 'active', 'outcome' => null, 'failure_stage' => null, 'failure_category' => null, 'failure_reason' => null, 'closed_at' => null]);
            $transaction->processSteps()->where('code', $resolution->restart_stage ?: $resolution->failed_stage)->update(['status' => 'available', 'record_status' => 'draft', 'locked_at' => null, 'locked_by' => null]);
        } else {
            $source = $resolution->spr ?: $transaction->spr;
            $revision = $source->replicate();
            $revision->kode_spr = preg_replace('/-R\d+$/', '', $source->kode_spr).'-R'.($source->revision_no + 1);
            $revision->revision_no = $source->revision_no + 1;
            $revision->revision_status = 'current';
            $revision->metode_pembayaran = $resolution->proposed_payment_method;
            $revision->status = Spr::STATUS_DRAFT;
            $revision->record_status = 'draft';
            $revision->locked_at = null;
            $revision->locked_by = null;
            $revision->payment_configuration_snapshot = null;
            $revision->save();
            foreach ($source->berkasCostumers as $file) {
                $revision->berkasCostumers()->create($file->only(['dokumen_costumer_id', 'customer_document_id', 'is_selected', 'nama_file', 'path_file', 'mime_type', 'file_size', 'keterangan', 'uploaded_by']));
            }
            $source->update(['revision_status' => 'pending_revision', 'superseded_by_spr_id' => $revision->id]);
            $transaction->update(['status' => 'awaiting_spr_revision', 'outcome' => 'payment_method_change']);
        }

        $resolution->update(['status' => 'approved', 'applied_at' => now(), 'applied_by' => auth()->id()]);
    }

    public function rejected(Model $model, ApprovalRequest $request, ?string $note): void
    {
        if ($model instanceof WaterPayment) {
            $model->update(['status' => 'rejected']);
            return;
        }
        if ($model instanceof HousingReservation && $request->module_key === 'housing-reservation') {
            $model->paymentSchedule()->delete();
            DetailRumah::query()->whereKey($model->detail_rumah_id)->where('status_penjualan', 'booking')->update(['status_penjualan' => 'tersedia', 'booking_at' => null]);
            $model->update(['status' => 'draft', 'payment_approval_status' => 'rejected', 'record_status' => 'draft', 'locked_at' => null, 'locked_by' => null, 'updated_by' => auth()->id()]);
            return;
        }
        if ($model instanceof EmployeeAdvance) {
            $model->update(['status' => 'draft']);

            return;
        }
        if ($model instanceof PayrollBatch) {
            $model->update(['status' => 'draft']);

            return;
        }
        if ($model instanceof PettyCashFunding) {
            $model->update(['status' => PettyCashFunding::REJECTED, 'rejection_notes' => $note]);

            return;
        }
        if ($model instanceof PettyCashDeposit) {
            $model->update(['status' => 'rejected']);

            return;
        }
        if ($model instanceof SalesProcessStep) {
            $model->update(['status' => 'available']);

            return;
        }
        if ($model instanceof CustomerReceipt) {
            $model->update(['status' => 'rejected']);

            return;
        }
        if ($model instanceof CustomerCharge) {
            $request->module_key === 'customer-charge-reversal'
                ? $model->update(['reversal_status' => 'rejected'])
                : $model->update(['status' => 'rejected']);

            return;
        }
        if ($model instanceof SalesResolutionRequest) {
            $model->update(['status' => 'rejected']);

            return;
        }
        if ($model instanceof CustomerRefund) {
            $model->update(['status' => 'rejected']);
            return;
        }
        if (! $model instanceof Spr) {
            return;
        }

        SprApproval::query()->create([
            'spr_id' => $model->id,
            'user_id' => auth()->id(),
            'level' => 'setting_approval_step_'.$request->current_step,
            'status' => 'ditolak',
            'catatan' => $note,
            'approved_at' => now(),
        ]);
        $model->forceFill(['status' => Spr::STATUS_DITOLAK])->save();
        DetailRumah::query()->whereKey($model->detail_rumah_id)->where('booking_spr_id', $model->id)->update([
            'status_penjualan' => 'tersedia',
            'booking_spr_id' => null,
            'booking_at' => null,
        ]);
        app(MarketingLeadStatusService::class)->markSpr($model, MarketingLeadStatusService::BATAL, $note);
    }
}
