<?php

namespace App\Http\Controllers\Admin\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Approval\RejectApprovalRequest;
use App\Models\ApprovalRequest;
use App\Models\BankKprDisbursement;
use App\Models\BankKprFinancing;
use App\Models\CashInstallmentContract;
use App\Models\CashSale;
use App\Models\Costumer;
use App\Models\CustomerReceipt;
use App\Models\HousingReservation;
use App\Models\DeveloperKprApplication;
use App\Models\SalesProcessStep;
use App\Models\Spr;
use App\Services\ApprovalWorkflowService;
use App\Support\ApprovalResources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalRequestController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('approval.view'), 403);
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', ApprovalRequest::STATUS_PENDING);

        $rows = ApprovalRequest::query()
            ->with(['requester:id,name', 'reviewer:id,name'])
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('module_label', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ApprovalRequest $approvalRequest) => $this->presentation($approvalRequest));

        return Inertia::render('Admin/Approval/Index', [
            'title' => 'Approval Perubahan Data',
            'baseUrl' => route('admin.approval.requests.index', absolute: false),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'rows' => $rows,
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
                ['value' => 'all', 'label' => 'Semua'],
            ],
        ]);
    }

    private function presentation(ApprovalRequest $approvalRequest): array
    {
        $display = $this->businessDisplay($approvalRequest);
        $canReview = app(ApprovalWorkflowService::class)->canReview($approvalRequest);
        $detailUrl = $display['detail_url'];
        if ($approvalRequest->module_key === 'housing-reservation'
            && $approvalRequest->status === ApprovalRequest::STATUS_PENDING
            && ! $canReview) {
            $detailUrl = null;
        }

        return [
            'id' => $approvalRequest->id,
            'module_key' => $approvalRequest->module_key,
            'module_label' => $approvalRequest->module_label,
            'action' => $approvalRequest->action,
            'action_label' => ApprovalResources::actions()[$approvalRequest->action] ?? $approvalRequest->action,
            'status' => $approvalRequest->status,
            'current_step' => $approvalRequest->current_step,
            'total_steps' => $approvalRequest->total_steps,
            'step_history' => $approvalRequest->step_history ?? [],
            'can_review' => $canReview,
            'requested_by' => $approvalRequest->requester?->name,
            'reviewed_by' => $approvalRequest->reviewer?->name,
            'reviewed_at' => optional($approvalRequest->reviewed_at)->format('Y-m-d H:i'),
            'before_data' => $approvalRequest->before_data,
            'after_data' => $approvalRequest->after_data,
            'rejection_note' => $approvalRequest->rejection_note,
            'created_at' => optional($approvalRequest->created_at)->format('Y-m-d H:i'),
            'business_title' => $display['title'],
            'business_summary' => $display['summary'],
            'business_detail_url' => $detailUrl,
        ];
    }

    private function businessDisplay(ApprovalRequest $request): array
    {
        $model = $this->approvalModel($request);
        if ($model instanceof Spr) {
            $model->loadMissing(['costumer', 'detailRumah.perumahan', 'creator']);

            return $this->sprDisplay($model);
        }
        if ($model instanceof Costumer) {
            $model->loadMissing(['perumahan']);
            $spr = Spr::query()->with(['costumer', 'detailRumah.perumahan', 'creator'])->where('costumer_id', $model->id)->latest('id')->first();
            if ($spr) {
                return $this->sprDisplay($spr);
            }

return ['title' => $model->kode_costumer.' — '.$model->nama, 'summary' => ['Customer' => $model->nama, 'No. Identitas' => $model->no_identitas, 'Telepon' => $model->telepon, 'Perumahan' => $model->perumahan?->nama_perusahaan, 'Status' => $model->status], 'detail_url' => route('admin.marketing.calon-konsumen.show', $model->id, absolute: false)];
        }
        if ($model instanceof CustomerReceipt) {
            $model->loadMissing(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'bankAccount']);

            return ['title' => $model->receipt_no.' — '.$model->salesTransaction?->customer?->nama, 'summary' => ['Transaksi' => $model->salesTransaction?->transaction_no, 'Customer' => $model->salesTransaction?->customer?->nama, 'Perumahan' => $model->salesTransaction?->housingProject?->nama_perusahaan, 'Unit' => $model->salesTransaction?->housingUnit?->nomor_rumah, 'Tanggal' => $model->payment_date?->format('d/m/Y'), 'Nominal' => 'Rp '.number_format((float) $model->amount, 0, ',', '.'), 'Metode' => str($model->payment_method)->replace('_', ' ')->title(), 'Rekening Tujuan' => $model->bankAccount?->nama_bank.' — '.$model->bankAccount?->nomor_rekening], 'detail_url' => route('admin.customer-receipts.preview', $model, absolute: false)];
        }
        if ($model instanceof HousingReservation && $request->module_key === 'housing-reservation') {
            $model->loadMissing(['customer', 'unit.perumahan', 'fundBank', 'pettyCashAccount']);
            $destination = $model->payment_channel === 'cash'
                ? trim(($model->pettyCashAccount?->code ?? '').' - '.($model->pettyCashAccount?->name ?? ''), ' -')
                : trim(($model->fundBank?->nama_bank ?? '').' - '.($model->fundBank?->nomor_rekening ?? ''), ' -');

            $requiresFinanceVerification = $request->status === ApprovalRequest::STATUS_PENDING
                && $request->current_step === 1
                && $model->payment_method !== 'cash';

            return ['title' => $model->reservation_no.' - '.$model->customer?->nama, 'summary' => ['Invoice' => $model->invoice_no, 'Customer' => $model->customer?->nama, 'Perumahan' => $model->unit?->perumahan?->nama_perusahaan, 'Unit' => trim(($model->unit?->kode_nlok ?? '').'/'.($model->unit?->nomor_rumah ?? '')), 'Tanggal Dana Diterima' => $model->payment_submitted_at?->format('d/m/Y'), 'Booking Fee' => 'Rp '.number_format((float) $model->booking_fee, 0, ',', '.'), 'Cara Penerimaan' => $model->payment_channel === 'cash' ? 'Tunai' : 'Transfer Bank', 'Pengirim/Penyetor' => $model->payment_sender_name, 'Tujuan Dana' => $destination], 'detail_url' => $requiresFinanceVerification ? route('admin.customer-receipts.reservation-review', $model, absolute: false) : route('admin.marketing.reservations.show', $model, absolute: false)];
        }
        if ($model instanceof CashInstallmentContract) {
            $model->loadMissing('salesTransaction.customer');

            return ['title' => $model->contract_no.' — '.$model->salesTransaction?->customer?->nama, 'summary' => ['Transaksi' => $model->salesTransaction?->transaction_no, 'Customer' => $model->salesTransaction?->customer?->nama, 'Nilai Kontrak' => 'Rp '.number_format((float) $model->contract_value, 0, ',', '.'), 'Tanggal Mulai' => $model->start_date?->format('d/m/Y'), 'Status Kontrak' => $model->status], 'detail_url' => route('admin.integrated-sales.show', ['contracts', $model->id], absolute: false)];
        }
        if ($model instanceof DeveloperKprApplication) {
            $model->loadMissing('salesTransaction.customer');

            return ['title' => $model->application_no.' — '.$model->salesTransaction?->customer?->nama, 'summary' => ['Transaksi' => $model->salesTransaction?->transaction_no, 'Customer' => $model->salesTransaction?->customer?->nama, 'Nilai Pembiayaan' => 'Rp '.number_format((float) $model->financing_amount, 0, ',', '.'), 'Tenor' => $model->tenor_months.' bulan', 'Estimasi Angsuran' => 'Rp '.number_format((float) $model->estimated_installment, 0, ',', '.'), 'Status' => $model->status], 'detail_url' => route('admin.integrated-sales.show', ['developer-applications', $model->id], absolute: false)];
        }
        if ($model instanceof CashSale) {
            $model->loadMissing(['spr.costumer', 'spr.detailRumah.perumahan']);

            return ['title' => $model->kode_cash.' — '.$model->spr?->costumer?->nama, 'summary' => ['SPR' => $model->spr?->kode_spr, 'Customer' => $model->spr?->costumer?->nama, 'Perumahan' => $model->spr?->detailRumah?->perumahan?->nama_perusahaan, 'Unit' => $model->spr?->detailRumah?->nomor_rumah, 'Harga Rumah' => 'Rp '.number_format((float) $model->harga_rumah, 0, ',', '.'), 'Sisa Tagihan' => 'Rp '.number_format((float) $model->sisa_tagihan, 0, ',', '.')], 'detail_url' => $model->spr_id ? route('admin.marketing.spr.show', $model->spr_id, absolute: false) : null];
        }
        if ($model instanceof SalesProcessStep) {
            $model->loadMissing(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit']);

            return [
                'title' => $model->label.' — '.$model->salesTransaction?->transaction_no,
                'summary' => [
                    'Transaksi' => $model->salesTransaction?->transaction_no,
                    'Customer' => $model->salesTransaction?->customer?->nama,
                    'Perumahan' => $model->salesTransaction?->housingProject?->nama_perusahaan,
                    'Unit' => $model->salesTransaction?->housingUnit?->nomor_rumah,
                    'Tahap' => $model->label,
                    'Status' => $model->status,
                    'Tanggal Aktual' => optional($model->actual_date)->format('d/m/Y'),
                ],
                'detail_url' => route('admin.sales-process.workspace', $model, absolute: false),
            ];
        }
        if ($model instanceof BankKprFinancing) {
            $model->loadMissing('submission.spr.costumer');

            return ['title' => $model->submission?->kode_kpr.' — '.$model->submission?->spr?->costumer?->nama, 'summary' => ['Harga Jual' => 'Rp '.number_format((float) $model->sale_price, 0, ',', '.'), 'Plafon Bank' => 'Rp '.number_format((float) $model->approved_limit, 0, ',', '.'), 'DP' => 'Rp '.number_format((float) $model->down_payment, 0, ',', '.'), 'Kekurangan' => 'Rp '.number_format((float) $model->shortfall, 0, ',', '.'), 'Nomor SP3K' => $model->sp3k_number, 'Tenor' => $model->tenor_months.' bulan'], 'detail_url' => route('admin.kpr.show', $model->kpr_submission_id, absolute: false)];
        }
        if ($model instanceof BankKprDisbursement) {
            $model->loadMissing('submission.spr.costumer');

            return ['title' => $model->disbursement_no.' — '.$model->submission?->spr?->costumer?->nama, 'summary' => ['KPR' => $model->submission?->kode_kpr, 'Tanggal' => $model->disbursement_date?->format('d/m/Y'), 'Nominal' => 'Rp '.number_format((float) $model->amount, 0, ',', '.'), 'Referensi Bank' => $model->bank_reference, 'Status' => $model->status], 'detail_url' => route('admin.kpr.show', $model->kpr_submission_id, absolute: false)];
        }

        return ['title' => $request->module_label, 'summary' => collect($request->after_data ?? [])->except(['id', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by'])->mapWithKeys(fn ($v, $k) => [str($k)->replace('_', ' ')->title()->toString() => is_array($v) ? implode(', ', $v) : (string) $v])->take(8)->all(), 'detail_url' => null];
    }

    private function approvalModel(ApprovalRequest $request): ?Model
    {
        if (! $request->model_type || ! $request->model_id || ! class_exists($request->model_type)) {
            return null;
        }

return $request->model_type::query()->find($request->model_id);
    }

    private function sprDisplay(Spr $spr): array
    {
        return ['title' => $spr->kode_spr.' — '.$spr->costumer?->nama, 'summary' => ['Customer' => $spr->costumer?->nama, 'No. Identitas' => $spr->costumer?->no_identitas, 'Perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan, 'Unit' => trim(($spr->detailRumah?->kode_nlok ?? '').' '.($spr->detailRumah?->nomor_rumah ?? '')), 'Metode Pembayaran' => str($spr->metode_pembayaran)->replace('_', ' ')->title(), 'Harga Jual' => 'Rp '.number_format((float) $spr->harga_jual, 0, ',', '.'), 'Booking Fee' => 'Rp '.number_format((float) $spr->booking_fee, 0, ',', '.'), 'Uang Muka' => 'Rp '.number_format((float) $spr->uang_muka, 0, ',', '.'), 'Marketing' => $spr->creator?->name], 'detail_url' => route('admin.marketing.spr.show', $spr->id, absolute: false)];
    }

    public function approve(ApprovalRequest $approvalRequest, ApprovalWorkflowService $service): RedirectResponse
    {
        $reservation = $approvalRequest->module_key === 'housing-reservation'
            ? HousingReservation::query()->find($approvalRequest->model_id)
            : null;
        if ($reservation
            && $approvalRequest->status === ApprovalRequest::STATUS_PENDING
            && $approvalRequest->current_step === 1
            && $reservation->payment_method !== 'cash') {
            return to_route('admin.customer-receipts.reservation-review', $approvalRequest->model_id)
                ->with('warning', 'Verifikasi bukti dan lokasi penerimaan dana sebelum menyetujui Booking Fee.');
        }

        $service->approve($approvalRequest);

        return back()->with('success', 'Request berhasil disetujui.');
    }

    public function reject(RejectApprovalRequest $request, ApprovalRequest $approvalRequest, ApprovalWorkflowService $service): RedirectResponse
    {
        $service->reject($approvalRequest, $request->validated('rejection_note'));

        return back()->with('success', 'Request berhasil ditolak.');
    }
}
