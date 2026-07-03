<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Requests\Admin\Marketing\StoreCostumerRequest;
use App\Http\Requests\Admin\Marketing\UpdateCostumerRequest;
use App\Models\Costumer;
use App\Models\MarketingLeadSource;
use App\Models\MarketingCampaign;
use App\Services\ApprovalWorkflowService;
use App\Services\Marketing\MarketingLeadStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostumerController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = Costumer::query()
            ->with(['leadSource:id,nama_sumber', 'campaign:id,nama_campaign', 'perumahan:id,nama_perusahaan'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->orWhere('nama', 'like', "%{$search}%")
                        ->orWhere('kode_costumer', 'like', "%{$search}%")
                        ->orWhere('no_identitas', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('pekerjaan', 'like', "%{$search}%")
                        ->orWhere('status_lead', 'like', "%{$search}%")
                        ->orWhereHas('leadSource', fn (Builder $query) => $query->where('nama_sumber', 'like', "%{$search}%"))
                        ->orWhereHas('campaign', fn (Builder $query) => $query->where('nama_campaign', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Costumer $customer) => [
                'id' => $customer->id,
                'kode_costumer' => $customer->kode_costumer,
                'marketing_lead_source_id' => $customer->marketing_lead_source_id,
                'marketing_campaign_id' => $customer->marketing_campaign_id,
                'perumahan_id' => (string) ($customer->perumahan_id ?? ''),
                'perumahan' => $customer->perumahan?->nama_perusahaan ?? '-',
                'sumber_lead' => $customer->leadSource?->nama_sumber ?? '-',
                'campaign' => $customer->campaign?->nama_campaign ?? '-',
                'status_lead' => $customer->status_lead ?? 'lead_baru',
                'status_lead_label' => $this->labelFromOptions($customer->status_lead ?? 'lead_baru', $this->leadStatusOptions()),
                'nama' => $customer->nama,
                'jenis_kelamin' => $customer->jenis_kelamin,
                'jenis_identitas' => $customer->jenis_identitas,
                'no_identitas' => $customer->no_identitas,
                'tanggal_lahir' => optional($customer->tanggal_lahir)->format('Y-m-d'),
                'tempat_lahir' => $customer->tempat_lahir,
                'status_perkawinan' => $customer->status_perkawinan,
                'alamat' => $customer->alamat,
                'email' => $customer->email,
                'npwp' => $customer->npwp,
                'telepon' => $customer->telepon,
                'file_identitas' => $customer->file_identitas,
                'penghasilan' => $customer->penghasilan,
                'keterangan' => $customer->keterangan,
                'pekerjaan' => $customer->pekerjaan,
                'nama_perusahaan' => $customer->nama_perusahaan,
                'alamat_perusahaan' => $customer->alamat_perusahaan,
                'telepon_perusahaan' => $customer->telepon_perusahaan,
                'keterangan_perusahaan' => $customer->keterangan_perusahaan,
                'nama_lengkap_pasangan' => $customer->nama_lengkap_pasangan,
                'jenis_kelamin_pasangan' => $customer->jenis_kelamin_pasangan,
                'jenis_identitas_pasangan' => $customer->jenis_identitas_pasangan,
                'no_identitas_pasangan' => $customer->no_identitas_pasangan,
                'tanggal_lahir_pasangan' => optional($customer->tanggal_lahir_pasangan)->format('Y-m-d'),
                'tempat_lahir_pasangan' => $customer->tempat_lahir_pasangan,
                'penghasilan_display' => number_format((float) ($customer->penghasilan ?? 0), 0, ',', '.'),
                'record_status' => $customer->record_status ?? 'draft',
                'record_status_label' => ($customer->record_status ?? 'draft') === 'locked' ? 'Locked' : 'Draft',
                'created_at' => optional($customer->created_at)->format('d/m/Y H:i'),
                'updated_at' => optional($customer->updated_at)->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Marketing/Costumer/Index', [
            'title' => 'Calon Konsumen',
            'description' => 'Kelola data identitas, pekerjaan, dan pasangan customer sebelum masuk proses follow up atau SPR.',
            'baseUrl' => route('admin.marketing.calon-konsumen.index', absolute: false),
            'columns' => [
                ['key' => 'kode_costumer', 'label' => 'Kode'],
                ['key' => 'perumahan', 'label' => 'Perumahan'],
                ['key' => 'sumber_lead', 'label' => 'Sumber Lead'],
                ['key' => 'campaign', 'label' => 'Campaign'],
                ['key' => 'status_lead_label', 'label' => 'Status Lead'],
                ['key' => 'nama', 'label' => 'Nama'],
                ['key' => 'no_identitas', 'label' => 'No Identitas'],
                ['key' => 'telepon', 'label' => 'Telepon'],
                ['key' => 'pekerjaan', 'label' => 'Pekerjaan'],
                ['key' => 'penghasilan_display', 'label' => 'Penghasilan'],
                ['key' => 'record_status_label', 'label' => 'Lock'],
                ['key' => 'created_at', 'label' => 'Dibuat'],
                ['key' => 'updated_at', 'label' => 'Diupdate'],
            ],
            'fields' => $this->fields(),
            'rows' => $rows,
            'options' => [
                'genderOptions' => $this->genderOptions(),
                'identityOptions' => $this->identityOptions(),
                'maritalOptions' => $this->maritalOptions(),
                'leadSourceOptions' => $this->leadSourceOptions(),
                'campaignOptions' => $this->campaignOptions(),
                'leadStatusOptions' => $this->leadStatusOptions(),
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(
        StoreCostumerRequest $request,
        MarketingLeadStatusService $leadStatus,
        ApprovalWorkflowService $approvalWorkflow,
    ): RedirectResponse
    {
        $this->ensureCampaignAllowed($request, $request->validated('marketing_campaign_id'));

        $payload = [
            ...$request->validated(),
            'perumahan_id' => $this->propertyIdForWrite($request, $request->validated('perumahan_id')),
            'kode_costumer' => $this->nextCustomerCode(),
            'status_lead' => 'lead_baru',
            'created_by' => $this->shouldAutoAssignNewCustomer($request) ? $request->user()?->id : null,
            'updated_by' => $request->user()?->id,
        ];

        return $approvalWorkflow->create('customer', $payload, function (array $payload) use ($leadStatus): void {
            $customer = Costumer::create($payload);

            $leadStatus->markCustomer(
                $customer->id,
                MarketingLeadStatusService::LEAD_BARU,
                Costumer::class,
                $customer->id,
                'Customer baru dibuat.',
                true
            );
        });
    }

    public function update(
        UpdateCostumerRequest $request,
        string $id,
        ApprovalWorkflowService $approvalWorkflow,
    ): RedirectResponse
    {
        $row = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        $this->abortIfLocked($row);
        if ($this->shouldScopeToActivePerumahan($request)) {
            $this->ensurePerumahanAllowed($request, (int) $row->perumahan_id);
        }
        $this->ensureCampaignAllowed($request, $request->validated('marketing_campaign_id'), (int) $row->perumahan_id);

        $payload = [
            ...$request->validated(),
            'perumahan_id' => $this->shouldScopeToActivePerumahan($request) ? $row->perumahan_id : ($request->validated('perumahan_id') ?: $row->perumahan_id),
            'updated_by' => $request->user()?->id,
        ];

        return $approvalWorkflow->update('customer', $row, $payload, function (Costumer $row, array $payload): void {
            $row->update($payload);
        });
    }

    public function destroy(
        Request $request,
        string $id,
        ApprovalWorkflowService $approvalWorkflow,
    ): RedirectResponse
    {
        $row = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        $this->abortIfLocked($row);

        return $approvalWorkflow->delete('customer', $row, function (Costumer $row): void {
            $row->delete();
        });
    }

    protected function fields(): array
    {
        return [
            ['name' => 'nama', 'label' => 'Nama Lengkap', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'marketing_lead_source_id', 'label' => 'Sumber Lead', 'type' => 'select', 'optionsKey' => 'leadSourceOptions', 'group' => 'profile'],
            ['name' => 'marketing_campaign_id', 'label' => 'Campaign Promosi', 'type' => 'select', 'optionsKey' => 'campaignOptions', 'group' => 'profile'],
            ['name' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'type' => 'select', 'optionsKey' => 'genderOptions', 'group' => 'profile'],
            ['name' => 'jenis_identitas', 'label' => 'Jenis Identitas', 'type' => 'select', 'optionsKey' => 'identityOptions', 'group' => 'profile'],
            ['name' => 'no_identitas', 'label' => 'No Identitas', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'type' => 'date', 'group' => 'profile'],
            ['name' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'status_perkawinan', 'label' => 'Status Perkawinan', 'type' => 'select', 'optionsKey' => 'maritalOptions', 'group' => 'profile'],
            ['name' => 'telepon', 'label' => 'Telepon', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'group' => 'profile'],
            ['name' => 'npwp', 'label' => 'NPWP', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'file_identitas', 'label' => 'File Identitas', 'type' => 'text', 'group' => 'profile', 'placeholder' => 'Nama file/path identitas'],
            ['name' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'group' => 'profile', 'full' => true],
            ['name' => 'keterangan', 'label' => 'Keterangan Customer', 'type' => 'textarea', 'group' => 'profile', 'full' => true],

            ['name' => 'pekerjaan', 'label' => 'Pekerjaan', 'type' => 'text', 'group' => 'pekerjaan'],
            ['name' => 'penghasilan', 'label' => 'Penghasilan', 'type' => 'currency', 'group' => 'pekerjaan'],
            ['name' => 'nama_perusahaan', 'label' => 'Nama Perusahaan', 'type' => 'text', 'group' => 'pekerjaan'],
            ['name' => 'telepon_perusahaan', 'label' => 'Telepon Perusahaan', 'type' => 'text', 'group' => 'pekerjaan'],
            ['name' => 'alamat_perusahaan', 'label' => 'Alamat Perusahaan', 'type' => 'textarea', 'group' => 'pekerjaan', 'full' => true],
            ['name' => 'keterangan_perusahaan', 'label' => 'Keterangan Perusahaan', 'type' => 'textarea', 'group' => 'pekerjaan', 'full' => true],

            ['name' => 'nama_lengkap_pasangan', 'label' => 'Nama Lengkap Pasangan', 'type' => 'text', 'group' => 'pasangan'],
            ['name' => 'jenis_kelamin_pasangan', 'label' => 'Jenis Kelamin Pasangan', 'type' => 'select', 'optionsKey' => 'genderOptions', 'group' => 'pasangan'],
            ['name' => 'jenis_identitas_pasangan', 'label' => 'Jenis Identitas Pasangan', 'type' => 'select', 'optionsKey' => 'identityOptions', 'group' => 'pasangan'],
            ['name' => 'no_identitas_pasangan', 'label' => 'No Identitas Pasangan', 'type' => 'text', 'group' => 'pasangan'],
            ['name' => 'tanggal_lahir_pasangan', 'label' => 'Tanggal Lahir Pasangan', 'type' => 'date', 'group' => 'pasangan'],
            ['name' => 'tempat_lahir_pasangan', 'label' => 'Tempat Lahir Pasangan', 'type' => 'text', 'group' => 'pasangan'],
        ];
    }

    protected function genderOptions(): array
    {
        return [
            ['value' => 'laki-laki', 'label' => 'Laki-laki'],
            ['value' => 'perempuan', 'label' => 'Perempuan'],
        ];
    }

    protected function identityOptions(): array
    {
        return [
            ['value' => 'ktp', 'label' => 'KTP'],
            ['value' => 'sim', 'label' => 'SIM'],
            ['value' => 'passport', 'label' => 'Passport'],
        ];
    }

    protected function maritalOptions(): array
    {
        return [
            ['value' => 'belum menikah', 'label' => 'Belum Menikah'],
            ['value' => 'menikah', 'label' => 'Menikah'],
            ['value' => 'cerai', 'label' => 'Cerai'],
        ];
    }

    protected function leadSourceOptions(): array
    {
        return MarketingLeadSource::query()
            ->where('status', 'aktif')
            ->orderBy('nama_sumber')
            ->get(['id', 'nama_sumber'])
            ->map(fn (MarketingLeadSource $source) => [
                'value' => (string) $source->id,
                'label' => $source->nama_sumber,
            ])
            ->prepend(['value' => '', 'label' => 'Pilih Sumber Lead'])
            ->values()
            ->all();
    }

    protected function leadStatusOptions(): array
    {
        return MarketingLeadStatusService::statusOptions();
    }

    protected function campaignOptions(): array
    {
        return MarketingCampaign::query()
            ->with('perumahan:id,nama_perusahaan')
            ->whereIn('status', ['draft', 'aktif'])
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->orderBy('nama_campaign')
            ->get(['id', 'perumahan_id', 'nama_campaign'])
            ->map(fn (MarketingCampaign $campaign) => [
                'value' => (string) $campaign->id,
                'label' => $this->shouldScopeToActivePerumahan(request())
                    ? $campaign->nama_campaign
                    : $campaign->nama_campaign.' - '.($campaign->perumahan?->nama_perusahaan ?? 'Tanpa Perumahan'),
            ])
            ->prepend(['value' => '', 'label' => 'Tanpa Campaign'])
            ->values()
            ->all();
    }

    protected function ensureCampaignAllowed(Request $request, mixed $campaignId, ?int $fallbackPerumahanId = null): void
    {
        if (! $campaignId) {
            return;
        }

        $perumahanId = $this->propertyIdForWrite($request, $request->validated('perumahan_id') ?: $fallbackPerumahanId);

        abort_unless(
            MarketingCampaign::query()
                ->whereKey($campaignId)
                ->where('perumahan_id', $perumahanId)
                ->exists(),
            403,
        );
    }

    protected function propertyIdForWrite(Request $request, mixed $requestedId = null): int
    {
        if ($this->shouldScopeToActivePerumahan($request)) {
            return $this->ensureActivePerumahan($request);
        }

        return (int) ($requestedId ?: $this->activePerumahanId($request) ?: $this->ensureActivePerumahan($request));
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

    protected function nextCustomerCode(): string
    {
        $lastId = (int) (Costumer::withTrashed()->max('id') ?? 0) + 1;

        return 'CST-'.str_pad((string) $lastId, 5, '0', STR_PAD_LEFT);
    }

    protected function modelClass(): string
    {
        return Costumer::class;
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    protected function shouldAutoAssignNewCustomer(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin', 'manager', 'manajer_pimpro']);
    }
}
