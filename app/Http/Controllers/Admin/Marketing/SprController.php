<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marketing\SaveSprRequest;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\BankBranch;
use App\Models\BankCreditProduct;
use App\Models\BankHousingPartnership;
use App\Models\BankKredit;
use App\Models\CashInstallmentScheme;
use App\Models\CashSale;
use App\Models\HousingReservation;
use App\Models\Costumer;
use App\Models\CustomerDocument;
use App\Models\CustomerReceipt;
use App\Models\DetailRumah;
use App\Models\DeveloperKprProduct;
use App\Models\DokumenCostumer;
use App\Models\KprSubmission;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\Spr;
use App\Models\SprBerkasCostumer;
use App\Services\ApprovalWorkflowService;
use App\Services\FixedSalesDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class SprController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    protected array $activeSprStatuses = [
        Spr::STATUS_MENUNGGU_APPROVAL,
        Spr::STATUS_MENUNGGU_MANAGER,
        Spr::STATUS_MENUNGGU_OWNER,
        Spr::STATUS_DISETUJUI,
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $paymentMethod = $request->query('payment_method');
        $perumahanId = $request->query('perumahan_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan',
                'bankKredit:id,nama_bank,bunga_tahunan,tenor_min_bulan,tenor_max_bulan,minimal_dp_persen,biaya_provisi_persen,biaya_admin',
                'creator:id,name',
                'updater:id,name',
                'berkasCostumers.dokumen:id,kode_dokumen,nama_dokumen',
                'approvalRequests' => fn ($query) => $query->where('action', 'lock')->latest('id'),
            ])
            ->tap(fn (Builder $query) => $this->scopeSprVisibility($query, $request))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($paymentMethod, fn (Builder $query) => $query->where('metode_pembayaran', $paymentMethod))
            ->when($perumahanId, fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $perumahanId)))
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('tanggal_spr', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('tanggal_spr', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('kode_spr', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('costumer', fn (Builder $query) => $query
                            ->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_identitas', 'like', "%{$search}%"));
                });
            });

        $analyticsQuery = clone $query;
        $statusChart = (clone $analyticsQuery)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($total, $key) => ['key' => $key, 'label' => $this->labelFromOptions($key, $this->statusOptions()), 'value' => (int) $total])->values();
        $methodChart = (clone $analyticsQuery)->selectRaw('metode_pembayaran, COUNT(*) as total')->groupBy('metode_pembayaran')->pluck('total', 'metode_pembayaran')->map(fn ($total, $key) => ['key' => $key, 'label' => $this->labelFromOptions($key, $this->paymentOptions()), 'value' => (int) $total])->values();
        $sprIds = (clone $analyticsQuery)->select('sprs.id');
        $scheduleQuery = PaymentSchedule::query()->where('record_status', 'locked')->whereHas('salesTransaction', fn (Builder $query) => $query->whereIn('spr_id', clone $sprIds));
        $receiptQuery = CustomerReceipt::query()->where('status', 'posted')->whereHas('salesTransaction', fn (Builder $query) => $query->whereIn('spr_id', clone $sprIds));
        $remainingExpression = 'CASE WHEN amount > paid_amount THEN amount - paid_amount ELSE 0 END';
        $financial = [
            'sales_value' => (float) (clone $analyticsQuery)->sum('harga_jual'),
            'booking_fee' => (float) (clone $analyticsQuery)->sum('booking_fee'),
            'down_payment' => (float) (clone $analyticsQuery)->sum('uang_muka'),
            'financing_value' => (float) (clone $analyticsQuery)->sum('nilai_pengajuan_kpr'),
            'invoiced' => (float) (clone $scheduleQuery)->sum('amount'),
            'received' => (float) (clone $receiptQuery)->sum('amount'),
            'receivable' => (float) (clone $scheduleQuery)->selectRaw("SUM({$remainingExpression}) total")->value('total'),
        ];

        $rows = $query->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Spr $spr) => $this->row($spr));

        return Inertia::render('Admin/Marketing/Spr/Index', [
            'title' => 'SPR',
            'description' => 'Buat Surat Pemesanan Rumah dan proses sesuai tahapan pada Setting Approval.',
            'baseUrl' => route('admin.marketing.spr.index', absolute: false),
            'rows' => $rows,
            'analytics' => ['total' => (clone $analyticsQuery)->count(), 'status' => $statusChart, 'methods' => $methodChart, 'financial' => $financial],
            'filters' => compact('search', 'status', 'paymentMethod', 'perumahanId', 'dateFrom', 'dateTo'),
            'filterOptions' => ['housing' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]), 'statuses' => $this->statusOptions(), 'paymentMethods' => $this->paymentOptions()],
            'customers' => $this->customerOptions(),
            'units' => $this->unitOptions(),
            'bankKreditOptions' => $this->bankKreditOptions(),
            'dokumenOptions' => $this->dokumenOptions(),
            'repositoryDocuments' => CustomerDocument::query()->with('documentType:id,kode_dokumen,nama_dokumen')->where('status', 'active')->orderByDesc('id')->get()->map(fn ($doc) => ['id' => (string) $doc->id, 'customer_id' => (string) $doc->costumer_id, 'document_type_id' => (string) $doc->dokumen_costumer_id, 'label' => $doc->documentType?->nama_dokumen ?: $doc->label, 'file_name' => $doc->nama_file, 'path' => $doc->path_file, 'party_scope' => $doc->party_scope, 'version' => $doc->version])->values()->all(),
            'options' => [
                'paymentOptions' => $this->paymentOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
            'permissions' => [],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $spr = Spr::query()->with(['costumer', 'detailRumah.perumahan', 'bankKredit', 'bankBranch', 'bankCreditProduct', 'cashInstallmentScheme', 'developerKprProduct', 'creator', 'berkasCostumers.dokumen', 'approvalRequests' => fn ($q) => $q->where('action', 'lock')->latest('id')])->tap(fn (Builder $q) => $this->scopeSprVisibility($q, $request))->when($this->shouldScopeToActivePerumahan($request), fn (Builder $q) => $q->whereHas('detailRumah', fn (Builder $q) => $this->scopeToActivePerumahan($q, $request)))->findOrFail($id);
        $row = $this->row($spr);
        $row['detail'] = [
            'customer_phone' => $spr->costumer?->telepon, 'customer_email' => $spr->costumer?->email, 'customer_address' => $spr->costumer?->alamat, 'customer_job' => $spr->costumer?->pekerjaan,
            'unit_type' => $spr->detailRumah?->tipe_rumah, 'land_area' => $spr->detailRumah?->luas_tanah, 'building_area' => $spr->detailRumah?->luas_bangunan, 'housing_address' => $spr->detailRumah?->perumahan?->alamat,
            'bank_branch' => $spr->bankBranch?->branch_name, 'bank_product' => $spr->bankCreditProduct?->product_name, 'cash_scheme' => $spr->cashInstallmentScheme?->name, 'developer_product' => $spr->developerKprProduct?->name,
        ];

        return Inertia::render('Admin/Marketing/Spr/Show', ['title' => 'Detail SPR '.$spr->kode_spr, 'baseUrl' => route('admin.marketing.spr.index', absolute: false), 'row' => $row, 'documentTemplates' => collect(app(FixedSalesDocumentService::class)->catalog())->where('document_type', 'spr')->values()]);
    }

    public function preview(Request $request, string $id)
    {
        return $this->printable($request, $id, false);
    }

    public function print(Request $request, string $id)
    {
        return $this->printable($request, $id, true);
    }

    private function printable(Request $request, string $id, bool $autoPrint)
    {
        $spr = Spr::query()->with(['costumer', 'detailRumah.perumahan', 'bankKredit', 'bankBranch', 'bankCreditProduct', 'cashInstallmentScheme', 'developerKprProduct', 'creator', 'berkasCostumers.dokumen', 'salesTransaction.paymentSchedules', 'salesTransaction.customerReceipts', 'approvalRequests' => fn ($q) => $q->where('action', 'lock')->latest('id')])->tap(fn (Builder $q) => $this->scopeSprVisibility($q, $request))->findOrFail($id);
        $row = $this->row($spr);
        $record = ['heading' => $spr->kode_spr, 'subtitle' => ($spr->costumer?->nama ?? '-').' — '.trim(($spr->detailRumah?->kode_nlok ?? '').' '.($spr->detailRumah?->nomor_rumah ?? '')), 'summary' => [
            'Tanggal SPR' => optional($spr->tanggal_spr)->format('d/m/Y'), 'Customer' => $spr->costumer?->nama, 'NIK' => $spr->costumer?->no_identitas, 'Telepon' => $spr->costumer?->telepon,
            'Perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan, 'Unit' => trim(($spr->detailRumah?->kode_nlok ?? '').' / '.($spr->detailRumah?->nomor_rumah ?? '')), 'Tipe Rumah' => $spr->detailRumah?->tipe_rumah,
            'Metode Pembayaran' => $row['metode_pembayaran'], 'Harga Jual' => 'Rp '.number_format((float) $spr->harga_jual, 0, ',', '.'), 'Total Penambahan' => 'Rp '.number_format((float) $row['total_penambahan'], 0, ',', '.'),
            'Booking Fee' => 'Rp '.number_format((float) $spr->booking_fee, 0, ',', '.'), 'Uang Muka' => 'Rp '.number_format((float) $spr->uang_muka, 0, ',', '.'), 'Bank / Produk' => trim(($spr->bankKredit?->nama_bank ?? '').' / '.($spr->bankCreditProduct?->product_name ?? ''), ' /'),
            'Status SPR' => $row['business_status_label'], 'Status Approval' => $row['status_label'], 'Marketing' => $spr->creator?->name,
        ], 'schedules' => $spr->salesTransaction?->paymentSchedules?->map(fn ($item) => ['description' => $item->description, 'due_date' => optional($item->due_date)->format('d/m/Y'), 'amount' => 'Rp '.number_format((float) $item->amount, 0, ',', '.'), 'paid' => 'Rp '.number_format((float) $item->paid_amount, 0, ',', '.'), 'status' => str($item->status)->replace('_', ' ')->title()])->all() ?? [], 'payments' => $spr->salesTransaction?->customerReceipts?->map(fn ($item) => ['label' => $item->receipt_no, 'date' => optional($item->payment_date)->format('d/m/Y'), 'value' => 'Rp '.number_format((float) $item->amount, 0, ',', '.'), 'status' => str($item->status)->title()])->all() ?? [], 'documents' => $spr->berkasCostumers->map(fn ($item) => ['label' => $item->dokumen?->nama_dokumen ?? $item->nama_file, 'file' => $item->nama_file, 'notes' => $item->keterangan])->all()];

        return view('reports.sales-erp-detail', ['title' => 'Ringkasan ERP SPR', 'record' => $record, 'autoPrint' => $autoPrint]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('booking.create') || $request->user()?->can('booking.manage'), 403, 'Anda tidak memiliki permission untuk membuat SPR.');

        return Inertia::render('Admin/Marketing/Spr/Form', [
            ...$this->formPayload(),
            'mode' => 'create',
            'title' => 'Buat SPR',
            'description' => 'Lengkapi data customer, unit rumah, penambahan, termin, dan berkas customer dalam satu halaman.',
            'submitUrl' => route('admin.marketing.spr.store', absolute: false),
            'method' => 'post',
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        abort_unless($request->user()?->can('booking.update') || $request->user()?->can('booking.manage'), 403, 'Anda tidak memiliki permission untuk mengubah SPR.');

        $spr = Spr::query()
            ->with([
                'costumer:id,nama,no_identitas,telepon',
                'detailRumah.perumahan:id,nama_perusahaan',
                'bankKredit:id,nama_bank,bunga_tahunan,tenor_min_bulan,tenor_max_bulan,minimal_dp_persen,biaya_provisi_persen,biaya_admin',
                'creator:id,name',
                'berkasCostumers.dokumen:id,kode_dokumen,nama_dokumen',
            ])
            ->where('created_by', $request->user()?->id)
            ->where(fn (Builder $query) => $query->whereNull('record_status')->orWhere('record_status', '!=', 'locked'))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->findOrFail($id);

        return Inertia::render('Admin/Marketing/Spr/Form', [
            ...$this->formPayload($spr),
            'mode' => 'edit',
            'title' => 'Edit SPR '.$spr->kode_spr,
            'description' => 'Perbarui data SPR, penambahan biaya, termin, dan berkas customer yang terkait.',
            'submitUrl' => route('admin.marketing.spr.update', $spr->id, absolute: false),
            'method' => 'put',
        ]);
    }

    public function store(SaveSprRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('booking.create') || $request->user()?->can('booking.manage'), 403, 'Anda tidak memiliki permission untuk membuat SPR.');

        $validated = $request->validated();

        $this->validateTerminRules($validated);
        $this->validateKprBankRules($validated);
        $this->validateRequiredDocuments($validated);

        DB::transaction(function () use ($request, $validated) {
            $reservation = $this->approvedReservationForCustomer((int) $validated['costumer_id'], lock: true);
            if ($reservation) {
                $validated['housing_reservation_id'] = $reservation->id;
                $validated['costumer_id'] = $reservation->costumer_id;
                $validated['detail_rumah_id'] = $reservation->detail_rumah_id;
                $validated['metode_pembayaran'] = $reservation->payment_method;
                $validated['booking_fee'] = $reservation->booking_fee;
                $validated['tanggal_pembayaran_booking_fee'] = $reservation->paid_at?->toDateString();
                $validated = $this->applyReservationPaymentMaster($validated, $reservation);
            } else {
                $validated['housing_reservation_id'] = null;
            }
            $this->ensureCustomerCanBeUsed($request, (int) $validated['costumer_id']);
            $this->ensureUnitBelongsToActivePerumahan($request, (int) $validated['detail_rumah_id']);
            if (! $reservation) $this->ensureUnitIsAvailable((int) $validated['detail_rumah_id']);
            $payload = $this->normalizeSprPayload($validated);

            $spr = Spr::create([
                ...$payload,
                'kode_spr' => $this->nextSprCode(),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
                'status' => Spr::STATUS_DRAFT,
                'record_status' => 'draft',
            ]);

            $this->storeBerkas($request, $spr, $validated['berkas'] ?? []);
            if ($reservation) app(\App\Services\HousingReservationService::class)->linkToSpr($reservation, $spr);
        });

        return to_route('admin.marketing.spr.index')->with('success', 'Draft SPR berhasil disimpan. Periksa kembali data lalu klik Lock untuk mengajukan approval.');
    }

    public function update(SaveSprRequest $request, string $id): RedirectResponse
    {
        abort_unless($request->user()?->can('booking.update') || $request->user()?->can('booking.manage'), 403, 'Anda tidak memiliki permission untuk mengubah SPR.');

        $validated = $request->validated();

        $this->validateTerminRules($validated);
        $this->validateKprBankRules($validated);

        $spr = Spr::query()
            ->where('created_by', $request->user()?->id)
            ->findOrFail($id);
        $this->validateRequiredDocuments($validated, $spr);
        abort_if(($spr->record_status ?? 'draft') === 'locked', 403, 'SPR sudah di-lock dan tidak dapat diedit. Manager atau Owner harus melakukan Unlock terlebih dahulu.');
        if (! in_array($spr->status, [Spr::STATUS_DRAFT, Spr::STATUS_DITOLAK], true)) {
            throw ValidationException::withMessages([
                'status' => 'SPR yang sudah disetujui atau ditolak tidak bisa diubah.',
            ]);
        }
        DB::transaction(function () use ($request, $spr, $validated) {
            $reservation = $spr->housing_reservation_id
                ? HousingReservation::query()->lockForUpdate()->find($spr->housing_reservation_id)
                : $this->approvedReservationForCustomer((int) $validated['costumer_id'], lock: true);
            if ($reservation) {
                $validated['housing_reservation_id'] = $reservation->id;
                $validated['costumer_id'] = $reservation->costumer_id;
                $validated['detail_rumah_id'] = $reservation->detail_rumah_id;
                $validated['metode_pembayaran'] = $reservation->payment_method;
                $validated['booking_fee'] = $reservation->booking_fee;
                $validated['tanggal_pembayaran_booking_fee'] = $reservation->paid_at?->toDateString();
                $validated = $this->applyReservationPaymentMaster($validated, $reservation);
            } else {
                $validated['housing_reservation_id'] = null;
            }
            $this->ensureCustomerCanBeUsed($request, (int) $validated['costumer_id']);
            $this->ensureUnitBelongsToActivePerumahan($request, (int) $validated['detail_rumah_id']);
            if (! $reservation) {
                $this->ensureUnitIsAvailable((int) $validated['detail_rumah_id'], $spr->id);
            }
            $payload = $this->normalizeSprPayload($validated);

            $spr->update([
                ...$payload,
                'updated_by' => $request->user()?->id,
            ]);

            if (array_key_exists('berkas', $validated)) {
                $this->updateBerkas($request, $spr, $validated['berkas'] ?? []);
            }
            if ($reservation && ! $reservation->spr_id) {
                app(\App\Services\HousingReservationService::class)->linkToSpr($reservation, $spr);
            }
        });

        return back()->with('success', 'SPR berhasil diperbarui.');
    }

    public function lock(string $id): RedirectResponse
    {
        $request = request();
        abort_unless($request->user()?->can('booking.update') || $request->user()?->can('booking.manage'), 403, 'Anda tidak memiliki permission untuk mengajukan SPR.');

        $spr = Spr::query()
            ->with(['berkasCostumers', 'detailRumah'])
            ->where('created_by', $request->user()?->id)
            ->where(fn (Builder $query) => $query->whereNull('record_status')->orWhere('record_status', '!=', 'locked'))
            ->findOrFail($id);
        $payload = $spr->toArray();

        $this->validateTerminRules($payload);
        $this->validateKprBankRules($payload);
        $this->validateRequiredDocuments($payload, $spr);
        $this->ensureCustomerCanBeUsed($request, (int) $spr->costumer_id);
        $this->ensureUnitBelongsToActivePerumahan($request, (int) $spr->detail_rumah_id);

        $approval = DB::transaction(function () use ($request, $spr, $payload) {
            DetailRumah::query()->whereKey($spr->detail_rumah_id)->lockForUpdate()->firstOrFail();
            $this->ensureUnitIsAvailable((int) $spr->detail_rumah_id, $spr->id);
            $normalized = $this->normalizeSprPayload($payload);
            $spr->update($normalized);
            $spr->forceFill([
                'status' => Spr::STATUS_MENUNGGU_APPROVAL,
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ])->save();

            return app(ApprovalWorkflowService::class)->submitLocked($spr, 'spr');
        });

        return back()->with('success', $approval->status === ApprovalRequest::STATUS_APPROVED
            ? 'SPR berhasil di-lock dan disetujui otomatis sesuai Setting Approval.'
            : "SPR berhasil di-lock dan masuk approval tahap {$approval->current_step} dari {$approval->total_steps}.");
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->currentUserCanUnlockSpr(), 403, 'Hanya Manager atau Owner yang dapat membuka lock SPR.');

        $spr = Spr::query()->where('record_status', 'locked')->findOrFail($id);
        $hasPendingApproval = ApprovalRequest::query()->where([
            'module_key' => 'spr',
            'action' => 'lock',
            'model_type' => Spr::class,
            'model_id' => $spr->id,
            'status' => ApprovalRequest::STATUS_PENDING,
        ])->exists();
        if (! $hasPendingApproval) {
            throw ValidationException::withMessages([
                'approval' => 'SPR yang approval-nya sudah final tidak dapat di-Unlock karena transaksi turunannya telah dibentuk.',
            ]);
        }
        app(ApprovalWorkflowService::class)->cancelPendingLock($spr);
        $spr->forceFill([
            'status' => Spr::STATUS_DRAFT,
            'record_status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
            'updated_by' => auth()->id(),
        ])->save();

        return back()->with('success', 'Lock SPR berhasil dibuka. Draft hanya dapat dilihat dan diperbaiki kembali oleh pembuatnya.');
    }

    public function approve(Request $request, string $id): RedirectResponse
    {
        $spr = Spr::query()->findOrFail($id);
        $approval = ApprovalRequest::query()->where(['module_key' => 'spr', 'action' => 'lock', 'model_type' => Spr::class, 'model_id' => $spr->id, 'status' => ApprovalRequest::STATUS_PENDING])->latest('id')->firstOrFail();
        $service = app(ApprovalWorkflowService::class);
        abort_unless($service->canReview($approval), 403, 'Role Anda tidak terdaftar pada tahap approval SPR ini.');
        $service->approve($approval);
        $approval->refresh();

        return back()->with('success', $approval->status === ApprovalRequest::STATUS_APPROVED
            ? 'Approval SPR selesai dan transaksi penjualan telah dibentuk.'
            : "Tahap approval SPR disetujui. Menunggu tahap {$approval->current_step} dari {$approval->total_steps}.");
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'catatan' => ['nullable', 'string'],
        ]);

        $spr = Spr::query()->findOrFail($id);
        $approval = ApprovalRequest::query()->where(['module_key' => 'spr', 'action' => 'lock', 'model_type' => Spr::class, 'model_id' => $spr->id, 'status' => ApprovalRequest::STATUS_PENDING])->latest('id')->firstOrFail();
        $service = app(ApprovalWorkflowService::class);
        abort_unless($service->canReview($approval), 403, 'Role Anda tidak terdaftar pada tahap approval SPR ini.');
        $service->reject($approval, $validated['catatan'] ?? null);

        return back()->with('success', 'SPR berhasil ditolak.');
    }

    private function approvedReservationForCustomer(int $customerId, bool $lock = false): ?HousingReservation
    {
        $query = HousingReservation::query()
            ->where('costumer_id', $customerId)
            ->whereNull('spr_id')
            ->where('payment_status', 'paid')
            ->where('payment_approval_status', 'approved')
            ->whereHas('latestApproval', fn ($query) => $query->where('status', ApprovalRequest::STATUS_APPROVED))
            ->latest('paid_at')
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function approvedReservationFromPayload(array $validated): ?HousingReservation
    {
        if (empty($validated['housing_reservation_id'])) {
            return null;
        }

        return HousingReservation::query()
            ->whereKey($validated['housing_reservation_id'])
            ->where('costumer_id', $validated['costumer_id'] ?? 0)
            ->where('payment_status', 'paid')
            ->where('payment_approval_status', 'approved')
            ->whereHas('latestApproval', fn ($query) => $query->where('status', ApprovalRequest::STATUS_APPROVED))
            ->first();
    }

    private function applyReservationPaymentMaster(array $validated, HousingReservation $reservation): array
    {
        if ($reservation->booking_fee_source_type === CashInstallmentScheme::class) {
            $validated['cash_installment_scheme_id'] = $reservation->booking_fee_source_id;
        }
        if ($reservation->booking_fee_source_type === DeveloperKprProduct::class) {
            $product = DeveloperKprProduct::query()->find($reservation->booking_fee_source_id);
            $validated['developer_kpr_product_id'] = $product?->id;
            $validated['kpr_tenor_bulan'] = ($validated['kpr_tenor_bulan'] ?? null) ?: $product?->maximum_tenor_months;
        }
        if ($reservation->booking_fee_source_type === BankCreditProduct::class) {
            $product = BankCreditProduct::query()->find($reservation->booking_fee_source_id);
            $validated['bank_credit_product_id'] = $product?->id;
            $validated['bank_kredit_id'] = $product?->bank_kredit_id;
            $validated['bank_branch_id'] = $product?->bank_branch_id;
            $validated['kpr_tenor_bulan'] = ($validated['kpr_tenor_bulan'] ?? null) ?: $product?->maximum_tenor_months;
            $validated['kpr_bunga_tahunan'] = $product?->indicative_interest_margin;
        }

        return $validated;
    }

    protected function formPayload(?Spr $spr = null): array
    {
        $row = $spr ? $this->row($spr) : [
            'costumer_id' => '',
            'housing_reservation_id' => '',
            'detail_rumah_id' => '',
            'tanggal_spr' => now()->toDateString(),
            'metode_key' => 'kpr_bank',
            'metode_pembayaran' => $this->labelFromOptions('kpr_bank', $this->paymentOptions()),
            'bank_kredit_id' => '',
            'bank_branch_id' => '',
            'cash_installment_scheme_id' => '',
            'developer_kpr_product_id' => '',
            'bank_kredit' => '-',
            'kpr_tenor_bulan' => '',
            'kpr_bunga_tahunan' => '',
            'harga_jual' => 0,
            'booking_fee' => 0,
            'booking_fee_includes_dp' => false,
            'tanggal_pembayaran_booking_fee' => '',
            'uang_muka' => 0,
            'uang_muka_jumlah_pembayaran' => '',
            'tanggal_jatuh_tempo_dp' => '',
            'nilai_pengajuan_kpr' => 0,
            'penambahan_tanah' => '',
            'harga_penambahan_tanah' => 0,
            'penambahan_lain_lain' => '',
            'harga_penambahan_lain_lain' => 0,
            'total_penambahan_tanah' => 0,
            'total_penambahan_lain_lain' => 0,
            'total_penambahan' => 0,
            'nilai_pengajuan_akhir' => 0,
            'jumlah_termin' => '',
            'nominal_termin' => '',
            'tanggal_jatuh_tempo_angsuran' => '',
            'status' => Spr::STATUS_DRAFT,
            'status_label' => $this->labelFromOptions(Spr::STATUS_DRAFT, $this->statusOptions()),
            'catatan' => '',
            'record_status' => 'draft',
            'record_status_label' => 'Draft',
            'berkas' => [],
        ];

        return [
            'title' => 'SPR',
            'description' => 'Buat Surat Pemesanan Rumah dan proses approval manajer serta owner sebelum masuk KPR.',
            'baseUrl' => route('admin.marketing.spr.index', absolute: false),
            'row' => $row,
            'customers' => $this->customerOptions($spr),
            'reservations' => HousingReservation::query()
                ->with(['customer:id,nama', 'unit.perumahan:id,nama_perusahaan'])
                ->where('payment_status', 'paid')
                ->where('payment_approval_status', 'approved')
                ->whereHas('latestApproval', fn ($query) => $query->where('status', ApprovalRequest::STATUS_APPROVED))
                ->where(function ($query) use ($spr) {
                    $query->whereNull('spr_id');
                    if ($spr?->housing_reservation_id) {
                        $query->orWhereKey($spr->housing_reservation_id);
                    }
                })
                ->latest('paid_at')
                ->get()
                ->unique('costumer_id')
                ->map(function ($row) {
                    $bankProduct = $row->booking_fee_source_type === BankCreditProduct::class
                        ? BankCreditProduct::query()->find($row->booking_fee_source_id)
                        : null;
                    $developerProduct = $row->booking_fee_source_type === DeveloperKprProduct::class
                        ? DeveloperKprProduct::query()->find($row->booking_fee_source_id)
                        : null;
                    $cashScheme = $row->booking_fee_source_type === CashInstallmentScheme::class
                        ? CashInstallmentScheme::query()->find($row->booking_fee_source_id)
                        : null;

                    return [
                        'id' => $row->id,
                        'label' => $row->reservation_no.' — '.$row->customer?->nama.' — '.$row->unit?->perumahan?->nama_perusahaan.' '.$row->unit?->kode_nlok.'/'.$row->unit?->nomor_rumah,
                        'costumer_id' => $row->costumer_id,
                        'detail_rumah_id' => $row->detail_rumah_id,
                        'payment_method' => $row->payment_method,
                        'booking_fee' => (float) $row->booking_fee,
                        'paid_at' => $row->paid_at?->format('Y-m-d'),
                        'cash_installment_scheme_id' => $cashScheme?->id,
                        'developer_kpr_product_id' => $developerProduct?->id,
                        'bank_credit_product_id' => $bankProduct?->id,
                        'bank_kredit_id' => $bankProduct?->bank_kredit_id,
                        'bank_branch_id' => $bankProduct?->bank_branch_id,
                        'kpr_tenor_bulan' => $bankProduct?->maximum_tenor_months ?? $developerProduct?->maximum_tenor_months,
                        'kpr_bunga_tahunan' => $bankProduct?->indicative_interest_margin ?? $developerProduct?->annual_margin,
                    ];
                })->values(),
            'units' => $this->unitOptions($spr),
            'bankKreditOptions' => $this->bankKreditOptions(),
            'bankBranchOptions' => $this->bankBranchOptions(),
            'bankCreditProductOptions' => $this->bankCreditProductOptions(),
            'cashInstallmentSchemeOptions' => $this->cashInstallmentSchemeOptions(),
            'developerKprProductOptions' => $this->developerKprProductOptions(),
            'dokumenOptions' => $this->dokumenOptions(),
            'options' => [
                'paymentOptions' => $this->paymentOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
            'permissions' => [],
        ];
    }

    protected function row(Spr $spr): array
    {
        $approval = ($spr->record_status ?? 'draft') === 'locked'
            ? ($spr->relationLoaded('approvalRequests') ? $spr->approvalRequests->first() : $spr->approvalRequests()->first())
            : null;
        $approvalLabel = match ($approval?->status) {
            ApprovalRequest::STATUS_PENDING => "Menunggu approval tahap {$approval->current_step}/{$approval->total_steps}",
            ApprovalRequest::STATUS_APPROVED => 'Disetujui melalui Setting Approval',
            ApprovalRequest::STATUS_REJECTED => 'Ditolak melalui Setting Approval',
            default => $this->labelFromOptions($spr->status, $this->statusOptions()),
        };
        $reviewerRoleIds = $approval?->status === ApprovalRequest::STATUS_PENDING
            ? app(ApprovalWorkflowService::class)->reviewerRoleIds($approval)
            : collect();
        $reviewerRoles = Role::query()->whereIn('id', $reviewerRoleIds)->orderBy('name')->pluck('name')->values();
        $unit = $spr->detailRumah
            ? trim(($spr->detailRumah->kode_nlok ?? '').' '.($spr->detailRumah->nomor_rumah ?? ''))
            : '-';
        $finalPrice = (float) ($spr->nilai_pengajuan_akhir ?: $spr->harga_jual);
        $cashBalance = max(0, $finalPrice - (float) $spr->booking_fee - (float) $spr->uang_muka);
        [$financingLabel, $financingValue] = match ($spr->metode_pembayaran) {
            'kpr_bank' => ['Pengajuan KPR Bank', (float) $spr->nilai_pengajuan_kpr],
            'kpr_developer' => ['Pembiayaan Developer', (float) $spr->nilai_pengajuan_kpr],
            'bertahap', 'cash_bertahap' => ['Nilai Dicicil', $cashBalance],
            default => ['Sisa Pembayaran', $cashBalance],
        };

        return [
            'id' => $spr->id,
            'housing_reservation_id' => $spr->housing_reservation_id ? (string) $spr->housing_reservation_id : '',
            'kode_spr' => $spr->kode_spr,
            'revision_no' => (int) $spr->revision_no,
            'revision_label' => $spr->revision_no > 0 ? 'Revisi '.$spr->revision_no : 'Awal',
            'revision_status' => $spr->revision_status,
            'costumer_id' => (string) $spr->costumer_id,
            'detail_rumah_id' => (string) $spr->detail_rumah_id,
            'tanggal_spr' => optional($spr->tanggal_spr)->format('Y-m-d'),
            'customer' => $spr->costumer?->nama ?? '-',
            'no_identitas' => $spr->costumer?->no_identitas ?? '-',
            'unit' => $unit,
            'perumahan' => $spr->detailRumah?->perumahan?->nama_perusahaan ?? '-',
            'metode_key' => $spr->metode_pembayaran,
            'metode_pembayaran' => $this->labelFromOptions($spr->metode_pembayaran, $this->paymentOptions()),
            'bank_kredit_id' => $spr->bank_kredit_id ? (string) $spr->bank_kredit_id : '',
            'bank_branch_id' => $spr->bank_branch_id ? (string) $spr->bank_branch_id : '',
            'bank_credit_product_id' => $spr->bank_credit_product_id ? (string) $spr->bank_credit_product_id : '',
            'cash_installment_scheme_id' => $spr->cash_installment_scheme_id ? (string) $spr->cash_installment_scheme_id : '',
            'developer_kpr_product_id' => $spr->developer_kpr_product_id ? (string) $spr->developer_kpr_product_id : '',
            'bank_kredit' => $spr->bankKredit?->nama_bank ?? '-',
            'kpr_tenor_bulan' => $spr->kpr_tenor_bulan,
            'kpr_bunga_tahunan' => $spr->kpr_bunga_tahunan,
            'harga_jual' => $spr->harga_jual,
            'booking_fee' => $spr->booking_fee,
            'booking_fee_includes_dp' => (bool) ($spr->booking_fee_includes_dp ?? false),
            'tanggal_pembayaran_booking_fee' => optional($spr->tanggal_pembayaran_booking_fee)->format('Y-m-d'),
            'uang_muka' => $spr->uang_muka,
            'uang_muka_jumlah_pembayaran' => $spr->uang_muka_jumlah_pembayaran,
            'tanggal_jatuh_tempo_dp' => optional($spr->tanggal_jatuh_tempo_dp)->format('Y-m-d'),
            'nilai_pengajuan_kpr' => $spr->nilai_pengajuan_kpr,
            'financing_label' => $financingLabel,
            'financing_value' => $financingValue,
            'penambahan_tanah' => $spr->penambahan_tanah,
            'harga_penambahan_tanah' => $spr->harga_penambahan_tanah,
            'penambahan_lain_lain' => $spr->penambahan_lain_lain,
            'harga_penambahan_lain_lain' => $spr->harga_penambahan_lain_lain,
            'total_penambahan_tanah' => $spr->total_penambahan_tanah,
            'total_penambahan_lain_lain' => $spr->total_penambahan_lain_lain,
            'total_penambahan' => $spr->total_penambahan,
            'nilai_pengajuan_akhir' => $spr->nilai_pengajuan_akhir,
            'jumlah_termin' => $spr->jumlah_termin,
            'nominal_termin' => $spr->nominal_termin,
            'tanggal_jatuh_tempo_angsuran' => optional($spr->tanggal_jatuh_tempo_angsuran)->format('Y-m-d'),
            'status' => $spr->status,
            'business_status_label' => $this->labelFromOptions($spr->status, $this->statusOptions()),
            'status_label' => $approvalLabel,
            'approval_status' => $approval?->status,
            'approval_current_step' => $approval?->current_step,
            'approval_total_steps' => $approval?->total_steps,
            'approval_reviewer_roles' => $reviewerRoles->all(),
            'approval_reviewer_label' => $reviewerRoles->isNotEmpty() ? $reviewerRoles->join(', ') : null,
            'can_review_approval' => $approval?->status === ApprovalRequest::STATUS_PENDING
                && app(ApprovalWorkflowService::class)->canReview($approval),
            'catatan' => $spr->catatan,
            'created_by' => $spr->creator?->name ?? '-',
            'updated_by' => $spr->updater?->name ?? $spr->creator?->name ?? '-',
            'created_at' => optional($spr->created_at)->format('d/m/Y H:i'),
            'updated_at' => optional($spr->updated_at)->format('d/m/Y H:i'),
            'record_status' => $spr->record_status ?? 'draft',
            'record_status_label' => ($spr->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
            'can_edit' => ($spr->record_status ?? 'draft') !== 'locked' && (int) $spr->created_by === (int) auth()->id(),
            'can_lock' => ($spr->record_status ?? 'draft') !== 'locked' && (int) $spr->created_by === (int) auth()->id(),
            'can_unlock' => $this->currentUserCanUnlockSpr()
                && ($spr->record_status ?? 'draft') === 'locked'
                && $approval?->status === ApprovalRequest::STATUS_PENDING,
            'berkas_count' => $spr->berkasCostumers()->count(),
            'berkas' => $spr->berkasCostumers->map(fn (SprBerkasCostumer $berkas) => [
                'id' => $berkas->id,
                'dokumen_costumer_id' => (string) $berkas->dokumen_costumer_id,
                'customer_document_id' => $berkas->customer_document_id ? (string) $berkas->customer_document_id : null,
                'dokumen_label' => $berkas->dokumen?->nama_dokumen
                    ? $berkas->dokumen->nama_dokumen.' ('.$berkas->dokumen->kode_dokumen.')'
                    : 'Dokumen',
                'nama_file' => $berkas->nama_file,
                'path_file' => $berkas->path_file,
                'keterangan' => $berkas->keterangan,
            ])->values(),
        ];
    }

    protected function dokumenOptions(): array
    {
        return DokumenCostumer::query()
            ->finalized()
            ->where('status', 'aktif')
            ->whereIn('kategori_pengajuan', ['spr', 'cash_bertahap', 'kpr_bank', 'kpr_developer'])
            ->orderBy('nama_dokumen')
            ->get(['id', 'kode_dokumen', 'nama_dokumen', 'kategori_pengajuan', 'wajib'])
            ->map(fn (DokumenCostumer $dokumen) => [
                'value' => (string) $dokumen->id,
                'label' => $dokumen->nama_dokumen.' ('.$dokumen->kode_dokumen.')',
                'category' => $dokumen->kategori_pengajuan,
                'required' => (bool) $dokumen->wajib,
                'search' => strtolower(trim($dokumen->nama_dokumen.' '.$dokumen->kode_dokumen.' '.$dokumen->kategori_pengajuan)),
            ])
            ->all();
    }

    protected function storeBerkas(Request $request, Spr $spr, array $berkasRows): void
    {
        foreach ($berkasRows as $row) {
            if (! ($row['selected'] ?? true)) {
                continue;
            }
            $repository = isset($row['customer_document_id']) ? CustomerDocument::where('costumer_id', $spr->costumer_id)->where('status', 'active')->find($row['customer_document_id']) : null;
            $file = $row['file_upload'] ?? null;
            if ($file) {
                $path = $file->store('customer-repository/'.$spr->costumer_id, 'public');
                $repository = CustomerDocument::create(['costumer_id' => $spr->costumer_id, 'dokumen_costumer_id' => $row['dokumen_costumer_id'], 'party_scope' => 'customer', 'nama_file' => $file->getClientOriginalName(), 'path_file' => $path, 'mime_type' => $file->getClientMimeType(), 'file_size' => $file->getSize(), 'keterangan' => $row['keterangan'] ?? null, 'uploaded_by' => $request->user()?->id]);
            }
            if (! $repository) {
                continue;
            }

            SprBerkasCostumer::create([
                'spr_id' => $spr->id,
                'dokumen_costumer_id' => $row['dokumen_costumer_id'],
                'customer_document_id' => $repository->id, 'is_selected' => true,
                'uploaded_by' => $request->user()?->id,
                'nama_file' => $repository->nama_file, 'path_file' => $repository->path_file, 'mime_type' => $repository->mime_type, 'file_size' => $repository->file_size,
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }
    }

    protected function updateBerkas(Request $request, Spr $spr, array $berkasRows): void
    {
        foreach ($berkasRows as $row) {
            if (! ($row['selected'] ?? true)) {
                continue;
            }
            $dokumenId = (int) ($row['dokumen_costumer_id'] ?? 0);
            if ($dokumenId <= 0) {
                continue;
            }

            $existing = $spr->berkasCostumers()->where('dokumen_costumer_id', $dokumenId)->first();
            $file = $row['file_upload'] ?? null;
            $repository = isset($row['customer_document_id']) ? CustomerDocument::where('costumer_id', $spr->costumer_id)->where('status', 'active')->find($row['customer_document_id']) : null;

            if ($file) {
                $path = $file->store('customer-repository/'.$spr->costumer_id, 'public');
                $repository = CustomerDocument::create(['costumer_id' => $spr->costumer_id, 'dokumen_costumer_id' => $dokumenId, 'party_scope' => 'customer', 'nama_file' => $file->getClientOriginalName(), 'path_file' => $path, 'mime_type' => $file->getClientMimeType(), 'file_size' => $file->getSize(), 'keterangan' => $row['keterangan'] ?? null, 'uploaded_by' => $request->user()?->id]);
            }
            if ($repository) {

                if ($existing) {
                    $existing->update([
                        'customer_document_id' => $repository->id, 'is_selected' => true, 'uploaded_by' => $request->user()?->id,
                        'nama_file' => $repository->nama_file, 'path_file' => $repository->path_file, 'mime_type' => $repository->mime_type, 'file_size' => $repository->file_size,
                        'keterangan' => $row['keterangan'] ?? null,
                    ]);
                } else {
                    SprBerkasCostumer::create([
                        'spr_id' => $spr->id,
                        'dokumen_costumer_id' => $dokumenId,
                        'customer_document_id' => $repository->id, 'is_selected' => true,
                        'uploaded_by' => $request->user()?->id,
                        'nama_file' => $repository->nama_file, 'path_file' => $repository->path_file, 'mime_type' => $repository->mime_type, 'file_size' => $repository->file_size,
                        'keterangan' => $row['keterangan'] ?? null,
                    ]);
                }

                continue;
            }

            if ($existing) {
                $existing->update([
                    'keterangan' => $row['keterangan'] ?? $existing->keterangan,
                ]);
            }
        }
    }

    protected function customerOptions(?Spr $currentSpr = null): array
    {
        return Costumer::query()
            ->finalized()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('created_by', request()->user()?->id))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->whereDoesntHave('sprs', function (Builder $query) use ($currentSpr): void {
                $query->where(function (Builder $query): void {
                    $query->whereIn('status', $this->activeSprStatuses)
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', '!=', Spr::STATUS_DITOLAK)
                                ->whereHas('salesTransaction.customerReceipts', fn (Builder $paymentQuery) => $paymentQuery->where('status', '!=', 'rejected'));
                        });
                })
                    ->when($currentSpr, fn (Builder $query) => $query->where('id', '!=', $currentSpr->id));
            })
            ->select(['id', 'perumahan_id', 'nama', 'no_identitas', 'telepon'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (Costumer $costumer) => [
                'id' => $costumer->id,
                'label' => $costumer->nama.' - '.($costumer->no_identitas ?? '-'),
                'search' => strtolower($costumer->nama.' '.$costumer->no_identitas.' '.$costumer->telepon),
                'nama' => $costumer->nama,
                'no_identitas' => $costumer->no_identitas,
                'telepon' => $costumer->telepon,
                'perumahan_id' => $costumer->perumahan_id ? (string) $costumer->perumahan_id : '',
            ])
            ->all();
    }

    protected function unitOptions(?Spr $currentSpr = null): array
    {
        return DetailRumah::query()
            ->where(function (Builder $query): void {
                $query->where('record_status', 'locked')
                    ->orWhereHas('housingReservations', function (Builder $query): void {
                        $query->where('payment_status', 'paid')
                            ->where('payment_approval_status', 'approved')
                            ->whereHas('latestApproval', fn (Builder $query) => $query->where('status', ApprovalRequest::STATUS_APPROVED));
                    });
            })
            ->with(['perumahan:id,nama_perusahaan'])
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->where('status', 'aktif')
            ->whereIn('status_penjualan', ['tersedia', 'available', 'booking'])
            ->whereDoesntHave('sprs', function (Builder $query) use ($currentSpr): void {
                $query->where(function (Builder $query): void {
                    $query->whereIn('status', $this->activeSprStatuses)
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', '!=', Spr::STATUS_DITOLAK)
                                ->whereHas('salesTransaction.customerReceipts', fn (Builder $paymentQuery) => $paymentQuery->where('status', '!=', 'rejected'));
                        });
                })
                    ->when($currentSpr, fn (Builder $query) => $query->where('id', '!=', $currentSpr->id));
            })
            ->withCount([
                'sprs as active_spr_count' => fn (Builder $query) => $query
                    ->whereIn('status', $this->activeSprStatuses)
                    ->when($currentSpr, fn (Builder $query) => $query->where('id', '!=', $currentSpr->id)),
                'sprs as paid_spr_count' => fn (Builder $query) => $query
                    ->whereHas('salesTransaction.customerReceipts', fn (Builder $paymentQuery) => $paymentQuery->where('status', '!=', 'rejected'))
                    ->when($currentSpr, fn (Builder $query) => $query->where('id', '!=', $currentSpr->id)),
            ])
            ->select(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah', 'luas_tanah', 'luas_bangunan', 'harga_jual', 'status_penjualan', 'status_pembangunan', 'status'])
            ->orderBy('kode_nlok')
            ->orderBy('nomor_rumah')
            ->limit(500)
            ->get()
            ->map(fn (DetailRumah $rumah) => [
                'id' => $rumah->id,
                'label' => trim(($rumah->kode_nlok ?? '').' '.($rumah->nomor_rumah ?? '')).' - '.($rumah->perumahan?->nama_perusahaan ?? '-'),
                'harga_jual' => (float) ($rumah->harga_jual ?? 0),
                'perumahan_id' => (string) $rumah->perumahan_id,
                'perumahan' => $rumah->perumahan?->nama_perusahaan,
                'kode_nlok' => $rumah->kode_nlok,
                'nomor_rumah' => $rumah->nomor_rumah,
                'tipe_rumah' => $rumah->tipe_rumah,
                'luas_tanah' => $rumah->luas_tanah,
                'luas_bangunan' => $rumah->luas_bangunan,
                'status_penjualan' => $rumah->status_penjualan ?? '-',
                'status_pembangunan' => $rumah->status_pembangunan ?? '-',
                'status' => $rumah->status,
                'is_available' => (int) ($rumah->active_spr_count ?? 0) === 0 && (int) ($rumah->paid_spr_count ?? 0) === 0,
                'availability_label' => (int) ($rumah->paid_spr_count ?? 0) > 0 ? 'Sudah Ada Pembayaran SPR' : ((int) ($rumah->active_spr_count ?? 0) === 0 ? 'Tersedia' : 'Sudah Ada SPR Aktif'),
                'search' => strtolower(trim(($rumah->kode_nlok ?? '').' '.($rumah->nomor_rumah ?? '').' '.($rumah->perumahan?->nama_perusahaan ?? '').' '.($rumah->status ?? '').' '.(((int) ($rumah->active_spr_count ?? 0) === 0 && (int) ($rumah->paid_spr_count ?? 0) === 0) ? 'tersedia' : 'spr aktif sudah bayar'))),
            ])
            ->all();
    }

    protected function bankKreditOptions(): array
    {
        $today = today();

        return BankKredit::query()->finalized()->where('status', 'aktif')
            ->whereHas('creditProducts', fn (Builder $q) => $q->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today)))
            ->whereHas('partnerships', fn (Builder $q) => $q->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn (Builder $q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today)))
            ->with(['partnerships' => fn ($q) => $q->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))])
            ->orderBy('nama_bank')
            ->get(['id', 'kode_bank', 'nama_bank'])
            ->map(fn (BankKredit $bank) => [
                'value' => (string) $bank->id,
                'label' => $bank->kode_bank.' — '.$bank->nama_bank,
                'perumahan_ids' => $bank->partnerships->pluck('perumahan_id')->map(fn ($id) => (string) $id)->unique()->values()->all(),
            ])
            ->values()
            ->all();
    }

    protected function bankCreditProductOptions(): array
    {
        $today = today();
        $partnerships = BankHousingPartnership::query()->finalized()->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))->get();

        return BankCreditProduct::query()->finalized()->with(['bank:id,kode_bank,nama_bank', 'branch:id,bank_kredit_id,branch_name'])->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))->orderBy('product_name')->get()
            ->map(fn (BankCreditProduct $product) => [
                'value' => (string) $product->id, 'label' => ($product->bank?->nama_bank ?? 'Bank').' — '.$product->product_name.' — Versi '.$product->current_version, 'bank_id' => (string) $product->bank_kredit_id, 'branch_id' => $product->bank_branch_id ? (string) $product->bank_branch_id : '',
                'perumahan_ids' => $partnerships->where('bank_kredit_id', $product->bank_kredit_id)->when($product->bank_branch_id, fn ($rows) => $rows->where('bank_branch_id', $product->bank_branch_id))->pluck('perumahan_id')->map(fn ($id) => (string) $id)->unique()->values()->all(),
                'maximum_tenor_months' => (int) $product->maximum_tenor_months, 'indicative_interest_margin' => (float) $product->indicative_interest_margin, 'minimum_down_payment' => (float) $product->minimum_down_payment, 'maximum_ceiling' => (float) $product->maximum_ceiling, 'provision_fee' => (float) $product->provision_fee, 'administration_fee' => (float) $product->administration_fee,
            ])->values()->all();
    }

    protected function bankBranchOptions(): array
    {
        $today = today();
        $partnerships = BankHousingPartnership::query()->finalized()->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))->get();
        $reservedProductIds = HousingReservation::query()
            ->where('booking_fee_source_type', BankCreditProduct::class)
            ->where('payment_status', 'paid')
            ->where('payment_approval_status', 'approved')
            ->whereHas('latestApproval', fn ($query) => $query->where('status', ApprovalRequest::STATUS_APPROVED))
            ->pluck('booking_fee_source_id');
        $reservedBranchIds = BankCreditProduct::query()
            ->whereKey($reservedProductIds)
            ->whereNotNull('bank_branch_id')
            ->pluck('bank_branch_id');

        return BankBranch::query()
            ->where(fn ($query) => $query->where('record_status', 'locked')->orWhereIn('id', $reservedBranchIds))
            ->with('bank:id,nama_bank')
            ->where('status', 'aktif')
            ->whereHas('products', fn ($q) => $q->finalized()->where('status', 'aktif')->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today)))
            ->orderBy('branch_name')
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => ($row->bank?->nama_bank ?? 'Bank').' — '.$row->branch_name, 'bank_id' => (string) $row->bank_kredit_id, 'perumahan_ids' => $partnerships->where('bank_branch_id', $row->id)->pluck('perumahan_id')->map(fn ($id) => (string) $id)->unique()->values()->all()])
            ->values()
            ->all();
    }

    protected function paymentOptions(): array
    {
        return [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'cash_bertahap', 'label' => 'Cash Bertahap'],
            ['value' => 'kpr_bank', 'label' => 'KPR Bank'],
            ['value' => 'kpr_developer', 'label' => 'KPR Developer'],
        ];
    }

    protected function cashInstallmentSchemeOptions(): array
    {
        return CashInstallmentScheme::query()->finalized()->with(['housings:id', 'steps'])->where('status', 'aktif')->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->orderBy('name')->get()->map(fn ($row) => [
            'value' => (string) $row->id, 'label' => $row->name.' — '.$row->installment_count.' Tahap', 'code' => $row->code, 'name' => $row->name, 'version' => $row->version,
            'perumahan_id' => $row->perumahan_id ? (string) $row->perumahan_id : '', 'perumahan_ids' => $row->housings->pluck('id')->push($row->perumahan_id)->filter()->unique()->map(fn ($id) => (string) $id)->values()->all(),
            'minimum_booking_fee' => (float) $row->minimum_booking_fee, 'booking_fee_deducts' => $row->booking_fee_deducts, 'minimum_dp' => (float) $row->minimum_dp, 'dp_type' => $row->dp_type,
            'payment_model' => $row->payment_model, 'installment_count' => (int) $row->installment_count, 'maximum_tenor_months' => (int) $row->maximum_tenor_months, 'grace_period_days' => (int) $row->grace_period_days,
            'schedule_config' => $row->schedule_config ?? [], 'penalty_method' => $row->penalty_method, 'penalty_value' => (float) $row->penalty_value, 'penalty_config' => $row->penalty_config ?? [],
            'handover_config' => $row->handover_config ?? [], 'requirements' => $row->requirements ?? [],
            'steps' => $row->steps->map(fn ($step) => ['name' => $step->name, 'sequence' => (int) $step->sequence, 'calculation_type' => $step->calculation_type, 'value' => (float) $step->value, 'due_offset_months' => (int) $step->due_offset_months])->values()->all(),
        ])->all();
    }

    protected function developerKprProductOptions(): array
    {
        return DeveloperKprProduct::query()->finalized()->with('housings:id')->where('status', 'aktif')->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->orderBy('name')->get()->map(fn ($row) => [
            'value' => (string) $row->id, 'label' => $row->name.' — Maks. '.$row->maximum_tenor_months.' Bulan', 'code' => $row->code, 'name' => $row->name, 'version' => $row->version,
            'perumahan_id' => $row->perumahan_id ? (string) $row->perumahan_id : '', 'perumahan_ids' => $row->housings->pluck('id')->push($row->perumahan_id)->filter()->unique()->map(fn ($id) => (string) $id)->values()->all(),
            'minimum_dp' => (float) $row->minimum_dp, 'dp_type' => $row->dp_type, 'financing_type' => $row->financing_type, 'maximum_financing' => (float) $row->maximum_financing, 'financing_basis' => $row->financing_basis,
            'tenor_mode' => $row->tenor_mode, 'minimum_tenor_months' => (int) $row->minimum_tenor_months, 'maximum_tenor_months' => (int) $row->maximum_tenor_months, 'tenor_increment' => (int) $row->tenor_increment, 'allowed_tenors' => $row->allowed_tenors ?? [],
            'margin_method' => $row->margin_method, 'margin_scope' => $row->margin_scope, 'annual_margin' => (float) $row->annual_margin, 'margin_tiers' => $row->margin_tiers ?? [], 'fees' => $row->fees ?? [],
            'schedule_config' => $row->schedule_config ?? [], 'penalty_method' => $row->penalty_method, 'penalty_value' => (float) $row->penalty_value, 'penalty_config' => $row->penalty_config ?? [],
            'minimum_income' => (float) $row->minimum_income, 'maximum_age' => (int) $row->maximum_age, 'eligibility_config' => $row->eligibility_config ?? [], 'handover_config' => $row->handover_config ?? [], 'requirements' => $row->requirements ?? [],
        ])->all();
    }

    protected function syncUnitBookingState(int $detailRumahId, ?int $bookingSprId = null): void
    {
        $unit = DetailRumah::query()->find($detailRumahId);

        if (! $unit) {
            return;
        }

        $approvedSpr = Spr::query()
            ->where('detail_rumah_id', $detailRumahId)
            ->where('status', Spr::STATUS_DISETUJUI)
            ->latest('id')
            ->first();

        if ($approvedSpr) {
            $unit->update([
                'status_penjualan' => 'booking',
                'booking_spr_id' => $bookingSprId ?? $approvedSpr->id,
                'booking_at' => $unit->booking_at ?? now(),
            ]);

            return;
        }

        if ($unit->status_penjualan === 'booking') {
            $unit->update([
                'status_penjualan' => 'tersedia',
                'booking_spr_id' => null,
                'booking_at' => null,
            ]);
        }
    }

    protected function syncUnitSaleState(int $detailRumahId, string $statusPenjualan): void
    {
        $unit = DetailRumah::query()->find($detailRumahId);

        if (! $unit) {
            return;
        }

        $unit->update([
            'status_penjualan' => $statusPenjualan,
        ]);
    }

    protected function validateTerminRules(array $validated): void
    {
        if (! in_array(($validated['metode_pembayaran'] ?? null), ['bertahap', 'cash_bertahap'], true)) {
            return;
        }

        if (empty($validated['tanggal_jatuh_tempo_angsuran'])) {
            throw ValidationException::withMessages([
                'tanggal_jatuh_tempo_angsuran' => 'Metode Bertahap wajib memiliki tanggal jatuh tempo angsuran.',
            ]);
        }

    }

    protected function validateRequiredDocuments(array $validated, ?Spr $spr = null): void
    {
        $requiredIds = DokumenCostumer::query()->finalized()->where('status', 'aktif')->where('wajib', true)
            ->where('kategori_pengajuan', 'spr')
            ->pluck('id');
        $uploadedIds = collect($validated['berkas'] ?? [])->filter(fn (array $row) => ($row['selected'] ?? true) && (! empty($row['file_upload']) || ! empty($row['customer_document_id'])))->pluck('dokumen_costumer_id')->map(fn ($id) => (int) $id);
        if ($spr) {
            $uploadedIds = $uploadedIds->merge($spr->berkasCostumers()->pluck('dokumen_costumer_id'));
        }
        $missing = $requiredIds->diff($uploadedIds->unique());
        if ($missing->isNotEmpty()) {
            $names = DokumenCostumer::query()->whereKey($missing)->pluck('nama_dokumen')->implode(', ');
            throw ValidationException::withMessages(['berkas' => 'Dokumen wajib belum diunggah: '.$names.'.']);
        }
    }

    protected function validateKprBankRules(array $validated): void
    {
        if (($validated['metode_pembayaran'] ?? null) !== 'kpr_bank') {
            return;
        }

        if (empty($validated['bank_kredit_id'])) {
            throw ValidationException::withMessages([
                'bank_kredit_id' => 'Metode KPR Bank wajib memilih bank kredit.',
            ]);
        }

        $reservation = $this->approvedReservationFromPayload($validated);
        $bank = BankKredit::query()->finalized()->find($validated['bank_kredit_id']);
        $product = BankCreditProduct::query()->finalized()->find($validated['bank_credit_product_id'] ?? null);
        $branch = BankBranch::query()->finalized()->find($validated['bank_branch_id'] ?? null)
            ?? ($reservation
                && $product
                && $reservation->booking_fee_source_type === BankCreditProduct::class
                && (int) $reservation->booking_fee_source_id === (int) $product->id
                ? BankBranch::query()->find($product->bank_branch_id)
                : null);
        $unit = DetailRumah::query()->finalized()->find($validated['detail_rumah_id'] ?? null)
            ?? ($reservation && (int) $reservation->detail_rumah_id === (int) ($validated['detail_rumah_id'] ?? 0)
                ? DetailRumah::query()->find($reservation->detail_rumah_id)
                : null);

        if (! $unit || ! $bank || ! $branch || ! $product) {
            throw ValidationException::withMessages(['bank_credit_product_id' => 'Kombinasi unit, bank, dan produk kredit tidak valid.']);
        }

        $productIsValid = (int) $product->bank_kredit_id === (int) $bank->id
            && (int) $branch->bank_kredit_id === (int) $bank->id
            && (int) $product->bank_branch_id === (int) $branch->id
            && $bank->status === 'aktif'
            && $branch->status === 'aktif'
            && $product->status === 'aktif'
            && (! $product->effective_from || $product->effective_from->lte(today()))
            && (! $product->effective_until || $product->effective_until->gte(today()));
        $partnershipExists = BankHousingPartnership::query()->finalized()
            ->where('bank_kredit_id', $bank->id)
            ->where('perumahan_id', $unit->perumahan_id)
            ->where('status', 'aktif')
            ->whereDate('effective_from', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->where('bank_branch_id', $branch->id)
            ->exists();

        $reservationConfirmsCombination = $reservation
            && $reservation->booking_fee_source_type === BankCreditProduct::class
            && (int) $reservation->booking_fee_source_id === (int) $product->id
            && (int) $reservation->detail_rumah_id === (int) $unit->id;

        if (! $productIsValid || (! $partnershipExists && ! $reservationConfirmsCombination)) {
            throw ValidationException::withMessages(['bank_credit_product_id' => 'Produk kredit tidak aktif atau bank tidak mempunyai PKS aktif untuk perumahan unit ini.']);
        }
        $maximumTenor = (int) ($product?->maximum_tenor_months ?? $bank?->tenor_max_bulan ?? 0);
        $minimumTenor = max(1, (int) ($bank?->tenor_min_bulan ?? 1));
        $tenor = (int) ($validated['kpr_tenor_bulan'] ?? $maximumTenor);

        if ($bank && ($tenor < $minimumTenor || $tenor > $maximumTenor)) {
            throw ValidationException::withMessages([
                'kpr_tenor_bulan' => "Tenor {$bank->nama_bank} harus antara {$minimumTenor} sampai {$maximumTenor} bulan.",
            ]);
        }

        $finalPrice = (float) $unit->harga_jual
            + ((float) ($validated['penambahan_tanah'] ?? 0) * (float) ($validated['harga_penambahan_tanah'] ?? 0))
            + (float) ($validated['harga_penambahan_lain_lain'] ?? 0);
        $downPayment = (float) ($validated['uang_muka'] ?? 0);
        $bookingFee = (float) ($validated['booking_fee'] ?? 0);
        $financing = (float) ($validated['nilai_pengajuan_kpr'] ?? 0);
        if ($downPayment < (float) $product->minimum_down_payment) {
            throw ValidationException::withMessages(['uang_muka' => 'Uang muka minimum produk bank ini adalah Rp '.number_format((float) $product->minimum_down_payment, 0, ',', '.').'.']);
        }
        if ($financing < (float) $product->minimum_ceiling || $financing > (float) $product->maximum_ceiling) {
            throw ValidationException::withMessages(['nilai_pengajuan_kpr' => 'Nilai pengajuan harus antara Rp '.number_format((float) $product->minimum_ceiling, 0, ',', '.').' sampai Rp '.number_format((float) $product->maximum_ceiling, 0, ',', '.').'.']);
        }
        if ($financing > max(0, $finalPrice - $bookingFee - $downPayment)) {
            throw ValidationException::withMessages(['nilai_pengajuan_kpr' => 'Nilai pengajuan tidak boleh melebihi sisa harga setelah booking fee dan uang muka.']);
        }
    }

    protected function normalizeSprPayload(array $validated): array
    {
        $bankKredit = null;
        $creditProduct = null;
        $bankBranch = null;
        $cashScheme = null;
        $developerProduct = null;
        if (($validated['metode_pembayaran'] ?? null) === 'kpr_bank' && ! empty($validated['bank_kredit_id'])) {
            $bankKredit = BankKredit::query()->finalized()->find($validated['bank_kredit_id']);
            $bankBranch = BankBranch::query()->finalized()->find($validated['bank_branch_id'] ?? null);
            $creditProduct = BankCreditProduct::query()->finalized()->find($validated['bank_credit_product_id'] ?? null);
        }
        $reservation = $this->approvedReservationFromPayload($validated);
        $unitForMaster = DetailRumah::query()->finalized()->find($validated['detail_rumah_id'] ?? null)
            ?? ($reservation && (int) $reservation->detail_rumah_id === (int) ($validated['detail_rumah_id'] ?? 0)
                ? DetailRumah::query()->find($reservation->detail_rumah_id)
                : null);
        if (($validated['metode_pembayaran'] ?? null) === 'cash_bertahap') {
            $cashScheme = CashInstallmentScheme::query()->finalized()->with('steps')->where('status', 'aktif')->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->where(fn ($q) => $q->where('perumahan_id', $unitForMaster?->perumahan_id)->orWhereHas('housings', fn ($h) => $h->whereKey($unitForMaster?->perumahan_id)))->find($validated['cash_installment_scheme_id'] ?? null);
            if (! $cashScheme
                && $reservation?->booking_fee_source_type === CashInstallmentScheme::class
                && (int) $reservation->booking_fee_source_id === (int) ($validated['cash_installment_scheme_id'] ?? 0)) {
                $cashScheme = CashInstallmentScheme::query()->finalized()->with('steps')->find($reservation->booking_fee_source_id);
            }
            if (! $cashScheme) {
                throw ValidationException::withMessages(['cash_installment_scheme_id' => 'Skema Cash Bertahap tidak aktif atau tidak berlaku untuk perumahan unit ini.']);
            }
        }
        if (($validated['metode_pembayaran'] ?? null) === 'kpr_developer') {
            $developerProduct = DeveloperKprProduct::query()->finalized()->where('status', 'aktif')->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->where(fn ($q) => $q->where('perumahan_id', $unitForMaster?->perumahan_id)->orWhereHas('housings', fn ($h) => $h->whereKey($unitForMaster?->perumahan_id)))->find($validated['developer_kpr_product_id'] ?? null);
            if (! $developerProduct
                && $reservation?->booking_fee_source_type === DeveloperKprProduct::class
                && (int) $reservation->booking_fee_source_id === (int) ($validated['developer_kpr_product_id'] ?? 0)) {
                $developerProduct = DeveloperKprProduct::query()->finalized()->find($reservation->booking_fee_source_id);
            }
            if (! $developerProduct) {
                throw ValidationException::withMessages(['developer_kpr_product_id' => 'Produk KPR Developer tidak aktif atau tidak berlaku untuk perumahan unit ini.']);
            }
            $tenor = (int) ($validated['kpr_tenor_bulan'] ?? 0);
            $allowed = $developerProduct->allowed_tenors ?? [];
            if ($developerProduct->tenor_mode === 'custom' && ! in_array($tenor, array_map('intval', $allowed), true)) {
                throw ValidationException::withMessages(['kpr_tenor_bulan' => 'Tenor yang dipilih tidak tersedia pada produk KPR Developer.']);
            }
            if ($developerProduct->tenor_mode === 'range' && ($tenor < (int) $developerProduct->minimum_tenor_months || $tenor > (int) $developerProduct->maximum_tenor_months || (($tenor - (int) $developerProduct->minimum_tenor_months) % (int) $developerProduct->tenor_increment) !== 0)) {
                throw ValidationException::withMessages(['kpr_tenor_bulan' => 'Tenor harus berada dalam rentang dan kelipatan tenor produk.']);
            }
        }
        $bookingFee = (float) ($validated['booking_fee'] ?? 0);
        $bookingFeeIncludesDp = (bool) ($validated['booking_fee_includes_dp'] ?? false);
        $tanggalPembayaranBookingFee = filled($validated['tanggal_pembayaran_booking_fee'] ?? null) ? $validated['tanggal_pembayaran_booking_fee'] : null;
        $uangMuka = (float) ($validated['uang_muka'] ?? 0);
        $uangMukaJumlahPembayaran = isset($validated['uang_muka_jumlah_pembayaran']) && $validated['uang_muka_jumlah_pembayaran'] !== '' ? (int) $validated['uang_muka_jumlah_pembayaran'] : null;
        $tanggalJatuhTempoDp = filled($validated['tanggal_jatuh_tempo_dp'] ?? null) ? $validated['tanggal_jatuh_tempo_dp'] : null;
        $nilaiPengajuanKpr = (float) ($validated['nilai_pengajuan_kpr'] ?? 0);
        $luasPenambahanTanah = (float) ($validated['penambahan_tanah'] ?? 0);
        $hargaPenambahanTanah = (float) ($validated['harga_penambahan_tanah'] ?? 0);
        $hargaPenambahanLain = (float) ($validated['harga_penambahan_lain_lain'] ?? 0);
        $tanggalJatuhTempoAngsuran = filled($validated['tanggal_jatuh_tempo_angsuran'] ?? null) ? $validated['tanggal_jatuh_tempo_angsuran'] : null;
        $totalPenambahanTanah = $luasPenambahanTanah * $hargaPenambahanTanah;
        $totalPenambahanLain = $hargaPenambahanLain;
        $totalPenambahan = $totalPenambahanTanah + $totalPenambahanLain;
        $hargaJual = (float) ($unitForMaster?->harga_jual ?? 0);
        $nilaiPengajuanAkhir = $hargaJual + $totalPenambahan;
        $jumlahTermin = $cashScheme ? max(1, (int) $cashScheme->installment_count) : null;
        $sisaBertahap = max(0, $nilaiPengajuanAkhir - $bookingFee - $uangMuka);
        $nominalTermin = $jumlahTermin ? round($sisaBertahap / $jumlahTermin) : null;

        if ($cashScheme) {
            $minimumDp = $cashScheme->dp_type === 'percentage' ? $nilaiPengajuanAkhir * (float) $cashScheme->minimum_dp / 100 : (float) $cashScheme->minimum_dp;
            if ($bookingFee < (float) $cashScheme->minimum_booking_fee) {
                throw ValidationException::withMessages(['booking_fee' => 'Booking fee minimum untuk skema ini adalah Rp '.number_format((float) $cashScheme->minimum_booking_fee, 0, ',', '.').'.']);
            }
            if ($uangMuka < $minimumDp) {
                throw ValidationException::withMessages(['uang_muka' => 'Uang muka minimum untuk skema ini adalah Rp '.number_format($minimumDp, 0, ',', '.').'.']);
            }
        }
        if ($developerProduct) {
            $minimumDp = $developerProduct->dp_type === 'percentage' ? $nilaiPengajuanAkhir * (float) $developerProduct->minimum_dp / 100 : (float) $developerProduct->minimum_dp;
            $financingBase = match ($developerProduct->financing_basis) {
                'sale_price' => $hargaJual,
                'final_less_booking' => max(0, $nilaiPengajuanAkhir - $bookingFee),
                'final_less_booking_dp' => max(0, $nilaiPengajuanAkhir - $bookingFee - $uangMuka),
                default => $nilaiPengajuanAkhir,
            };
            $maximumFinancing = $developerProduct->financing_type === 'percentage' ? $financingBase * (float) $developerProduct->maximum_financing / 100 : (float) $developerProduct->maximum_financing;
            if ($uangMuka < $minimumDp) {
                throw ValidationException::withMessages(['uang_muka' => 'Uang muka minimum produk ini adalah Rp '.number_format($minimumDp, 0, ',', '.').'.']);
            }
            $availableFinancing = max(0, $nilaiPengajuanAkhir - $bookingFee - $uangMuka);
            $nilaiPengajuanKpr = min($maximumFinancing, $availableFinancing);
        }

        $developerMargin = $developerProduct ? (float) $developerProduct->annual_margin : null;
        if ($developerProduct?->margin_scope === 'per_tenor') {
            $tenor = (int) ($validated['kpr_tenor_bulan'] ?? 0);
            $tier = collect($developerProduct->margin_tiers ?? [])->first(fn (array $row) => (int) ($row['tenor_months'] ?? $row['tenor'] ?? 0) === $tenor);
            if (! $tier) {
                throw ValidationException::withMessages(['kpr_tenor_bulan' => 'Margin untuk tenor yang dipilih belum tersedia pada master produk.']);
            }
            $developerMargin = (float) ($tier['annual_margin'] ?? $tier['margin'] ?? $tier['value'] ?? 0);
        }

        $masterSnapshot = $cashScheme
            ? ['type' => 'cash_installment_scheme', 'master' => $cashScheme->toArray()]
            : ($developerProduct
                ? ['type' => 'developer_kpr_product', 'master' => $developerProduct->toArray()]
                : ($creditProduct ? ['type' => 'bank_credit_product', 'master' => $creditProduct->toArray()] : ['type' => $validated['metode_pembayaran'], 'master' => null]));
        $paymentSnapshot = [
            ...$masterSnapshot,
            'captured_at' => now()->toIso8601String(),
            'pricing' => ['unit_price' => $hargaJual, 'additional_price' => $totalPenambahan, 'final_price' => $nilaiPengajuanAkhir, 'booking_fee' => $bookingFee, 'down_payment' => $uangMuka, 'financing_amount' => $nilaiPengajuanKpr],
        ];

        return [
            'housing_reservation_id' => $validated['housing_reservation_id'] ?? null,
            'costumer_id' => $validated['costumer_id'],
            'detail_rumah_id' => $validated['detail_rumah_id'],
            'tanggal_spr' => $validated['tanggal_spr'],
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'bank_kredit_id' => $bankKredit?->id,
            'bank_branch_id' => $bankBranch?->id,
            'bank_credit_product_id' => $creditProduct?->id,
            'cash_installment_scheme_id' => $cashScheme?->id,
            'developer_kpr_product_id' => $developerProduct?->id,
            'payment_configuration_snapshot' => $paymentSnapshot,
            'kpr_tenor_bulan' => ($bankKredit || $developerProduct) ? (int) ($validated['kpr_tenor_bulan'] ?? $creditProduct?->maximum_tenor_months ?? $developerProduct?->maximum_tenor_months ?? $bankKredit?->tenor_max_bulan) : null,
            'kpr_bunga_tahunan' => $creditProduct ? (float) $creditProduct->indicative_interest_margin : ($developerProduct ? $developerMargin : null),
            'harga_jual' => $hargaJual,
            'booking_fee' => $bookingFee,
            'booking_fee_includes_dp' => $bookingFeeIncludesDp,
            'tanggal_pembayaran_booking_fee' => $tanggalPembayaranBookingFee,
            'uang_muka' => $uangMuka,
            'uang_muka_jumlah_pembayaran' => $uangMukaJumlahPembayaran,
            'tanggal_jatuh_tempo_dp' => $tanggalJatuhTempoDp,
            'nilai_pengajuan_kpr' => $nilaiPengajuanKpr,
            'penambahan_tanah' => $luasPenambahanTanah,
            'harga_penambahan_tanah' => $hargaPenambahanTanah,
            'penambahan_lain_lain' => $validated['penambahan_lain_lain'] ?? null,
            'harga_penambahan_lain_lain' => $hargaPenambahanLain,
            'total_penambahan_tanah' => $totalPenambahanTanah,
            'total_penambahan_lain_lain' => $totalPenambahanLain,
            'total_penambahan' => $totalPenambahan,
            'nilai_pengajuan_akhir' => $nilaiPengajuanAkhir,
            'jumlah_termin' => $jumlahTermin,
            'nominal_termin' => $nominalTermin,
            'tanggal_jatuh_tempo_termin' => $tanggalJatuhTempoAngsuran,
            'tanggal_jatuh_tempo_angsuran' => $tanggalJatuhTempoAngsuran,
            'catatan' => $validated['catatan'] ?? null,
        ];
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => Spr::STATUS_DRAFT, 'label' => 'Draft / Belum Diajukan'],
            ['value' => Spr::STATUS_MENUNGGU_APPROVAL, 'label' => 'Menunggu Approval'],
            ['value' => Spr::STATUS_MENUNGGU_MANAGER, 'label' => 'Menunggu Manajer'],
            ['value' => Spr::STATUS_MENUNGGU_OWNER, 'label' => 'Menunggu Owner'],
            ['value' => Spr::STATUS_DISETUJUI, 'label' => 'Disetujui'],
            ['value' => Spr::STATUS_DITOLAK, 'label' => 'Ditolak'],
        ];
    }

    protected function labelFromOptions(?string $value, array $options): string
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value ?? '-';
    }

    protected function nextSprCode(): string
    {
        return 'SPR-'.now()->format('ymd').'-'.str_pad((string) ((Spr::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function nextKprCode(): string
    {
        return 'KPR-'.now()->format('ymd').'-'.str_pad((string) ((KprSubmission::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function modelClass(): string
    {
        return Spr::class;
    }

    protected function nextCashCode(): string
    {
        $next = (int) (CashSale::withTrashed()->max('id') ?? 0) + 1;

        return 'CASH-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    protected function ensureUnitIsAvailable(int $detailRumahId, ?int $ignoreSprId = null): void
    {
        $reserved = Spr::query()
            ->where('detail_rumah_id', $detailRumahId)
            ->where(function (Builder $query): void {
                $query->whereIn('status', $this->activeSprStatuses)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', '!=', Spr::STATUS_DITOLAK)
                            ->whereHas('salesTransaction.customerReceipts', fn (Builder $paymentQuery) => $paymentQuery->where('status', '!=', 'rejected'));
                    });
            })
            ->when($ignoreSprId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreSprId))
            ->exists();

        if ($reserved) {
            throw ValidationException::withMessages([
                'detail_rumah_id' => 'Unit ini sudah memiliki SPR aktif, silakan pilih unit lain.',
            ]);
        }
    }

    protected function ensureCustomerCanBeUsed(Request $request, int $customerId): void
    {
        if (! $this->shouldScopeToCurrentMarketing($request)) {
            return;
        }

        abort_unless(
            Costumer::query()
                ->whereKey($customerId)
                ->where('created_by', $request->user()?->id)
                ->where('perumahan_id', $this->ensureActivePerumahan($request))
                ->exists(),
            403,
        );
    }

    protected function ensureUnitBelongsToActivePerumahan(Request $request, int $detailRumahId): void
    {
        if (! $this->shouldScopeToActivePerumahan($request)) {
            return;
        }

        abort_unless(
            DetailRumah::query()
                ->whereKey($detailRumahId)
                ->where('perumahan_id', $this->ensureActivePerumahan($request))
                ->exists(),
            403,
        );
    }

    protected function abortIfCurrentMarketingCannotAccessSpr(Request $request, Spr $spr): void
    {
        abort_if(
            $this->shouldScopeToCurrentMarketing($request)
            && (int) $spr->created_by !== (int) $request->user()?->id,
            403,
        );

        if ($this->shouldScopeToActivePerumahan($request)) {
            abort_unless(
                DetailRumah::query()
                    ->whereKey($spr->detail_rumah_id)
                    ->where('perumahan_id', $this->ensureActivePerumahan($request))
                    ->exists(),
                403,
            );
        }
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    protected function scopeSprVisibility(Builder $query, Request $request): Builder
    {
        $user = $request->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $canSeeLocked = $this->currentUserCanUnlockSpr();
        if (! $canSeeLocked) {
            $setting = ApprovalSetting::query()
                ->where(['module_key' => 'spr', 'action' => 'lock', 'is_active' => true])
                ->first();
            $reviewerRoleIds = collect($setting?->approval_steps ?? [])
                ->flatMap(fn (array $step) => $step['role_ids'] ?? [])
                ->map(fn ($roleId) => (int) $roleId)
                ->filter()
                ->unique();
            $canSeeLocked = $reviewerRoleIds->isNotEmpty()
                && $user->roles()->whereIn('roles.id', $reviewerRoleIds)->exists();
        }

        return $query->where(function (Builder $query) use ($user, $canSeeLocked): void {
            $query->where('created_by', $user->id);
            if ($canSeeLocked) {
                $query->orWhere('record_status', 'locked');
            }
        });
    }

    protected function currentUserCanUnlockSpr(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['manager', 'owner', 'super_admin']);
    }
}
