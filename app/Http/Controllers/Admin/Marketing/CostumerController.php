<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marketing\StoreCostumerRequest;
use App\Http\Requests\Admin\Marketing\UpdateCostumerRequest;
use App\Models\Costumer;
use App\Models\CustomerUnitInterest;
use App\Models\DetailRumah;
use App\Models\MarketingCampaign;
use App\Models\MarketingLeadSource;
use App\Models\MarketingReferenceOption;
use App\Models\MarketingVisit;
use App\Services\ApprovalWorkflowService;
use App\Services\Marketing\MarketingLeadStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostumerController extends Controller
{
    use HandlesCrudLock, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'view');
        $search = trim((string) $request->query('search', ''));
        $administrativeGap = (string) $request->query('administrative_gap', '');

        $rows = Costumer::query()
            ->with(['leadSource:id,nama_sumber', 'campaign:id,nama_campaign', 'perumahan:id,nama_perusahaan'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->when($administrativeGap === 'unit', fn (Builder $query) => $query->whereDoesntHave('unitInterests'))
            ->when($administrativeGap === 'payment_method', fn (Builder $query) => $query->whereNull('preferred_payment_method'))
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
                'customer_stage' => $customer->customer_stage ?? 'legacy',
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
            'title' => 'Customer Terkonversi',
            'description' => 'Customer hanya berasal dari Lead Qualified. Lengkapi identitas dan administrasi sebelum reservasi atau booking fee.',
            'baseUrl' => route('admin.marketing.calon-konsumen.index', absolute: false),
            'columns' => [
                ['key' => 'kode_costumer', 'label' => 'Kode'],
                ['key' => 'perumahan', 'label' => 'Perumahan'],
                ['key' => 'sumber_lead', 'label' => 'Sumber Lead'],
                ['key' => 'campaign', 'label' => 'Campaign'],
                ['key' => 'customer_stage', 'label' => 'Tahap Customer'],
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
                'administrative_gap' => $administrativeGap,
            ],
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorizePermission($request, 'create');

        return redirect()->route('admin.marketing.leads.create')->with('warning', 'Customer tidak dibuat langsung. Input Lead terlebih dahulu, lakukan kualifikasi, lalu konversi menjadi Customer.');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'update');
        $row = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        $this->abortIfLocked($row);
        $payload = $row->loadMissing('unitInterests')->only(collect($this->fields())->pluck('name')->all());
        $payload['unit_interests'] = $this->unitInterestPayload($row);
        foreach (['tanggal_lahir', 'tanggal_lahir_pasangan'] as $field) {
            $payload[$field] = optional($row->{$field})->format('Y-m-d');
        }

        return Inertia::render('Admin/Marketing/Costumer/FormPage', [
            'title' => 'Edit Calon Konsumen '.$row->kode_costumer,
            'description' => 'Perbarui data customer pada halaman khusus.',
            'baseUrl' => route('admin.marketing.calon-konsumen.index', absolute: false),
            'actionUrl' => route('admin.marketing.calon-konsumen.update', $row->id, false),
            'method' => 'put', 'fields' => $this->fields(), 'options' => $this->formOptions(), 'row' => $payload,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorizePermission($request, 'view');
        $row = Costumer::query()->with([
            'leadSource:id,nama_sumber', 'campaign:id,nama_campaign', 'perumahan:id,nama_perusahaan',
            'unitInterests.unit:id,kode_nlok,nomor_rumah,tipe_rumah,harga_jual,status_penjualan', 'unitInterests.perumahan:id,nama_perusahaan',
            'followUps.user:id,name', 'leadActivities.user:id,name', 'visits', 'actionPlans', 'reminders', 'documentChecklists', 'housingReservations.unit:id,kode_nlok,nomor_rumah', 'sprs.detailRumah:id,kode_nlok,nomor_rumah', 'salesTransactions.housingUnit:id,kode_nlok,nomor_rumah', 'salesTransactions.customerReceipts',
        ])->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $q) => $q->where('assigned_marketing_id', $request->user()?->id))->when($this->shouldScopeToActivePerumahan($request), fn (Builder $q) => $this->scopeToActivePerumahan($q, $request))->findOrFail($id);
        $data = $row->only(collect($this->fields())->pluck('name')->all());
        $data = ['id' => $row->id, 'kode_costumer' => $row->kode_costumer, 'perumahan' => $row->perumahan?->nama_perusahaan ?? '-', 'sumber_lead' => $row->leadSource?->nama_sumber ?? '-', 'campaign' => $row->campaign?->nama_campaign ?? '-', 'record_status' => $row->record_status ?? 'draft', 'unit_interests' => $this->unitInterestPayload($row), ...$data];
        foreach (['tanggal_lahir', 'tanggal_lahir_pasangan'] as $field) {
            $data[$field] = optional($row->{$field})->format('d/m/Y');
        }

        return Inertia::render('Admin/Marketing/Costumer/Show', [
            'title' => 'Detail Calon Konsumen '.$row->kode_costumer,
            'baseUrl' => route('admin.marketing.calon-konsumen.index', absolute: false),
            'row' => $data,
            'fields' => $this->fields(),
            'canEdit' => ($row->record_status ?? 'draft') !== 'locked' && ($request->user()?->hasRole('super_admin') || $request->user()?->can('customer.update')),
            'quickActions' => [
                'followUpUrl' => $request->user()?->hasRole('super_admin') || $request->user()?->can('customer-follow-up.create')
                    ? route('admin.marketing.jejak-follow-up.create', ['costumer_id' => $row->id], false)
                    : null,
                'visitUrl' => $request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-visit.create')
                    ? route('admin.marketing.crm.create', ['resource' => 'visits', 'costumer_id' => $row->id], false)
                    : null,
                'phone' => $row->telepon,
            ],
            'timeline' => $this->customerTimeline($row),
        ]);
    }

    public function store(
        StoreCostumerRequest $request,
        MarketingLeadStatusService $leadStatus,
        ApprovalWorkflowService $approvalWorkflow,
    ): RedirectResponse {
        $this->authorizePermission($request, 'create');

        return redirect()->route('admin.marketing.leads.create')->with('warning', 'Pembuatan Customer langsung dinonaktifkan. Gunakan alur Lead → Qualified → Customer.');

        $this->ensureCustomerIsNotDuplicate($request);
        $this->ensureCampaignAllowed($request, $request->validated('marketing_campaign_id'));

        $validated = $request->validated();
        $unitInterests = $validated['unit_interests'] ?? [];
        unset($validated['unit_interests']);

        $payload = [
            ...$validated,
            'perumahan_id' => $this->propertyIdForWrite($request, $validated['perumahan_id'] ?? null),
            'kode_costumer' => $this->nextCustomerCode(),
            'status_lead' => 'lead_baru',
            'created_by' => $request->user()?->id,
            'assigned_marketing_id' => $this->shouldAutoAssignNewCustomer($request) ? $request->user()?->id : null,
            'assigned_at' => $this->shouldAutoAssignNewCustomer($request) ? now() : null,
            'lead_received_at' => now(),
            'first_response_due_at' => now()->addHours(2),
            'lead_priority' => $validated['lead_priority'] ?? 'normal',
            'updated_by' => $request->user()?->id,
        ];

        return $approvalWorkflow->create('customer', $payload, function (array $payload) use ($leadStatus, $unitInterests): void {
            $customer = Costumer::create($payload);
            $this->syncUnitInterests($customer, $unitInterests);

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
    ): RedirectResponse {
        $this->authorizePermission($request, 'update');
        $row = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->findOrFail($id);
        $this->ensureCustomerIsNotDuplicate($request, (int) $row->id);
        $this->abortIfLocked($row);
        if ($this->shouldScopeToActivePerumahan($request)) {
            $this->ensurePerumahanAllowed($request, (int) $row->perumahan_id);
        }
        $this->ensureCampaignAllowed($request, $request->validated('marketing_campaign_id'), (int) $row->perumahan_id);

        $validated = $request->validated();
        $unitInterests = $validated['unit_interests'] ?? [];
        unset($validated['unit_interests']);

        $payload = [
            ...$validated,
            'perumahan_id' => $this->shouldScopeToActivePerumahan($request) ? $row->perumahan_id : (($validated['perumahan_id'] ?? null) ?: $row->perumahan_id),
            'updated_by' => $request->user()?->id,
        ];

        return $approvalWorkflow->update('customer', $row, $payload, function (Costumer $row, array $payload) use ($unitInterests): void {
            $row->update($payload);
            $this->syncUnitInterests($row, $unitInterests);
        });
    }

    public function destroy(
        Request $request,
        string $id,
        ApprovalWorkflowService $approvalWorkflow,
    ): RedirectResponse {
        $this->authorizePermission($request, 'delete');
        $row = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('assigned_marketing_id', $request->user()?->id))
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
            ['name' => 'nama', 'label' => 'Nama Lengkap', 'type' => 'text', 'group' => 'profile', 'required' => true],
            ['name' => 'marketing_lead_source_id', 'label' => 'Sumber Lead', 'type' => 'select', 'optionsKey' => 'leadSourceOptions', 'group' => 'profile'],
            ['name' => 'marketing_campaign_id', 'label' => 'Campaign Promosi', 'type' => 'select', 'optionsKey' => 'campaignOptions', 'group' => 'profile'],
            ['name' => 'lead_priority', 'label' => 'Prioritas Lead', 'type' => 'select', 'optionsKey' => 'priorityOptions', 'group' => 'profile'],
            ['name' => 'interest_level', 'label' => 'Tingkat Minat', 'type' => 'select', 'optionsKey' => 'interestOptions', 'group' => 'profile'],
            ['name' => 'budget_min', 'label' => 'Anggaran Minimum', 'type' => 'currency', 'group' => 'profile'],
            ['name' => 'budget_max', 'label' => 'Anggaran Maksimum', 'type' => 'currency', 'group' => 'profile'],
            ['name' => 'preferred_payment_method', 'label' => 'Rencana Pembayaran', 'type' => 'select', 'optionsKey' => 'paymentOptions', 'group' => 'profile'],
            ['name' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'type' => 'select', 'optionsKey' => 'genderOptions', 'group' => 'profile', 'required' => true],
            ['name' => 'jenis_identitas', 'label' => 'Jenis Identitas', 'type' => 'select', 'optionsKey' => 'identityOptions', 'group' => 'profile', 'required' => true],
            ['name' => 'no_identitas', 'label' => 'No Identitas', 'type' => 'text', 'group' => 'profile', 'required' => true],
            ['name' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'type' => 'date', 'group' => 'profile'],
            ['name' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'status_perkawinan', 'label' => 'Status Perkawinan', 'type' => 'select', 'optionsKey' => 'maritalOptions', 'group' => 'profile', 'required' => true],
            ['name' => 'telepon', 'label' => 'Telepon', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'group' => 'profile'],
            ['name' => 'npwp', 'label' => 'NPWP', 'type' => 'text', 'group' => 'profile'],
            ['name' => 'file_identitas', 'label' => 'File Identitas', 'type' => 'text', 'group' => 'profile', 'placeholder' => 'Nama file/path identitas'],
            ['name' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'group' => 'profile', 'full' => true, 'required' => true],
            ['name' => 'keterangan', 'label' => 'Keterangan Customer', 'type' => 'textarea', 'group' => 'profile', 'full' => true],

            ['name' => 'pekerjaan', 'label' => 'Pekerjaan', 'type' => 'text', 'group' => 'pekerjaan'],
            ['name' => 'employment_category', 'label' => 'Kategori Pekerjaan', 'type' => 'select', 'optionsKey' => 'employmentOptions', 'group' => 'pekerjaan', 'required' => true],
            ['name' => 'penghasilan', 'label' => 'Penghasilan', 'type' => 'currency', 'group' => 'pekerjaan'],
            ['name' => 'pengeluaran_bulanan', 'label' => 'Pengeluaran Bulanan', 'type' => 'currency', 'group' => 'pekerjaan'],
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
            ['name' => 'pekerjaan_pasangan', 'label' => 'Pekerjaan Pasangan', 'type' => 'text', 'group' => 'pasangan'],
            ['name' => 'penghasilan_pasangan', 'label' => 'Penghasilan Pasangan', 'type' => 'currency', 'group' => 'pasangan'],
            ['name' => 'pengeluaran_bulanan_pasangan', 'label' => 'Pengeluaran Bulanan Pasangan', 'type' => 'currency', 'group' => 'pasangan'],
            ['name' => 'daftar_cicilan', 'label' => 'Daftar Cicilan Berjalan', 'type' => 'installments', 'group' => 'cicilan', 'full' => true],
            ['name' => 'unit_interests', 'label' => 'Unit / Perumahan yang Diminati', 'type' => 'unit_interests', 'group' => 'minat', 'full' => true],
        ];
    }

    private function formOptions(): array
    {
        return ['genderOptions' => $this->genderOptions(), 'identityOptions' => $this->identityOptions(), 'maritalOptions' => $this->maritalOptions(), 'employmentOptions' => $this->employmentOptions(), 'leadSourceOptions' => $this->leadSourceOptions(), 'campaignOptions' => $this->campaignOptions(), 'leadStatusOptions' => $this->leadStatusOptions(), 'priorityOptions' => $this->simpleOptions(['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak']), 'interestOptions' => MarketingReferenceOption::options('interest_level', $this->simpleOptions(['cold' => 'Dingin', 'warm' => 'Hangat', 'hot' => 'Panas'])), 'paymentOptions' => $this->simpleOptions(['cash' => 'Cash', 'cash_installment' => 'Cash Bertahap', 'kpr' => 'KPR']), 'unitOptions' => $this->unitOptions()];
    }

    private function prefillFromRequest(Request $request): ?array
    {
        $payload = array_filter([
            'nama' => $request->query('nama'),
            'telepon' => $request->query('telepon'),
            'alamat' => $request->query('alamat'),
            'keterangan' => $request->query('keterangan'),
            'perumahan_id' => $request->query('perumahan_id'),
        ], fn ($value) => $value !== null && $value !== '');

        if ($request->filled('visit_id')) {
            $visit = MarketingVisit::query()
                ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('marketing_id', $request->user()?->id))
                ->find($request->integer('visit_id'));

            if ($visit) {
                $payload = array_filter([
                    'nama' => $visit->contact_name,
                    'telepon' => $visit->contact_phone,
                    'alamat' => $visit->location,
                    'keterangan' => trim(($visit->objective ?: '').($visit->lead_source_note ? "\nSumber: {$visit->lead_source_note}" : '')),
                    'perumahan_id' => $visit->perumahan_id,
                    'nama_perusahaan' => $visit->organization_name,
                    'interest_level' => $visit->interest_level,
                ], fn ($value) => $value !== null && $value !== '') + $payload;
            }
        }

        return $payload ?: null;
    }

    private function simpleOptions(array $items): array
    {
        return collect($items)->map(fn (string $label, string $value) => compact('value', 'label'))->prepend(['value' => '', 'label' => 'Pilih'])->values()->all();
    }

    protected function employmentOptions(): array
    {
        return collect(['pns' => 'PNS / ASN', 'tni_polri' => 'TNI / Polri', 'bumn' => 'Pegawai BUMN/BUMD', 'pegawai_swasta' => 'Pegawai Swasta', 'wiraswasta' => 'Wiraswasta', 'profesional' => 'Profesional', 'pensiunan' => 'Pensiunan', 'lainnya' => 'Lainnya'])->map(fn ($label, $value) => compact('value', 'label'))->values()->all();
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

    protected function abortIfLocked(Model $model): void
    {
        abort_if(($model->record_status ?? 'draft') === 'locked', 422, 'Data sudah dikunci. Gunakan Unlock sebelum melakukan perubahan.');
    }

    protected function lockableQuery()
    {
        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing(request()), fn (Builder $query) => $query->where('assigned_marketing_id', request()->user()?->id))
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()));
    }

    protected function authorizeLockPermission(): void
    {
        $this->authorizePermission(request(), 'lock');
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

    private function authorizePermission(Request $request, string $action): void
    {
        $user = $request->user();
        abort_unless($user?->hasRole('super_admin') || $user?->can("customer.{$action}"), 403);
    }

    private function ensureCustomerIsNotDuplicate(Request $request, ?int $ignoreId = null): void
    {
        $identity = trim((string) $request->input('no_identitas'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $phone = preg_replace('/\D+/', '', (string) $request->input('telepon'));
        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $duplicate = Costumer::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where(function (Builder $query) use ($identity, $email, $phone): void {
                if ($identity !== '') {
                    $query->orWhere('no_identitas', $identity);
                }
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(telepon, '+', ''), '-', ''), ' ', ''), '(', '') LIKE ?", ['%'.substr($phone, -10)]);
                }
            })
            ->first(['id', 'kode_costumer', 'nama']);

        abort_if($duplicate, 422, "Data mirip sudah terdaftar sebagai {$duplicate?->kode_costumer} - {$duplicate?->nama}. Buka data lama atau ajukan pemindahan PIC.");
    }

    private function customerTimeline(Costumer $customer): array
    {
        return collect()
            ->concat($customer->followUps->map(fn ($row) => ['type' => 'follow_up', 'title' => 'Follow-up '.ucwords(str_replace('_', ' ', $row->metode_follow_up)), 'description' => $row->catatan, 'status' => $row->result_code ?: $row->status, 'at' => ($row->followed_up_at ?? $row->tanggal_follow_up)?->toISOString(), 'user' => $row->user?->name]))
            ->concat($customer->visits->map(fn ($row) => ['type' => 'visit', 'title' => 'Kunjungan customer', 'description' => $row->result ?: $row->objective, 'status' => $row->verification_status ?: $row->status, 'at' => ($row->finished_at ?? $row->started_at ?? $row->planned_at)?->toISOString()]))
            ->concat($customer->actionPlans->map(fn ($row) => ['type' => 'action', 'title' => $row->title, 'description' => $row->actual_result ?: $row->objective, 'status' => $row->status, 'at' => $row->start_at?->toISOString()]))
            ->concat($customer->leadActivities->map(fn ($row) => ['type' => $row->activity_type ?: 'status', 'title' => $row->title ?: 'Status lead: '.($row->status_to ?: '-'), 'description' => $row->note, 'status' => $row->status_to, 'at' => $row->activity_at?->toISOString(), 'user' => $row->user?->name, 'url' => $row->source_url]))
            ->concat($customer->reminders->map(fn ($row) => ['type' => 'reminder', 'title' => $row->judul, 'description' => $row->catatan, 'status' => $row->status, 'at' => $row->remind_at?->toISOString()]))
            ->concat($customer->documentChecklists->map(fn ($row) => ['type' => 'document', 'title' => 'Checklist dokumen '.ucwords($row->process_stage), 'description' => $row->completion_percentage.'% lengkap', 'status' => $row->validation_status, 'at' => $row->updated_at?->toISOString()]))
            ->concat($customer->housingReservations->map(fn ($row) => ['type' => 'reservation', 'title' => 'Reservasi unit '.trim(($row->unit?->kode_nlok ?? '').' '.($row->unit?->nomor_rumah ?? '')), 'description' => 'Booking fee Rp '.number_format((float) $row->booking_fee, 0, ',', '.'), 'status' => $row->status, 'at' => ($row->reserved_at ?? $row->created_at)?->toISOString()]))
            ->concat($customer->sprs->map(fn ($row) => ['type' => 'spr', 'title' => 'SPR '.$row->kode_spr, 'description' => 'Unit '.trim(($row->detailRumah?->kode_nlok ?? '').' '.($row->detailRumah?->nomor_rumah ?? '')), 'status' => $row->status, 'at' => ($row->tanggal_spr ?? $row->created_at)?->toISOString()]))
            ->concat($customer->salesTransactions->map(fn ($row) => ['type' => 'sale', 'title' => 'Transaksi Penjualan', 'description' => 'Nilai Rp '.number_format((float) $row->sale_price_snapshot, 0, ',', '.'), 'status' => $row->status, 'at' => ($row->closed_at ?? $row->approved_at ?? $row->created_at)?->toISOString()]))
            ->concat($customer->salesTransactions->flatMap(fn ($sale) => $sale->customerReceipts->map(fn ($row) => ['type' => 'payment', 'title' => 'Pembayaran Customer', 'description' => 'Rp '.number_format((float) $row->amount, 0, ',', '.'), 'status' => $row->status, 'at' => ($row->payment_date ?? $row->created_at)?->toISOString()])))
            ->filter(fn (array $item) => ! empty($item['at']))
            ->sortByDesc('at')->take(50)->values()->all();
    }

    private function unitOptions(): array
    {
        return DetailRumah::query()
            ->with('perumahan:id,nama_perusahaan')
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $this->scopeToActivePerumahan($query, request()))
            ->whereNotIn('status_penjualan', ['terjual', 'sold', 'ditempati', 'batal'])
            ->orderBy('perumahan_id')
            ->orderBy('kode_nlok')
            ->orderBy('nomor_rumah')
            ->limit(500)
            ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah', 'harga_jual', 'status_penjualan'])
            ->map(fn (DetailRumah $unit) => [
                'value' => (string) $unit->id,
                'perumahan_id' => (string) $unit->perumahan_id,
                'label' => trim(($unit->perumahan?->nama_perusahaan ? $unit->perumahan->nama_perusahaan.' - ' : '').$unit->display_label.' - '.($unit->tipe_rumah ?: 'Tipe belum diisi')),
                'price' => (float) ($unit->harga_jual ?? 0),
                'status' => $unit->status_penjualan,
            ])
            ->values()
            ->all();
    }

    private function syncUnitInterests(Costumer $customer, array $rows): void
    {
        $customer->unitInterests()->delete();
        collect($rows)->filter(fn (array $row) => filled($row['detail_rumah_id'] ?? null) || filled($row['perumahan_id'] ?? null) || filled($row['notes'] ?? null))->values()->each(function (array $row) use ($customer): void {
            $unit = ! empty($row['detail_rumah_id']) ? DetailRumah::query()->find($row['detail_rumah_id']) : null;
            $customer->unitInterests()->create([
                'detail_rumah_id' => $unit?->id,
                'perumahan_id' => $unit?->perumahan_id ?: ($row['perumahan_id'] ?? $customer->perumahan_id),
                'interest_level' => $row['interest_level'] ?? $customer->interest_level,
                'payment_plan' => $row['payment_plan'] ?? $customer->preferred_payment_method,
                'budget_min' => $row['budget_min'] ?? null,
                'budget_max' => $row['budget_max'] ?? null,
                'notes' => $row['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });
    }

    private function unitInterestPayload(Costumer $customer): array
    {
        return $customer->unitInterests->map(fn (CustomerUnitInterest $interest) => [
            'detail_rumah_id' => (string) ($interest->detail_rumah_id ?? ''),
            'perumahan_id' => (string) ($interest->perumahan_id ?? ''),
            'unit_label' => $interest->unit?->display_label,
            'perumahan' => $interest->perumahan?->nama_perusahaan,
            'interest_level' => $interest->interest_level,
            'payment_plan' => $interest->payment_plan,
            'budget_min' => $interest->budget_min,
            'budget_max' => $interest->budget_max,
            'notes' => $interest->notes,
        ])->values()->all();
    }
}
