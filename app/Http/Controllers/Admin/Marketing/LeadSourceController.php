<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ChecksMarketingAccess;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\MarketingLeadSource;
use App\Services\ApprovalWorkflowService;
use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadSourceController extends Controller
{
    use ChecksMarketingAccess, HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $this->abortUnlessMarketingAccess($request, ['manajer_pimpro', 'supervisor_marketing'], 'marketing.lead-source.manage');
        $search = trim((string) $request->query('search', ''));
        $canManage = $this->hasAnyMarketingPermission($request, [
            'marketing.lead-source.manage',
            'marketing.lead-source.create',
            'marketing.lead-source.update',
            'marketing.lead-source.delete',
            'marketing.lead-source.unlock',
            'marketing-lead-source.create',
            'marketing-lead-source.update',
            'marketing-lead-source.delete',
            'marketing-lead-source.unlock',
        ]);

        $rows = MarketingLeadSource::query()
            ->withCount(['costumers' => fn (Builder $query) => $query
                ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))])
            ->with(['creator:id,name', 'updater:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('kode_sumber', 'like', "%{$search}%")
                    ->orWhere('nama_sumber', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            }))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MarketingLeadSource $source) => [
                'id' => $source->id,
                'kode_sumber' => $source->kode_sumber,
                'nama_sumber' => $source->nama_sumber,
                'kategori' => $source->kategori ?? '-',
                'keterangan' => $source->keterangan,
                'status' => $source->status,
                'jumlah_customer' => $source->costumers_count,
                'record_status' => $source->record_status ?? 'draft',
                'created_by_name' => $source->creator?->name ?? '-',
                'updated_by_name' => $source->updater?->name ?? '-',
                'can_edit' => ($source->record_status ?? 'draft') !== 'locked' && $this->hasAnyMarketingPermission($request, ['marketing.lead-source.update', 'marketing-lead-source.update', 'marketing.lead-source.manage']),
                'can_delete' => ($source->record_status ?? 'draft') !== 'locked' && $this->hasAnyMarketingPermission($request, ['marketing.lead-source.delete', 'marketing-lead-source.delete', 'marketing.lead-source.manage']),
                'can_lock' => (bool) auth()->check() && ($source->record_status ?? 'draft') !== 'locked',
                'can_unlock' => (($source->record_status ?? 'draft') === 'locked') && $this->currentUserCanManageLockedRecords(),
            ]);

        return Inertia::render('Admin/Marketing/LeadSource/Index', [
            'title' => 'Sumber Lead',
            'description' => 'Master asal calon customer: iklan, referral, spanduk, pameran, walk-in, dan channel lainnya.',
            'baseUrl' => route('admin.marketing.sumber-lead.index', absolute: false),
            'rows' => $rows,
            'filters' => ['search' => $search],
            'permissions' => [
                'canCreate' => $canManage || $this->hasAnyMarketingPermission($request, ['marketing.lead-source.create', 'marketing-lead-source.create']),
                'canUpdate' => $canManage || $this->hasAnyMarketingPermission($request, ['marketing.lead-source.update', 'marketing-lead-source.update']),
                'canDelete' => $canManage || $this->hasAnyMarketingPermission($request, ['marketing.lead-source.delete', 'marketing-lead-source.delete']),
                'canUnlock' => $canManage || $this->currentUserCanManageLockedRecords(),
            ],
            'options' => [
                'kategoriOptions' => $this->kategoriOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        ]);
    }

    public function store(Request $request, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $this->abortUnlessMarketingAccess($request, ['manajer_pimpro', 'supervisor_marketing'], 'marketing.lead-source.manage');
        $validated = $this->validatePayload($request);

        return $approvalWorkflow->create('marketing-lead-source', [
            ...$validated,
            'kode_sumber' => CodeGenerator::next(MarketingLeadSource::class, 'kode_sumber', 'LEAD'),
        ], function (array $payload): void {
            MarketingLeadSource::query()->create($payload);
        });
    }

    public function update(Request $request, string $id, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $this->abortUnlessMarketingAccess($request, ['manajer_pimpro', 'supervisor_marketing'], 'marketing.lead-source.manage');
        $source = MarketingLeadSource::query()->findOrFail($id);
        $this->abortIfLocked($source);
        $payload = $this->validatePayload($request);

        return $approvalWorkflow->update('marketing-lead-source', $source, $payload, function (MarketingLeadSource $row, array $payload): void {
            $row->update($payload);
        });
    }

    public function destroy(string $id, ApprovalWorkflowService $approvalWorkflow): RedirectResponse
    {
        $this->abortUnlessMarketingAccess(request(), ['manajer_pimpro', 'supervisor_marketing'], 'marketing.lead-source.manage');
        $source = MarketingLeadSource::query()->findOrFail($id);
        $this->abortIfLocked($source);

        return $approvalWorkflow->delete('marketing-lead-source', $source, function (MarketingLeadSource $row): void {
            $row->delete();
        });
    }

    protected function modelClass(): string
    {
        return MarketingLeadSource::class;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nama_sumber' => ['required', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
        ]);
    }

    private function kategoriOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Pilih Kategori'],
            ['value' => 'digital', 'label' => 'Digital'],
            ['value' => 'offline', 'label' => 'Offline'],
            ['value' => 'referral', 'label' => 'Referral'],
            ['value' => 'agen', 'label' => 'Agen / Broker'],
            ['value' => 'walk_in', 'label' => 'Walk-in'],
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'aktif', 'label' => 'Aktif'],
            ['value' => 'nonaktif', 'label' => 'Nonaktif'],
        ];
    }
}
