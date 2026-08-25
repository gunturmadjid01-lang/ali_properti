<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ChecksMarketingAccess;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\BerkasCostumer;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerReceipt;
use App\Models\KprSubmission;
use App\Models\MarketingCampaign;
use App\Models\MarketingCommission;
use App\Models\MarketingDocumentReview;
use App\Models\MarketingLead;
use App\Models\MarketingLeadActivity;
use App\Models\MarketingReminder;
use App\Models\MarketingSurveySchedule;
use App\Models\MarketingTarget;
use App\Models\MarketingTemplate;
use App\Models\PaymentSchedule;
use App\Models\Spr;
use App\Models\SprBerkasCostumer;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\Marketing\MarketingOperationsService;
use App\Support\CodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketingOperationsController extends Controller
{
    use ChecksMarketingAccess, ScopesActivePerumahan;

    public function show(Request $request, string $section, MarketingOperationsService $service): Response
    {
        abort_unless(in_array($section, $this->sections(), true), 404);
        $permissions = $this->permissionsForSection($request, $section);
        if ($section === 'dashboard') {
            abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('dashboard.view'), 403);
        } else {
            $this->abortUnlessMarketingAccess(
                $request,
                $this->defaultRolesForSection($section),
                $this->permissionForSection($section),
            );
        }

        if (in_array($section, ['dashboard', 'reminder'], true)) {
            $service->syncAutomaticReminders($request->user()?->id);
        }

        return Inertia::render('Admin/Marketing/Operations/Index', [
            'title' => $this->title($section),
            'section' => $section,
            'baseUrl' => route('admin.marketing.operasional.show', $section, absolute: false),
            'data' => $this->data($request, $section),
            'permissions' => $permissions,
        ]);
    }

    public function create(Request $request, string $section): Response
    {
        $this->authorizeSectionAction($request, $section, 'canCreate');

        return $this->formResponse($request, $section);
    }

    public function edit(Request $request, string $section, string $id): Response
    {
        $this->authorizeSectionAction($request, $section, 'canUpdate');
        $type = $this->formType($request, $section);
        $row = $this->editableModel($request, $section, $id, $type);
        abort_if(($row->record_status ?? 'draft') === 'locked', 422, 'Data yang sudah dikunci tidak dapat diubah.');

        return $this->formResponse($request, $section, $row, $type);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $this->authorizeSectionAction($request, $section, 'canCreate');
        match ($section) {
            'campaign' => $this->storeCampaign($request),
            'reminder' => $this->storeReminder($request),
            'target-komisi' => $this->storeTargetOrCommission($request),
            'template' => $this->storeTemplate($request),
            default => abort(404),
        };

        return redirect()->route('admin.marketing.operasional.show', $section)->with('success', 'Data berhasil disimpan.');
    }

    public function update(Request $request, string $section, string $id): RedirectResponse
    {
        $this->authorizeSectionAction($request, $section, 'canUpdate');
        match ($section) {
            'campaign' => $this->updateCampaign($request, $id),
            'reminder' => $this->updateReminder($request, $id),
            'target-komisi' => $this->updateTargetOrCommission($request, $id),
            'template' => $this->updateTemplate($request, $id),
            default => abort(404),
        };

        return redirect()->route('admin.marketing.operasional.show', $section)->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Request $request, string $section, string $id): RedirectResponse
    {
        $this->authorizeSectionAction($request, $section, 'canDelete');
        $model = match ($section) {
            'campaign' => MarketingCampaign::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->findOrFail($id),
            'reminder' => $this->reminderQueryFor($request)->findOrFail($id),
            'template' => MarketingTemplate::query()->findOrFail($id),
            'target-komisi' => $request->input('type') === 'commission'
                ? MarketingCommission::query()
                    ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                    ->findOrFail($id)
                : MarketingTarget::query()
                    ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                    ->findOrFail($id),
            default => abort(404),
        };

        abort_if(($model->record_status ?? 'draft') === 'locked', 422, 'Data yang sudah di-lock tidak dapat dihapus.');
        abort_if(($model->is_system ?? false), 422, 'Template bawaan sistem tidak dapat dihapus.');
        $model->delete();

        return back()->with('success', 'Data berhasil dihapus.');
    }

    public function completeReminder(Request $request, string $id): RedirectResponse
    {
        $this->authorizeSectionAction($request, 'reminder', 'canUpdate');
        $reminder = $this->reminderQueryFor($request)->findOrFail($id);
        $reminder->update([
            'status' => 'selesai',
            'completed_at' => now(),
        ]);
        $this->syncReminderSource($reminder);

        return back()->with('success', 'Reminder ditandai selesai.');
    }

    public function lock(string $section, string $id): RedirectResponse
    {
        $permissionSection = in_array($section, ['target', 'commission'], true) ? 'target-komisi' : $section;
        $this->authorizeSectionAction(request(), $permissionSection, 'canLock');
        $model = $this->lockableModel($section, $id);
        abort_unless(($model->record_status ?? 'draft') === 'draft', 422, 'Data sudah dikunci.');
        $model->forceFill([
            'record_status' => 'locked',
            'locked_at' => now(),
            'locked_by' => auth()->id(),
        ])->save();
        $approval = app(ApprovalWorkflowService::class)
            ->submitLocked($model, 'marketing-'.$section);

        return back()->with('success', $approval->status === 'approved'
            ? 'Data di-lock dan disetujui otomatis.'
            : "Data di-lock dan masuk approval tahap 1 dari {$approval->total_steps}.");
    }

    public function unlock(string $section, string $id): RedirectResponse
    {
        $permissionSection = in_array($section, ['target', 'commission'], true) ? 'target-komisi' : $section;
        $permissions = $this->permissionsForSection(request(), $permissionSection);
        abort_unless(($permissions['canUnlock'] ?? false) === true, 403, 'Hanya user yang diberi akses yang dapat membuka lock data.');

        $model = $this->lockableModel($section, $id);
        abort_unless(($model->record_status ?? 'draft') === 'locked', 422, 'Data tidak sedang dikunci.');
        app(ApprovalWorkflowService::class)->reverseLockApproval($model);
        $model->forceFill([
            'record_status' => 'draft',
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        return back()->with('success', 'Lock data berhasil dibuka.');
    }

    public function reviewDocument(Request $request): RedirectResponse
    {
        $this->abortUnlessMarketingAccess($request, ['supervisor_marketing'], 'marketing.document-review.manage');
        $validated = $request->validate([
            'document_type' => ['required', Rule::in([SprBerkasCostumer::class, BerkasCostumer::class])],
            'document_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['menunggu', 'valid', 'revisi', 'ditolak'])],
            'catatan_revisi' => ['nullable', 'string'],
        ]);
        $documentQuery = $validated['document_type'] === SprBerkasCostumer::class
            ? SprBerkasCostumer::query()->whereKey($validated['document_id'])
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            : BerkasCostumer::query()->whereKey($validated['document_id'])
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('submission.spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)));
        abort_unless($documentQuery->exists(), 404);

        MarketingDocumentReview::query()->updateOrCreate(
            ['document_type' => $validated['document_type'], 'document_id' => $validated['document_id']],
            [
                'status' => $validated['status'],
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ],
        );

        return back()->with('success', 'Status validasi berkas berhasil diperbarui.');
    }

    public function receipt(Request $request, string $id): Response
    {
        $this->abortUnlessMarketingAccess($request, ['owner', 'manajer_pimpro'], 'marketing.receivable.view');
        $payment = CustomerReceipt::query()
            ->with(['salesTransaction.customer', 'salesTransaction.spr', 'bankAccount'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('marketing_user_id', $request->user()?->id)))
            ->findOrFail($id);

        return Inertia::render('Admin/Marketing/Receipt/Show', [
            'title' => 'Kwitansi '.$payment->receipt_no,
            'receipt' => [
                'number' => $payment->receipt_no,
                'date' => optional($payment->payment_date)->format('d/m/Y'),
                'customer' => $payment->salesTransaction?->customer?->nama ?? '-',
                'type' => ucwords(str_replace('_', ' ', $payment->receipt_purpose)),
                'spr' => $payment->salesTransaction?->spr?->kode_spr ?? '-',
                'bank' => trim(($payment->bankAccount?->nama_bank ?? '-').' - '.($payment->bankAccount?->nomor_rekening ?? '-').' - '.($payment->bankAccount?->nama_rekening ?? '-')),
                'note' => $payment->notes,
                'amount' => (float) $payment->amount,
            ],
        ]);
    }

    protected function data(Request $request, string $section): array
    {
        return match ($section) {
            'dashboard' => $this->dashboardData($request),
            'pipeline' => $this->pipelineData($request),
            'campaign' => $this->campaignData($request),
            'reminder' => $this->reminderData($request),
            'dokumen' => $this->documentData($request),
            'piutang' => $this->receivableData($request),
            'target-komisi' => $this->targetData($request),
            'template' => $this->templateData(),
        };
    }

    protected function dashboardData(Request $request): array
    {
        $month = now()->month;
        $year = now()->year;
        $months = collect(range(5, 0))->map(fn (int $offset) => now()->copy()->subMonths($offset));
        $trendStart = $months->first()->copy()->startOfMonth();
        $leadsByMonth = $this->customerQueryFor($request)
            ->where('created_at', '>=', $trendStart)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_key, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->pluck('total', 'month_key');
        $sprByMonth = $this->sprQueryFor($request)
            ->whereDate('tanggal_spr', '>=', $trendStart)
            ->selectRaw("DATE_FORMAT(tanggal_spr, '%Y-%m') as month_key, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(tanggal_spr, '%Y-%m')")
            ->pluck('total', 'month_key');

        return [
            'stats' => [
                'lead' => $this->customerQueryFor($request)->count(),
                'follow_up_due' => $this->reminderQueryFor($request)->where('status', 'menunggu')->where('remind_at', '<=', now()->addDays(3))->count(),
                'spr_month' => $this->sprQueryFor($request)->whereMonth('tanggal_spr', $month)->whereYear('tanggal_spr', $year)->count(),
                'kpr_active' => $this->kprQueryFor($request)->whereNotIn('status', ['ditolak', 'serah_terima_selesai'])->count(),
                'booking_expiring' => $this->sprQueryFor($request)->where('status', Spr::STATUS_DISETUJUI)->whereBetween('booking_expires_at', [now(), now()->addDays(7)])->count(),
                'overdue' => PaymentSchedule::query()->where('record_status', 'locked')->whereDate('due_date', '<', today())->whereColumn('paid_amount', '<', 'amount')
                    ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('marketing_user_id', $request->user()?->id)))
                    ->count(),
            ],
            'trend' => [
                'labels' => $months->map(fn (Carbon $date) => $date->translatedFormat('M Y'))->values(),
                'leads' => $months->map(fn (Carbon $date) => $leadsByMonth->get($date->format('Y-m'), 0))->values(),
                'spr' => $months->map(fn (Carbon $date) => $sprByMonth->get($date->format('Y-m'), 0))->values(),
            ],
            'performance' => User::query()
                ->withCount([
                    'sprs as spr_count' => fn (Builder $query) => $query
                        ->whereMonth('tanggal_spr', $month)
                        ->whereYear('tanggal_spr', $year)
                        ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))),
                    'kprSubmissions as kpr_count' => fn (Builder $query) => $query
                        ->whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))),
                ])
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'supervisor_marketing', 'area_marketing']))
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereKey($request->user()?->id))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($this->activePerumahanId($request))))
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'spr' => $user->spr_count, 'kpr' => $user->kpr_count])
                ->values(),
            'recent' => MarketingLeadActivity::query()
                ->with(['costumer:id,nama', 'user:id,name'])
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                ->latest('activity_at')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'customer' => $row->costumer?->nama ?? '-',
                    'status' => $row->status_to,
                    'user' => $row->user?->name ?? '-',
                    'at' => optional($row->activity_at)->format('d/m/Y H:i'),
                ]),
        ];
    }

    protected function pipelineData(Request $request): array
    {
        $statuses = [
            ['value' => 'new', 'label' => 'Lead Baru'],
            ['value' => 'contacted', 'label' => 'Sudah Dihubungi'],
            ['value' => 'nurturing', 'label' => 'Dalam Follow-up'],
            ['value' => 'qualified', 'label' => 'Qualified'],
            ['value' => 'postponed', 'label' => 'Ditunda'],
            ['value' => 'lost', 'label' => 'Tidak Potensial'],
            ['value' => 'converted', 'label' => 'Menjadi Customer'],
        ];
        $leads = MarketingLead::query()
            ->with(['source:id,nama_sumber'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->where('perumahan_id', $this->activePerumahanId($request)))
            ->latest('id')
            ->limit(500)
            ->get();

        return [
            'columns' => collect($statuses)->map(fn ($status) => [
                ...$status,
                'leads' => $leads->where('stage', $status['value'])->map(fn (MarketingLead $lead) => [
                    'id' => $lead->id,
                    'kode' => $lead->lead_no,
                    'nama' => $lead->name,
                    'telepon' => $lead->phone,
                    'source' => $lead->source?->nama_sumber ?? ($lead->source_channel ?: '-'),
                    'campaign' => '-',
                    'last_activity' => $lead->last_activity_at
                        ? $lead->last_activity_at->format('d/m/Y')
                        : '-',
                ])->values(),
            ])->values(),
        ];
    }

    protected function campaignData(Request $request): array
    {
        return [
            'rows' => MarketingCampaign::query()
                ->with('perumahan:id,nama_perusahaan')
                ->withCount('customers')
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (MarketingCampaign $row) => [
                    ...$row->only(['id', 'kode_campaign', 'nama_campaign', 'kanal', 'anggaran', 'realisasi_biaya', 'target_lead', 'status', 'keterangan', 'record_status']),
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                    'tanggal_selesai' => optional($row->tanggal_selesai)->format('Y-m-d'),
                    'lead_count' => $row->customers_count,
                ]),
        ];
    }

    protected function reminderData(Request $request): array
    {
        return [
            'rows' => $this->reminderQueryFor($request)->with(['costumer:id,nama,telepon', 'user:id,name'])->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END")->orderBy('remind_at')->limit(300)->get()
                ->map(fn (MarketingReminder $row) => [
                    ...$row->only(['id', 'costumer_id', 'user_id', 'jenis', 'judul', 'status', 'catatan']),
                    'customer' => $row->costumer?->nama ?? '-',
                    'telepon' => $row->costumer?->telepon ?? '-',
                    'user' => $row->user?->name ?? '-',
                    'remind_at' => optional($row->remind_at)->format('Y-m-d\TH:i'),
                    'is_overdue' => $row->status === 'menunggu' && $row->remind_at?->isPast(),
                ]),
        ];
    }

    protected function documentData(Request $request): array
    {
        $sprDocuments = SprBerkasCostumer::query()
            ->with(['spr.costumer:id,nama', 'spr:id,kode_spr,costumer_id,created_by', 'dokumen:id,nama_dokumen'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->latest('id')
            ->limit(150)
            ->get();
        $kprDocuments = BerkasCostumer::query()
            ->with(['submission.spr.costumer:id,nama', 'submission:id,kode_kpr,spr_id', 'dokumen:id,nama_dokumen'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('submission.spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('submission.spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->latest('id')
            ->limit(150)
            ->get();

        $reviews = MarketingDocumentReview::query()
            ->where(function (Builder $query) use ($sprDocuments, $kprDocuments): void {
                $query->where(function (Builder $query) use ($sprDocuments): void {
                    $query->where('document_type', SprBerkasCostumer::class)
                        ->whereIn('document_id', $sprDocuments->pluck('id'));
                })->orWhere(function (Builder $query) use ($kprDocuments): void {
                    $query->where('document_type', BerkasCostumer::class)
                        ->whereIn('document_id', $kprDocuments->pluck('id'));
                });
            })
            ->get()
            ->keyBy(fn ($row) => $row->document_type.'-'.$row->document_id);

        $sprDocuments = $sprDocuments->map(fn ($row) => $this->documentRow($row, SprBerkasCostumer::class, $reviews));
        $kprDocuments = $kprDocuments->map(fn ($row) => $this->documentRow($row, BerkasCostumer::class, $reviews));

        return ['rows' => $sprDocuments->concat($kprDocuments)->sortByDesc('created_at')->values()];
    }

    protected function receivableData(Request $request): array
    {
        $scheduleQuery = PaymentSchedule::query()->where('record_status', 'locked')
            ->with(['salesTransaction.customer', 'salesTransaction.spr', 'salesTransaction.housingUnit'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('marketing_user_id', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('perumahan_id', $this->activePerumahanId($request))));
        $totalBilled = (float) (clone $scheduleQuery)->sum('amount');
        $totalPaid = (float) (clone $scheduleQuery)->sum('paid_amount');
        $overdueCount = (clone $scheduleQuery)->whereDate('due_date', '<', today())->whereColumn('paid_amount', '<', 'amount')->count();
        $schedules = $scheduleQuery
            ->orderBy('due_date')
            ->limit(200)
            ->get();
        $payments = CustomerReceipt::query()->where('status', 'posted')
            ->with(['salesTransaction.customer', 'salesTransaction.spr', 'bankAccount'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('marketing_user_id', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('salesTransaction', fn (Builder $query) => $query->where('perumahan_id', $this->activePerumahanId($request))))
            ->latest('payment_date')
            ->limit(100)
            ->get();

        return [
            'summary' => [
                'tagihan' => $totalBilled,
                'dibayar' => $totalPaid,
                'sisa' => max(0, $totalBilled - $totalPaid),
                'jatuh_tempo' => $overdueCount,
            ],
            'schedules' => $schedules->map(fn ($row) => [
                'id' => $row->id,
                'kode_spr' => $row->salesTransaction?->transaction_no ?? '-',
                'customer' => $row->salesTransaction?->customer?->nama ?? '-',
                'jenis' => $row->description,
                'termin_ke' => $row->sequence,
                'tanggal_jatuh_tempo' => optional($row->due_date)->format('d/m/Y'),
                'nominal_tagihan' => $row->amount,
                'nominal_dibayar' => $row->paid_amount,
                'sisa' => max(0, $row->amount - $row->paid_amount),
                'status' => $row->status,
            ]),
            'receipts' => $payments->map(fn (CustomerReceipt $row) => [
                'id' => $row->id,
                'nomor' => $row->receipt_no,
                'kode_spr' => $row->salesTransaction?->transaction_no ?? '-',
                'customer' => $row->salesTransaction?->customer?->nama ?? '-',
                'tanggal' => optional($row->payment_date)->format('d/m/Y'),
                'jenis' => $row->receipt_purpose,
                'nominal' => $row->amount,
                'bank' => trim(($row->bankAccount?->nama_bank ?? '-').' '.($row->bankAccount?->nomor_rekening ?? '')),
                'print_url' => route('admin.marketing.kwitansi.show', $row->id, absolute: false),
            ]),
        ];
    }

    protected function targetData(Request $request): array
    {
        return [
            'targets' => MarketingTarget::query()
                ->with(['user:id,name', 'perumahan:id,nama_perusahaan'])
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->latest('tahun')
                ->latest('bulan')
                ->limit(200)
                ->get()
                ->map(fn ($row) => [
                    ...$row->only(['id', 'user_id', 'tahun', 'bulan', 'target_lead', 'target_follow_up', 'target_visit', 'target_survey', 'target_reservation', 'target_spr', 'target_closing', 'target_nilai_penjualan', 'catatan', 'record_status']),
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'user' => $row->user?->name ?? '-',
                    'type' => 'target',
                ]),
            'commissions' => MarketingCommission::query()
                ->with(['user:id,name', 'spr:id,kode_spr,created_by,detail_rumah_id', 'spr.detailRumah.perumahan:id,nama_perusahaan'])
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('user_id', $request->user()?->id))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn ($row) => [
                    ...$row->only(['id', 'spr_id', 'user_id', 'dasar_perhitungan', 'persentase', 'nominal', 'status', 'catatan', 'record_status']),
                    'perumahan' => $row->spr?->detailRumah?->perumahan?->nama_perusahaan ?? '-',
                    'user' => $row->user?->name ?? '-',
                    'spr' => $row->spr?->kode_spr ?? '-',
                    'tanggal_jatuh_tempo' => optional($row->tanggal_jatuh_tempo)->format('Y-m-d'),
                    'tanggal_dibayar' => optional($row->tanggal_dibayar)->format('Y-m-d'),
                    'type' => 'commission',
                ]),
        ];
    }

    protected function templateData(): array
    {
        return ['rows' => MarketingTemplate::query()->latest('id')->limit(200)->get()];
    }

    private function authorizeSectionAction(Request $request, string $section, string $ability): void
    {
        abort_unless(in_array($section, ['campaign', 'reminder', 'target-komisi', 'template'], true), 404);
        $this->abortUnlessMarketingAccess($request, $this->defaultRolesForSection($section), $this->permissionForSection($section));
        $permissions = $this->permissionsForSection($request, $section);
        abort_unless($request->user()?->hasRole('super_admin') || ($permissions[$ability] ?? false), 403);
    }

    private function formType(Request $request, string $section): string
    {
        if ($section !== 'target-komisi') {
            return $section;
        }

        $type = (string) $request->query('type', $request->input('type', 'target'));
        abort_unless(in_array($type, ['target', 'commission'], true), 404);

        return $type;
    }

    private function editableModel(Request $request, string $section, string $id, string $type): Model
    {
        return match ($section) {
            'campaign' => MarketingCampaign::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->findOrFail($id),
            'reminder' => $this->reminderQueryFor($request)->findOrFail($id),
            'template' => MarketingTemplate::query()->findOrFail($id),
            'target-komisi' => $type === 'commission'
                ? MarketingCommission::query()
                    ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                    ->findOrFail($id)
                : MarketingTarget::query()
                    ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                    ->findOrFail($id),
            default => abort(404),
        };
    }

    private function formResponse(Request $request, string $section, ?Model $row = null, ?string $resolvedType = null): Response
    {
        $type = $resolvedType ?? $this->formType($request, $section);
        $baseUrl = route('admin.marketing.operasional.show', $section, absolute: false);

        return Inertia::render('Admin/Marketing/Operations/FormPage', [
            'title' => ($row ? 'Edit ' : 'Tambah ').match ($type) {
                'campaign' => 'Campaign',
                'reminder' => 'Reminder',
                'template' => 'Template Komunikasi',
                'commission' => 'Komisi Marketing',
                default => 'Target Marketing',
            },
            'section' => $section,
            'type' => $type,
            'baseUrl' => $baseUrl,
            'actionUrl' => $row
                ? route('admin.marketing.operasional.update', [$section, $row->getKey()], absolute: false)
                : route('admin.marketing.operasional.store', $section, absolute: false),
            'method' => $row ? 'put' : 'post',
            'row' => $row ? $this->formRow($row, $type) : [],
            'options' => $this->formOptions($request, $type),
        ]);
    }

    private function formRow(Model $row, string $type): array
    {
        return match ($type) {
            'campaign' => [
                ...$row->only(['nama_campaign', 'kanal', 'anggaran', 'realisasi_biaya', 'target_lead', 'status', 'keterangan']),
                'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                'tanggal_selesai' => optional($row->tanggal_selesai)->format('Y-m-d'),
            ],
            'reminder' => [
                ...$row->only(['costumer_id', 'user_id', 'jenis', 'judul', 'status', 'catatan']),
                'costumer_id' => (string) ($row->costumer_id ?? ''),
                'user_id' => (string) ($row->user_id ?? ''),
                'remind_at' => optional($row->remind_at)->format('Y-m-d\TH:i'),
            ],
            'template' => $row->only(['nama_template', 'kanal', 'tahapan', 'isi_template', 'status']),
            'commission' => [
                ...$row->only(['spr_id', 'user_id', 'dasar_perhitungan', 'persentase', 'status', 'catatan']),
                'spr_id' => (string) $row->spr_id,
                'user_id' => (string) $row->user_id,
                'tanggal_jatuh_tempo' => optional($row->tanggal_jatuh_tempo)->format('Y-m-d'),
                'tanggal_dibayar' => optional($row->tanggal_dibayar)->format('Y-m-d'),
            ],
            default => [
                ...$row->only(['tahun', 'bulan', 'target_lead', 'target_follow_up', 'target_visit', 'target_survey', 'target_reservation', 'target_spr', 'target_closing', 'target_nilai_penjualan', 'catatan']),
                'user_id' => (string) $row->user_id,
            ],
        };
    }

    private function formOptions(Request $request, string $type): array
    {
        $options = [
            'hideUser' => $this->shouldScopeToCurrentMarketing($request),
            'currentUser' => $request->user()?->name,
        ];

        if ($type === 'reminder') {
            $options['customers'] = $this->customerQueryFor($request)->orderBy('nama')->limit(200)->get(['id', 'nama', 'no_identitas'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => "{$row->nama} - {$row->no_identitas}"]);
        }

        if (in_array($type, ['reminder', 'target', 'commission'], true) && ! ($type === 'reminder' && $options['hideUser'])) {
            $options['users'] = User::query()
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing', 'supervisor_marketing', 'manager', 'manajer_pimpro', 'owner']))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($this->activePerumahanId($request))))
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name]);
        }

        if ($type === 'commission') {
            $options['sprs'] = $this->sprQueryFor($request)->with('costumer:id,nama')->where('status', Spr::STATUS_DISETUJUI)->latest('id')->limit(200)->get(['id', 'kode_spr', 'costumer_id', 'nilai_pengajuan_akhir', 'harga_jual', 'created_by'])->map(fn ($row) => [
                'value' => (string) $row->id,
                'label' => "{$row->kode_spr} - ".($row->costumer?->nama ?? '-'),
                'amount' => (float) ($row->nilai_pengajuan_akhir ?: $row->harga_jual),
                'user_id' => $row->created_by,
            ]);
        }

        return $options;
    }

    protected function documentRow($row, string $type, $reviews): array
    {
        $review = $reviews->get($type.'-'.$row->id);
        $isKpr = $type === BerkasCostumer::class;

        return [
            'id' => $row->id,
            'document_type' => $type,
            'source' => $isKpr ? 'KPR' : 'SPR',
            'reference' => $isKpr ? ($row->submission?->kode_kpr ?? '-') : ($row->spr?->kode_spr ?? '-'),
            'customer' => $isKpr ? ($row->submission?->spr?->costumer?->nama ?? '-') : ($row->spr?->costumer?->nama ?? '-'),
            'document' => $row->dokumen?->nama_dokumen ?? '-',
            'file' => $row->nama_file,
            'url' => route('media', ['path' => $row->path_file], false),
            'status' => $review?->status ?? 'menunggu',
            'catatan_revisi' => $review?->catatan_revisi,
            'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    protected function storeCampaign(Request $request): void
    {
        MarketingCampaign::create([
            ...$request->validate($this->campaignRules()),
            'perumahan_id' => $this->ensureActivePerumahan($request),
            'kode_campaign' => CodeGenerator::next(MarketingCampaign::class, 'kode_campaign', 'CMP'),
        ]);
    }

    protected function updateCampaign(Request $request, string $id): void
    {
        $row = MarketingCampaign::query()
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        abort_if($row->record_status === 'locked', 422, 'Campaign sudah di-lock.');
        $row->update($request->validate($this->campaignRules()));
    }

    protected function campaignRules(): array
    {
        return [
            'nama_campaign' => ['required', 'string', 'max:255'],
            'kanal' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'anggaran' => ['required', 'numeric', 'min:0'],
            'realisasi_biaya' => ['nullable', 'numeric', 'min:0'],
            'target_lead' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'aktif', 'selesai', 'dibatalkan'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    protected function storeReminder(Request $request): void
    {
        $data = $request->validate($this->reminderRules());
        if (! empty($data['costumer_id'])) {
            $this->ensureCustomerCanBeUsed($request, (int) $data['costumer_id']);
        }
        if ($this->shouldScopeToCurrentMarketing($request)) {
            $data['user_id'] = $request->user()?->id;
        } elseif (! empty($data['user_id'])) {
            $this->ensureTeamUserCanBeUsed($request, (int) $data['user_id']);
        }

        MarketingReminder::create($data);
    }

    protected function updateReminder(Request $request, string $id): void
    {
        $reminder = $this->reminderQueryFor($request)->findOrFail($id);
        $data = $request->validate($this->reminderRules());
        if (! empty($data['costumer_id'])) {
            $this->ensureCustomerCanBeUsed($request, (int) $data['costumer_id']);
        }
        if ($this->shouldScopeToCurrentMarketing($request)) {
            $data['user_id'] = $request->user()?->id;
        } elseif (! empty($data['user_id'])) {
            $this->ensureTeamUserCanBeUsed($request, (int) $data['user_id']);
        }

        $reminder->update($data);
        $this->syncReminderSource($reminder);
    }

    protected function reminderRules(): array
    {
        return [
            'costumer_id' => ['nullable', 'exists:costumers,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'jenis' => ['required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:255'],
            'remind_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['menunggu', 'selesai', 'dibatalkan'])],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function syncReminderSource(MarketingReminder $reminder): void
    {
        if (! $reminder->source_type || ! $reminder->source_id) {
            return;
        }

        if ($reminder->source_type === CostumerFollowUp::class) {
            CostumerFollowUp::query()
                ->whereKey($reminder->source_id)
                ->update([
                    'status' => $reminder->status,
                    'updated_by' => $reminder->updated_by ?? auth()->id(),
                ]);

            return;
        }

        if ($reminder->source_type === MarketingSurveySchedule::class) {
            $status = match ($reminder->status) {
                'selesai' => 'selesai',
                'dibatalkan' => 'batal',
                default => null,
            };

            if (! $status) {
                return;
            }

            MarketingSurveySchedule::query()
                ->whereKey($reminder->source_id)
                ->update([
                    'status' => $status,
                    'updated_by' => $reminder->updated_by ?? auth()->id(),
                ]);
        }
    }

    protected function storeTargetOrCommission(Request $request): void
    {
        if ($request->input('type') === 'commission') {
            $data = $request->validate($this->commissionRules());
            $this->ensureSprCanBeUsed($request, (int) $data['spr_id']);
            $this->ensureTeamUserCanBeUsed($request, (int) $data['user_id']);
            MarketingCommission::create([
                ...$data,
                'kode_komisi' => CodeGenerator::next(MarketingCommission::class, 'kode_komisi', 'KMS'),
                'nominal' => (float) $data['dasar_perhitungan'] * ((float) $data['persentase'] / 100),
            ]);

            return;
        }

        $data = $request->validate($this->targetRules());
        $this->ensureTeamUserCanBeUsed($request, (int) $data['user_id']);
        MarketingTarget::create([
            ...$data,
            'perumahan_id' => $this->ensureActivePerumahan($request),
        ]);
    }

    protected function updateTargetOrCommission(Request $request, string $id): void
    {
        if ($request->input('type') === 'commission') {
            $row = MarketingCommission::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                ->findOrFail($id);
            abort_if($row->record_status === 'locked', 422, 'Komisi sudah di-lock.');
            $data = $request->validate($this->commissionRules());
            $this->ensureSprCanBeUsed($request, (int) $data['spr_id']);
            $this->ensureTeamUserCanBeUsed($request, (int) $data['user_id']);
            $row->update([
                ...$data,
                'nominal' => (float) $data['dasar_perhitungan'] * ((float) $data['persentase'] / 100),
            ]);

            return;
        }

        $row = MarketingTarget::query()
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        abort_if($row->record_status === 'locked', 422, 'Target sudah di-lock.');
        $data = $request->validate($this->targetRules($row->id));
        $this->ensureTeamUserCanBeUsed($request, (int) $data['user_id']);
        $row->update($data);
    }

    protected function targetRules(?int $ignoreId = null): array
    {
        return [
            'type' => ['nullable'],
            'user_id' => ['required', 'exists:users,id'],
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'target_lead' => ['required', 'integer', 'min:0'],
            'target_follow_up' => ['required', 'integer', 'min:0'],
            'target_visit' => ['required', 'integer', 'min:0'],
            'target_survey' => ['required', 'integer', 'min:0'],
            'target_reservation' => ['required', 'integer', 'min:0'],
            'target_spr' => ['required', 'integer', 'min:0'],
            'target_closing' => ['required', 'integer', 'min:0'],
            'target_nilai_penjualan' => ['required', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function commissionRules(): array
    {
        return [
            'type' => ['required', Rule::in(['commission'])],
            'spr_id' => ['required', 'exists:sprs,id'],
            'user_id' => ['required', 'exists:users,id'],
            'dasar_perhitungan' => ['required', 'numeric', 'min:0'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'diajukan', 'disetujui', 'dibayar', 'dibatalkan'])],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'tanggal_dibayar' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    protected function storeTemplate(Request $request): void
    {
        MarketingTemplate::create([
            ...$request->validate($this->templateRules()),
            'kode_template' => CodeGenerator::next(MarketingTemplate::class, 'kode_template', 'TPL'),
        ]);
    }

    protected function updateTemplate(Request $request, string $id): void
    {
        $row = MarketingTemplate::query()->findOrFail($id);
        abort_if($row->record_status === 'locked', 422, 'Template sudah di-lock.');
        $row->update($request->validate($this->templateRules()));
    }

    protected function templateRules(): array
    {
        return [
            'nama_template' => ['required', 'string', 'max:255'],
            'kanal' => ['required', Rule::in(['whatsapp', 'sms', 'email'])],
            'tahapan' => ['nullable', 'string', 'max:100'],
            'isi_template' => ['required', 'string'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    protected function sections(): array
    {
        return ['dashboard', 'pipeline', 'campaign', 'reminder', 'dokumen', 'piutang', 'target-komisi', 'template'];
    }

    protected function permissionsForSection(Request $request, string $section): array
    {
        $user = $request->user();

        return match ($section) {
            'pipeline' => [
                'canView' => $this->hasMarketingAccess($request, ['manajer_pimpro', 'owner'])
                    || $this->hasAnyMarketingPermission($request, ['marketing.pipeline.view', 'marketing.pipeline-report.view']),
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'canUnlock' => false,
            ],
            'campaign' => [
                'canView' => $this->hasMarketingAccess($request, ['pengawas'], 'marketing.campaign.manage'),
                'canCreate' => (bool) $user?->can('marketing.campaign.create') || $user?->can('marketing.campaign.manage'),
                'canUpdate' => (bool) $user?->can('marketing.campaign.update') || $user?->can('marketing.campaign.manage'),
                'canDelete' => (bool) $user?->can('marketing.campaign.delete') || $user?->can('marketing.campaign.manage'),
                'canLock' => (bool) $user?->can('marketing.campaign.lock') || $user?->can('marketing.campaign.manage'),
                'canUnlock' => (bool) $user?->can('marketing.campaign.unlock') || $user?->can('marketing.campaign.manage'),
            ],
            'reminder' => [
                'canView' => $this->hasMarketingAccess($request, $this->defaultRolesForSection('reminder'), 'marketing-reminder.view'),
                'canCreate' => (bool) $user?->can('marketing-reminder.create') || $user?->can('marketing.reminder.create') || $user?->can('marketing.reminder.manage'),
                'canUpdate' => (bool) $user?->can('marketing-reminder.update') || $user?->can('marketing.reminder.update') || $user?->can('marketing.reminder.manage'),
                'canDelete' => (bool) $user?->can('marketing-reminder.delete') || $user?->can('marketing.reminder.delete') || $user?->can('marketing.reminder.manage'),
                'canLock' => false,
                'canUnlock' => (bool) $user?->can('marketing-reminder.unlock') || $user?->can('marketing.reminder.unlock') || $user?->can('marketing.reminder.manage'),
            ],
            'dokumen' => [
                'canView' => $this->hasMarketingAccess($request, ['supervisor_marketing'], 'marketing.document-review.manage'),
                'canCreate' => false,
                'canUpdate' => (bool) $user?->can('marketing.document-review.manage'),
                'canDelete' => false,
                'canUnlock' => false,
            ],
            'piutang' => [
                'canView' => $this->hasMarketingAccess($request, ['owner', 'manajer_pimpro'], 'marketing.receivable.view'),
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'canUnlock' => false,
            ],
            'target-komisi' => [
                'canView' => $this->hasMarketingAccess($request, ['supervisor_marketing', 'manajer_pimpro'], 'marketing.target-commission.manage'),
                'canCreate' => (bool) $user?->can('marketing.target-commission.create') || $user?->can('marketing.target-commission.manage'),
                'canUpdate' => (bool) $user?->can('marketing.target-commission.update') || $user?->can('marketing.target-commission.manage'),
                'canDelete' => (bool) $user?->can('marketing.target-commission.delete') || $user?->can('marketing.target-commission.manage'),
                'canLock' => (bool) $user?->can('marketing.target-commission.lock') || $user?->can('marketing.target-commission.manage'),
                'canUnlock' => (bool) $user?->can('marketing.target-commission.unlock') || $user?->can('marketing.target-commission.manage'),
            ],
            'template' => [
                'canView' => $this->hasMarketingAccess($request, ['supervisor_marketing'], 'marketing.template.manage'),
                'canCreate' => (bool) $user?->can('marketing.template.create') || $user?->can('marketing.template.manage'),
                'canUpdate' => (bool) $user?->can('marketing.template.update') || $user?->can('marketing.template.manage'),
                'canDelete' => (bool) $user?->can('marketing.template.delete') || $user?->can('marketing.template.manage'),
                'canLock' => (bool) $user?->can('marketing.template.lock') || $user?->can('marketing.template.manage'),
                'canUnlock' => (bool) $user?->can('marketing.template.unlock') || $user?->can('marketing.template.manage'),
            ],
            default => [
                'canView' => true,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'canUnlock' => false,
            ],
        };
    }

    protected function defaultRolesForSection(string $section): array
    {
        return match ($section) {
            'pipeline' => ['manajer_pimpro', 'owner'],
            'campaign' => ['pengawas'],
            'reminder' => ['marketing', 'area_marketing', 'supervisor_marketing', 'manajer_pimpro', 'owner', 'pengawas'],
            'dokumen' => ['supervisor_marketing'],
            'piutang' => ['owner', 'manajer_pimpro'],
            'target-komisi' => ['supervisor_marketing', 'manajer_pimpro'],
            'template' => ['supervisor_marketing', 'manajer_pimpro', 'owner'],
            default => [],
        };
    }

    protected function permissionForSection(string $section): ?string
    {
        return match ($section) {
            'pipeline' => 'marketing.pipeline-report.view',
            'campaign' => 'marketing.campaign.manage',
            'reminder' => 'marketing-reminder.view',
            'dokumen' => 'marketing.document-review.manage',
            'piutang' => 'marketing.receivable.view',
            'target-komisi' => 'marketing.target-commission.manage',
            'template' => 'marketing.template.manage',
            default => null,
        };
    }

    protected function lockableModel(string $section, string $id)
    {
        $request = request();

        return match ($section) {
            'campaign' => MarketingCampaign::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->findOrFail($id),
            'template' => MarketingTemplate::query()->findOrFail($id),
            'target' => MarketingTarget::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                ->findOrFail($id),
            'commission' => MarketingCommission::query()
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
                ->findOrFail($id),
            default => abort(404),
        };
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    protected function customerQueryFor(Request $request): Builder
    {
        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request));
    }

    protected function sprQueryFor(Request $request): Builder
    {
        return Spr::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)));
    }

    protected function kprQueryFor(Request $request): Builder
    {
        return KprSubmission::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('spr', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('spr.detailRumah', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)));
    }

    protected function reminderQueryFor(Request $request): Builder
    {
        return MarketingReminder::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where(function (Builder $query) use ($request): void {
                $query->where('user_id', $request->user()?->id)
                    ->orWhereHas('costumer', fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id));
            }))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)));
    }

    protected function ensureCustomerCanBeUsed(Request $request, int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }

        abort_unless(
            Costumer::query()
                ->whereKey($customerId)
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->where('perumahan_id', $this->ensureActivePerumahan($request)))
                ->exists(),
            403,
        );
    }

    protected function ensureTeamUserCanBeUsed(Request $request, int $userId): void
    {
        abort_unless(
            User::query()
                ->whereKey($userId)
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing', 'supervisor_marketing']))
                ->exists(),
            422,
            'Petugas harus berasal dari tim Marketing.',
        );

        if (! $this->shouldScopeToActivePerumahan($request)) {
            return;
        }

        abort_unless(
            User::query()
                ->whereKey($userId)
                ->whereHas('perumahans', fn (Builder $query) => $query->whereKey($this->ensureActivePerumahan($request)))
                ->exists(),
            403,
        );
    }

    protected function ensureSprCanBeUsed(Request $request, int $sprId): void
    {
        if (! $this->shouldScopeToActivePerumahan($request)) {
            return;
        }

        abort_unless(
            Spr::query()
                ->whereKey($sprId)
                ->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $this->ensureActivePerumahan($request)))
                ->exists(),
            403,
        );
    }

    protected function title(string $section): string
    {
        return [
            'dashboard' => 'Dashboard Performa Marketing',
            'pipeline' => 'Pipeline Marketing',
            'campaign' => 'Campaign dan Promosi',
            'reminder' => 'Reminder Follow Up',
            'dokumen' => 'Validasi Berkas Customer',
            'piutang' => 'Jadwal Tagihan dan Kwitansi',
            'target-komisi' => 'Target, KPI, dan Komisi',
            'template' => 'Template Komunikasi',
        ][$section];
    }
}
