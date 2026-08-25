<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\MasterBank;
use App\Models\QualityUpgradeContract;
use App\Models\Spr;
use App\Services\ApprovalWorkflowService;
use App\Services\QualityUpgradeContractService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QualityUpgradeContractController extends Controller
{
    use HandlesCrudLock {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        $this->allow('view');
        $search = trim((string) $request->query('search', ''));
        $companyId = $request->integer('company_id') ?: null;
        $status = trim((string) $request->query('status', ''));
        $base = QualityUpgradeContract::query()
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->when($status, fn (Builder $query) => $query->where('business_status', $status))
            ->when($request->date('from'), fn (Builder $query, $date) => $query->whereDate('contract_date', '>=', $date))
            ->when($request->date('to'), fn (Builder $query, $date) => $query->whereDate('contract_date', '<=', $date));
        $summaryRows = (clone $base)->with('schedules')->get();
        $rows = $base
            ->with(['customer', 'unit.perumahan', 'company', 'items', 'schedules'])
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('contract_no', 'like', "%{$search}%")->orWhereHas('customer', fn ($q) => $q->where('nama', 'like', "%{$search}%"))))
            ->latest('contract_date')->paginate(12)->withQueryString()
            ->through(fn (QualityUpgradeContract $row) => $this->row($row));

        return Inertia::render('Admin/QualityUpgradeContracts/Index', [
            'title' => 'Kontrak Penambahan Mutu',
            'rows' => $rows,
            'filters' => ['search' => $search, 'company_id' => $companyId, 'status' => $status, 'from' => $request->query('from'), 'to' => $request->query('to')],
            'companies' => CabangPerusahaan::query()->finalized()->where('status', 'aktif')->orderBy('nama_cabang')->get(['id', 'nama_cabang']),
            'summary' => [
                'contracts' => $summaryRows->count(),
                'value' => (float) $summaryRows->sum('contract_value'),
                'paid' => (float) $summaryRows->sum(fn ($row) => $row->schedules->sum('paid_amount')),
                'outstanding' => (float) $summaryRows->sum(fn ($row) => max(0, $row->schedules->sum('amount') - $row->schedules->sum('paid_amount'))),
                'actual_cost' => (float) $summaryRows->sum(fn ($row) => $row->actual_material_cost + $row->actual_labor_cost + $row->actual_other_cost),
            ],
            'createUrl' => route('admin.quality-upgrades.create', absolute: false),
            'canCreate' => $this->can('create'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->allow('create');

        return $this->form(new QualityUpgradeContract(['contract_date' => today(), 'payment_method' => 'cash', 'warranty_days' => 30]), 'post', route('admin.quality-upgrades.store', absolute: false), $request);
    }

    public function store(Request $request, QualityUpgradeContractService $service): RedirectResponse
    {
        $this->allow('create');
        $contract = $service->save(new QualityUpgradeContract, $this->validated($request));

        return to_route('admin.quality-upgrades.index')->with('success', "Kontrak {$contract->contract_no} disimpan sebagai draft.");
    }

    public function edit(Request $request, QualityUpgradeContract $qualityUpgrade): Response
    {
        $this->allow('update');
        $this->abortIfLocked($qualityUpgrade);

        return $this->form($qualityUpgrade->load('items'), 'put', route('admin.quality-upgrades.update', $qualityUpgrade, absolute: false), $request);
    }

    public function update(Request $request, QualityUpgradeContract $qualityUpgrade, QualityUpgradeContractService $service): RedirectResponse
    {
        $this->allow('update');
        $this->abortIfLocked($qualityUpgrade);
        $service->save($qualityUpgrade, $this->validated($request));

        return to_route('admin.quality-upgrades.index')->with('success', 'Kontrak penambahan mutu diperbarui.');
    }

    public function destroy(QualityUpgradeContract $qualityUpgrade): RedirectResponse
    {
        $this->allow('delete');
        $this->abortIfLocked($qualityUpgrade);
        $qualityUpgrade->delete();

        return back()->with('success', 'Draft kontrak dihapus.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->allow('lock');

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        $this->allow('unlock');

        return $this->traitUnlock($id);
    }

    public function review(Request $request, QualityUpgradeContract $qualityUpgrade, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $approval = ApprovalRequest::query()->where(['model_type' => QualityUpgradeContract::class, 'model_id' => $qualityUpgrade->id, 'status' => ApprovalRequest::STATUS_PENDING])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string|max:1000'])['note']);

        return back()->with('success', $decision === 'approve' ? 'Tahap approval disetujui.' : 'Kontrak ditolak.');
    }

    public function print(Request $request, QualityUpgradeContract $qualityUpgrade)
    {
        $this->allow('print');
        abort_unless($qualityUpgrade->record_status === 'locked', 422, 'Kontrak harus difinalisasi sebelum dicetak.');

        return response()->view('documents.quality-upgrade-contract', ['contract' => $qualityUpgrade->load(['items', 'customer', 'unit.perumahan', 'company', 'bankAccount', 'schedules'])]);
    }

    public function show(QualityUpgradeContract $qualityUpgrade): Response
    {
        $this->allow('view');
        $qualityUpgrade->load(['customer', 'unit.perumahan', 'company', 'bankAccount', 'items', 'schedules', 'progresses.item', 'progresses.inspector', 'materialUsages.details', 'addenda', 'handover', 'defects.item']);
        $qualityUpgrade->progresses->each(fn ($progress) => $progress->setAttribute('evidence_url', $progress->evidence_path ? route('media', ['path' => $progress->evidence_path], false) : null));
        $handoverApproval = $qualityUpgrade->handover ? ApprovalRequest::query()->where(['model_type' => \App\Models\QualityUpgradeHandover::class, 'model_id' => $qualityUpgrade->handover->id])->latest()->first() : null;
        $estimatedCost = (float) $qualityUpgrade->items->sum(fn ($item) => $item->estimated_material_cost + $item->estimated_labor_cost + $item->estimated_other_cost);
        $actualCost = (float) $qualityUpgrade->actual_material_cost + $qualityUpgrade->actual_labor_cost + $qualityUpgrade->actual_other_cost;

        return Inertia::render('Admin/QualityUpgradeContracts/Show', [
            'title' => "Detail {$qualityUpgrade->contract_no}",
            'row' => $qualityUpgrade,
            'summary' => ['billed' => (float) $qualityUpgrade->schedules->sum('amount'), 'paid' => (float) $qualityUpgrade->schedules->sum('paid_amount'), 'outstanding' => max(0, (float) $qualityUpgrade->schedules->sum('amount') - (float) $qualityUpgrade->schedules->sum('paid_amount')), 'estimated_cost' => $estimatedCost, 'actual_cost' => $actualCost, 'estimated_margin' => (float) $qualityUpgrade->contract_value - $estimatedCost, 'actual_margin' => (float) $qualityUpgrade->contract_value - $actualCost],
            'progressUrl' => route('admin.quality-upgrades.progress.store', $qualityUpgrade, false),
            'cancelUrl' => route('admin.quality-upgrades.cancel', $qualityUpgrade, false),
            'indexUrl' => route('admin.quality-upgrades.index', absolute: false),
            'canProgress' => $qualityUpgrade->business_status === 'active' && $this->can('update'),
            'canCancel' => $qualityUpgrade->business_status === 'active' && $this->can('unlock'),
            'addenda' => $qualityUpgrade->addenda->map(function ($addendum) {
                $approval = ApprovalRequest::query()->where(['model_type' => \App\Models\QualityUpgradeAddendum::class, 'model_id' => $addendum->id])->latest()->first();
                return $addendum->toArray() + [
                    'can_lock' => $addendum->record_status === 'draft' && $this->canAddendum('lock'),
                    'can_unlock' => $addendum->record_status === 'locked' && $this->canAddendum('unlock'),
                    'can_delete' => $addendum->record_status === 'draft' && $this->canAddendum('delete'),
                    'can_review' => $approval?->status === ApprovalRequest::STATUS_PENDING && app(ApprovalWorkflowService::class)->canReview($approval),
                    'approval_stage' => $approval?->status === ApprovalRequest::STATUS_PENDING ? "Tahap {$approval->current_step}/{$approval->total_steps}" : $approval?->status,
                    'lock_url' => route('admin.quality-upgrades.addenda.lock', $addendum, false),
                    'unlock_url' => route('admin.quality-upgrades.addenda.unlock', $addendum, false),
                    'delete_url' => route('admin.quality-upgrades.addenda.destroy', $addendum, false),
                    'approve_url' => route('admin.quality-upgrades.addenda.review', [$addendum, 'approve'], false),
                    'reject_url' => route('admin.quality-upgrades.addenda.review', [$addendum, 'reject'], false),
                    'print_url' => route('admin.quality-upgrades.addenda.print', $addendum, false),
                ];
            }),
            'addendumUrl' => route('admin.quality-upgrades.addenda.store', $qualityUpgrade, false),
            'canAddendum' => in_array($qualityUpgrade->business_status, ['active', 'completed'], true) && $this->canAddendum('create'),
            'materialUsageUrl' => route('admin.material-usage.index', ['quality_upgrade_contract_id' => $qualityUpgrade->id], false),
            'materialRequestUrl' => route('admin.material-request.create', ['quality_upgrade_contract_id' => $qualityUpgrade->id], false),
            'canMaterialRequest' => (bool) (auth()->user()?->can('material-request.create') || auth()->user()?->hasRole('super_admin')),
            'canMaterialUsage' => (bool) (auth()->user()?->can('material-usage.create') || auth()->user()?->hasRole('super_admin')),
            'handover' => $qualityUpgrade->handover ? $qualityUpgrade->handover->toArray() + [
                'approval_stage' => $handoverApproval?->status === ApprovalRequest::STATUS_PENDING ? "Tahap {$handoverApproval->current_step}/{$handoverApproval->total_steps}" : $handoverApproval?->status,
                'can_lock' => $qualityUpgrade->handover->record_status === 'draft' && $this->canModule('quality-upgrade-handover', 'lock'),
                'can_unlock' => $qualityUpgrade->handover->record_status === 'locked' && $this->canModule('quality-upgrade-handover', 'unlock'),
                'can_review' => $handoverApproval?->status === ApprovalRequest::STATUS_PENDING && app(ApprovalWorkflowService::class)->canReview($handoverApproval),
                'lock_url' => route('admin.quality-upgrades.handover.lock', $qualityUpgrade->handover, false),
                'unlock_url' => route('admin.quality-upgrades.handover.unlock', $qualityUpgrade->handover, false),
                'approve_url' => route('admin.quality-upgrades.handover.review', [$qualityUpgrade->handover, 'approve'], false),
                'reject_url' => route('admin.quality-upgrades.handover.review', [$qualityUpgrade->handover, 'reject'], false),
            ] : null,
            'handoverUrl' => route('admin.quality-upgrades.handover.save', $qualityUpgrade, false),
            'canHandover' => (float) $qualityUpgrade->progress_percent >= 100 && $this->canModule('quality-upgrade-handover', 'create'),
            'defectUrl' => route('admin.quality-upgrades.defects.store', $qualityUpgrade, false),
            'canDefect' => $this->canModule('quality-upgrade-defect', 'create'),
            'defects' => $qualityUpgrade->defects->map(fn ($defect) => $defect->toArray() + ['evidence_url' => $defect->evidence_path ? route('media', ['path' => $defect->evidence_path], false) : null, 'resolve_url' => route('admin.quality-upgrades.defects.resolve', $defect, false), 'delete_url' => route('admin.quality-upgrades.defects.destroy', $defect, false), 'can_resolve' => $defect->status === 'open' && $this->canModule('quality-upgrade-defect', 'update'), 'can_delete' => $defect->status === 'open' && $this->canModule('quality-upgrade-defect', 'delete')]),
        ]);
    }

    public function storeProgress(Request $request, QualityUpgradeContract $qualityUpgrade, QualityUpgradeContractService $service): RedirectResponse
    {
        $this->allow('update');
        $data = $request->validate([
            'quality_upgrade_contract_item_id' => 'required|integer', 'report_date' => 'required|date',
            'progress_percent' => 'required|numeric|min:0|max:100',
            'work_status' => 'required|in:not_started,in_progress,inspection,completed,defect',
            'material_cost' => 'nullable|numeric|min:0', 'labor_cost' => 'nullable|numeric|min:0', 'other_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:3000', 'inspection_notes' => 'nullable|string|max:3000',
            'evidence' => 'nullable|image|max:6144',
        ]);
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')->store('quality-upgrade-progress', 'public');
        }
        unset($data['evidence']);
        $service->recordProgress($qualityUpgrade, $data);

        return back()->with('success', 'Laporan progres dan realisasi biaya tersimpan.');
    }

    public function cancel(Request $request, QualityUpgradeContract $qualityUpgrade, QualityUpgradeContractService $service): RedirectResponse
    {
        $this->allow('unlock');
        $service->cancel($qualityUpgrade, $request->validate(['reason' => 'required|string|min:10|max:2000'])['reason']);

        return to_route('admin.quality-upgrades.index')->with('success', 'Kontrak dibatalkan dan invoice/jurnal yang belum dibayar telah dibalik.');
    }

    protected function modelClass(): string { return QualityUpgradeContract::class; }
    protected function beforeUnlock(QualityUpgradeContract $contract): void { app(QualityUpgradeContractService::class)->reverseForUnlock($contract); }

    private function form(QualityUpgradeContract $row, string $method, string $actionUrl, Request $request): Response
    {
        $sprId = $request->integer('spr');
        $spr = $sprId ? Spr::query()->with('detailRumah.perumahan')->find($sprId) : null;
        if (! $row->exists && $spr) {
            $row->costumer_id = $spr->costumer_id;
            $row->detail_rumah_id = $spr->detail_rumah_id;
            $row->spr_id = $spr->id;
            $row->company_id = $spr->detailRumah?->perumahan?->cabang_id;
        }

        return Inertia::render('Admin/QualityUpgradeContracts/Form', [
            'title' => $row->exists ? 'Ubah Kontrak Penambahan Mutu' : 'Tambah Kontrak Penambahan Mutu',
            'row' => $row->exists ? $row->load('items') : $row,
            'method' => $method,
            'actionUrl' => $actionUrl,
            'indexUrl' => route('admin.quality-upgrades.index', absolute: false),
            'options' => $this->options(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'contract_date' => 'required|date',
            'costumer_id' => 'required|exists:costumers,id',
            'detail_rumah_id' => 'required|exists:detail_rumahs,id',
            'spr_id' => 'nullable|exists:sprs,id',
            'company_id' => 'required|exists:cabang_perusahaans,id',
            'master_bank_id' => ['nullable', Rule::exists('master_banks', 'id')->where(fn ($query) => $query->where('cabang_id', $request->input('company_id')))],
            'payment_method' => 'required|in:cash,installment',
            'discount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'planned_start_date' => 'nullable|date',
            'planned_finish_date' => 'nullable|date|after_or_equal:planned_start_date',
            'warranty_days' => 'required|integer|min:0|max:3650',
            'scope_notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'nullable|string|max:50',
            'items.*.name' => 'required|string|max:255',
            'items.*.specification' => 'nullable|string',
            'items.*.location' => 'nullable|string|max:255',
            'items.*.volume' => 'required|numeric|min:0.0001',
            'items.*.unit' => 'required|string|max:30',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.quality_upgrade_catalog_id' => 'nullable|exists:quality_upgrade_catalogs,id',
            'items.*.estimated_material_cost' => 'nullable|numeric|min:0',
            'items.*.estimated_labor_cost' => 'nullable|numeric|min:0',
            'items.*.estimated_other_cost' => 'nullable|numeric|min:0',
            'installments' => 'required|array|min:1',
            'installments.*.description' => 'required|string|max:255',
            'installments.*.due_date' => 'required|date',
            'installments.*.amount' => 'required|numeric|min:1',
        ]);
        $unit = DetailRumah::query()->with('currentOwnership')->findOrFail($data['detail_rumah_id']);
        abort_if($unit->currentOwnership && (int) $unit->currentOwnership->costumer_id !== (int) $data['costumer_id'], 422, 'Customer tidak sesuai dengan pemilik aktif unit.');
        abort_if((float) ($data['down_payment'] ?? 0) > (float) collect($data['installments'])->sum('amount'), 422, 'DP tidak boleh melebihi nilai kontrak.');
        abort_if((float) ($data['down_payment'] ?? 0) > 0 && abs((float) $data['installments'][0]['amount'] - (float) $data['down_payment']) > 0.01, 422, 'Nilai jadwal pertama harus sama dengan DP.');

        return $data;
    }

    private function options(): array
    {
        return [
            'customers' => Costumer::query()->orderBy('nama')->get(['id', 'nama', 'telepon'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama.' — '.$row->telepon]),
            'units' => DetailRumah::query()->finalized()->with(['perumahan:id,nama_perusahaan,cabang_id', 'currentOwnership:id,detail_rumah_id,costumer_id'])->orderBy('perumahan_id')->orderBy('kode_nlok')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->perumahan?->nama_perusahaan.' — '.$row->display_label, 'customer_id' => (string) ($row->currentOwnership?->costumer_id ?? ''), 'company_id' => (string) ($row->perumahan?->cabang_id ?? '')]),
            'sprs' => Spr::query()->where('status', Spr::STATUS_DISETUJUI)->with(['costumer:id,nama', 'detailRumah.perumahan:id,nama_perusahaan,cabang_id'])->latest()->limit(500)->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->kode_spr.' — '.$row->costumer?->nama.' — '.$row->detailRumah?->display_label, 'customer_id' => (string) $row->costumer_id, 'unit_id' => (string) $row->detail_rumah_id, 'company_id' => (string) ($row->detailRumah?->perumahan?->cabang_id ?? '')]),
            'companies' => CabangPerusahaan::query()->finalized()->where('status', 'aktif')->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_cabang]),
            'banks' => MasterBank::query()->finalized()->where('status', 'aktif')->orderBy('nama_bank')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_bank.' — '.$row->nomor_rekening.' — '.$row->nama_rekening, 'company_id' => (string) $row->cabang_id]),
            'catalogs' => \App\Models\QualityUpgradeCatalog::query()->where('is_active', true)->orderBy('name')->get()->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name, 'name' => $row->name, 'specification' => $row->specification, 'unit' => $row->unit, 'price' => (string) $row->standard_price, 'material_cost' => (string) $row->estimated_material_cost, 'labor_cost' => (string) $row->estimated_labor_cost, 'other_cost' => (string) $row->estimated_other_cost]),
        ];
    }

    private function row(QualityUpgradeContract $row): array
    {
        $approval = ApprovalRequest::query()->where(['model_type' => QualityUpgradeContract::class, 'model_id' => $row->id])->latest()->first();
        return [
            'id' => $row->id, 'contract_no' => $row->contract_no, 'date' => $row->contract_date?->format('d/m/Y'),
            'customer' => $row->customer?->nama, 'unit' => $row->unit?->display_label, 'housing' => $row->unit?->perumahan?->nama_perusahaan,
            'company' => $row->company?->nama_cabang, 'value' => $row->contract_value, 'payment_method' => $row->payment_method,
            'business_status' => $row->business_status, 'record_status' => $row->record_status, 'items_count' => $row->items->count(),
            'billed' => (float) $row->schedules->sum('amount'), 'paid' => (float) $row->schedules->sum('paid_amount'),
            'approval_status' => $approval?->status, 'approval_stage' => $approval?->status === ApprovalRequest::STATUS_PENDING ? "Tahap {$approval->current_step}/{$approval->total_steps}" : $approval?->status,
            'can_review' => $approval?->status === ApprovalRequest::STATUS_PENDING && app(ApprovalWorkflowService::class)->canReview($approval),
            'can_edit' => $row->record_status === 'draft' && $this->can('update'), 'can_delete' => $row->record_status === 'draft' && $this->can('delete'),
            'can_lock' => $row->record_status === 'draft' && $this->can('lock'), 'can_unlock' => $row->record_status === 'locked' && $this->can('unlock'),
            'edit_url' => route('admin.quality-upgrades.edit', $row, absolute: false), 'lock_url' => route('admin.quality-upgrades.lock', $row, absolute: false),
            'show_url' => route('admin.quality-upgrades.show', $row, absolute: false),
            'unlock_url' => route('admin.quality-upgrades.unlock', $row, absolute: false), 'print_url' => route('admin.quality-upgrades.print', $row, absolute: false),
            'approve_url' => $approval ? route('admin.quality-upgrades.review', [$row, 'approve'], absolute: false) : null,
            'reject_url' => $approval ? route('admin.quality-upgrades.review', [$row, 'reject'], absolute: false) : null,
            'receipt_url' => route('admin.customer-receipts.create', ['quality_upgrade_contract' => $row->id], absolute: false),
        ];
    }

    private function allow(string $action): void { abort_unless($this->can($action), 403); }
    private function can(string $action): bool { return (bool) (auth()->user()?->can("quality-upgrade.{$action}") || auth()->user()?->hasRole('super_admin')); }
    private function canAddendum(string $action): bool { return (bool) (auth()->user()?->can("quality-upgrade-addendum.{$action}") || auth()->user()?->hasRole('super_admin')); }
    private function canModule(string $module, string $action): bool { return (bool) (auth()->user()?->can("{$module}.{$action}") || auth()->user()?->hasRole('super_admin')); }
}
