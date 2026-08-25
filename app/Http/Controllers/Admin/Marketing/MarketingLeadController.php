<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\MarketingActivityContact;
use App\Models\MarketingCampaign;
use App\Models\MarketingLead;
use App\Models\MarketingLeadSource;
use App\Models\MarketingVisit;
use App\Models\Perumahan;
use App\Models\SalesActivityLog;
use App\Models\User;
use App\Services\Marketing\MarketingLeadConversionService;
use App\Services\SalesActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarketingLeadController extends Controller
{
    public function __construct(private readonly SalesActivityLogger $logger) {}

    public function index(Request $request): Response
    {
        $this->allow($request, 'marketing-lead.view');
        $rows = $this->scope(MarketingLead::query(), $request)->with(['marketing:id,name', 'source:id,nama_sumber', 'perumahan:id,nama_perusahaan', 'customer:id,kode_costumer,nama'])
            ->when($request->query('stage'), fn (Builder $q, string $x) => $q->where('stage', $x))->when($request->query('ownership_type'), fn (Builder $q, string $x) => $q->where('ownership_type', $x))
            ->when($request->query('search'), fn (Builder $q, string $x) => $q->where(fn ($q) => $q->where('name', 'like', "%{$x}%")->orWhere('phone', 'like', "%{$x}%")))->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Marketing/Leads/Index', ['title' => 'Lead & Prospek', 'rows' => $rows, 'filters' => $request->only(['stage', 'ownership_type', 'search']), 'canCreate' => $request->user()?->can('marketing-lead.create')]);
    }

    public function create(Request $request): Response
    {
        $this->allow($request, 'marketing-lead.create');

        return Inertia::render('Admin/Marketing/Leads/Form', ['title' => 'Tambah Lead Langsung', 'options' => $this->options(), 'row' => null]);
    }

    public function edit(Request $request, MarketingLead $lead): Response
    {
        $this->allow($request, 'marketing-lead.update');
        $this->assertScope($request, $lead);
        abort_if($lead->stage === 'converted', 422, 'Lead yang sudah menjadi Customer diperbarui melalui master Customer.');

        return Inertia::render('Admin/Marketing/Leads/Form', ['title' => 'Edit Lead', 'options' => $this->options(), 'row' => $lead]);
    }

    public function update(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'marketing-lead.update');
        $this->assertScope($request, $lead);
        abort_if($lead->stage === 'converted', 422, 'Lead yang sudah menjadi Customer tidak dapat diedit dari halaman Lead.');
        $data = $this->data($request);
        $duplicate = $this->duplicateQuery($data['phone'] ?? null, $data['email'] ?? null, $data['identity_no'] ?? null, $lead->id)->first();
        if ($duplicate && ((int) ($data['duplicate_acknowledged_id'] ?? 0) !== $duplicate->id || empty($data['duplicate_override_reason']))) {
            return back()->withErrors(['duplicate_override_reason' => 'Perubahan menghasilkan data serupa. Pilih kandidat dan berikan alasan jika memang berbeda.'])->withInput();
        }
        unset($data['duplicate_acknowledged_id']);
        $data = $this->normalizePropertyInterest($data);
        $before = $lead->only(array_keys($data));
        $lead->update($data + ['possible_duplicate_lead_id' => $duplicate?->id, 'duplicate_checked_at' => now(), 'duplicate_checked_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->logger->record($request, $lead, 'lead_profile_updated', $lead->stage, $lead->stage, $data['duplicate_override_reason'] ?? 'Data dasar Lead diperbarui.', $before, $lead->only(array_keys($data)));

        return redirect()->route('admin.marketing.leads.show', $lead)->with('success', 'Data Lead diperbarui dan perubahan dicatat pada timeline.');
    }

    public function checkDuplicates(Request $request): JsonResponse
    {
        $this->allow($request, 'marketing-lead.view');
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'identity_no' => ['nullable', 'string', 'max:60'], 'exclude_id' => ['nullable', 'integer', 'exists:marketing_leads,id']]);

        return response()->json(['duplicates' => $this->duplicateQuery($data['phone'] ?? null, $data['email'] ?? null, $data['identity_no'] ?? null, isset($data['exclude_id']) ? (int) $data['exclude_id'] : null)
            ->with('marketing:id,name')->limit(10)->get()->map(fn (MarketingLead $lead) => [
                'id' => $lead->id, 'lead_no' => $lead->lead_no, 'name' => $lead->name, 'phone' => $lead->phone,
                'email' => $lead->email, 'stage' => $lead->stage, 'marketing' => $lead->marketing?->name,
                'last_activity_at' => $lead->last_activity_at?->toIso8601String(),
                'url' => route('admin.marketing.leads.show', $lead, false),
            ])->values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'marketing-lead.create');
        $data = $this->data($request);
        $duplicate = $this->duplicateQuery($data['phone'] ?? null, $data['email'] ?? null, $data['identity_no'] ?? null)->first();
        if ($duplicate && ((int) ($data['duplicate_acknowledged_id'] ?? 0) !== $duplicate->id || empty($data['duplicate_override_reason']))) {
            return back()->withErrors(['duplicate_override_reason' => 'Lead serupa ditemukan. Buka kandidat lama atau nyatakan sebagai data berbeda dengan alasan yang dapat diaudit.'])->withInput();
        }
        unset($data['duplicate_acknowledged_id']);
        $data = $this->normalizePropertyInterest($data);
        $lead = MarketingLead::query()->create($data + ['lead_no' => 'LD-'.now()->format('YmdHis').'-'.random_int(100, 999), 'ownership_type' => $request->user()?->hasAnyRole(['admin_sales', 'manager', 'owner', 'super_admin']) ? ($request->input('ownership_type', 'company')) : 'marketing', 'marketing_id' => $request->user()?->hasAnyRole(['marketing', 'area_marketing']) ? $request->user()->id : ($data['marketing_id'] ?? null), 'stage' => 'new', 'qualification_status' => 'unqualified', 'consent_at' => ($data['consent_status'] ?? 'unknown') === 'granted' ? now() : null, 'do_not_contact' => ($data['consent_status'] ?? 'unknown') === 'denied', 'possible_duplicate_lead_id' => $duplicate?->id, 'duplicate_checked_at' => now(), 'duplicate_checked_by' => $request->user()->id, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        if ($duplicate) {
            $this->logger->record($request, $lead, 'lead_duplicate_overridden', null, 'new', $data['duplicate_override_reason'], [], ['possible_duplicate_lead_id' => $duplicate->id]);
        }

        return redirect()->route('admin.marketing.leads.show', $lead)->with('success', 'Lead tersimpan. Lakukan follow-up dan kualifikasi sebelum menjadi Customer.');
    }

    public function show(Request $request, MarketingLead $lead): Response
    {
        $this->allow($request, 'marketing-lead.view');
        $this->assertScope($request, $lead);
        $lead->load(['marketing:id,name', 'adminSales:id,name', 'source:id,nama_sumber', 'perumahan:id,nama_perusahaan', 'branch:id,nama_cabang', 'campaign:id,nama_campaign,kanal', 'unit:id,kode_nlok,nomor_rumah,tipe_rumah,harga_jual', 'sourceVisit:id,visit_no,visit_type,location,check_in_latitude,check_in_longitude', 'customer:id,kode_costumer,nama,customer_stage', 'followUps.user:id,name', 'assignments.fromMarketing:id,name', 'assignments.toMarketing:id,name']);
        $logs = SalesActivityLog::query()->where('subject_type', $lead->getMorphClass())->where('subject_id', $lead->id)->with('user:id,name')->latest()->get();
        $duplicates = MarketingLead::query()->where('id', '!=', $lead->id)->whereNull('merged_into_lead_id')->where(function (Builder $query) use ($lead): void {
            $query->when($lead->phone, fn (Builder $query) => $query->orWhere('phone', $lead->phone))
                ->when($lead->email, fn (Builder $query) => $query->orWhere('email', $lead->email));
        })->limit(10)->get(['id', 'lead_no', 'name', 'phone', 'email', 'stage']);

        return Inertia::render('Admin/Marketing/Leads/Show', ['title' => 'Detail Lead', 'lead' => $lead, 'logs' => $logs, 'duplicates' => $duplicates, 'options' => $this->options(), 'canEdit' => $lead->stage !== 'converted' && (bool) $request->user()?->can('marketing-lead.update'), 'canQualify' => $request->user()?->can('marketing-lead.qualify'), 'canConvert' => $request->user()?->can('marketing-lead.convert'), 'canVerify' => $request->user()?->hasRole('super_admin') || $request->user()?->can('admin-sales.lead.verify'), 'canAssign' => $request->user()?->hasRole('super_admin') || $request->user()?->can('admin-sales.lead.assign')]);
    }

    public function stage(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'marketing-lead.qualify');
        $this->assertScope($request, $lead);
        $data = $request->validate(['stage' => ['required', Rule::in(['new', 'contacted', 'nurturing', 'qualified', 'postponed', 'lost'])], 'qualification_note' => ['required', 'string', 'min:5', 'max:2000'], 'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'preferred_payment_method' => ['nullable', Rule::in(['cash', 'cash_installment', 'kpr'])], 'interest_level' => ['required', Rule::in(['cold', 'warm', 'hot'])], 'budget_min' => ['nullable', 'numeric', 'min:0'], 'budget_max' => ['nullable', 'numeric', 'gte:budget_min'], 'purchase_timeline' => ['nullable', Rule::in(['0_3_months', '3_6_months', '6_12_months', 'over_12_months', 'unknown'])], 'decision_maker' => ['nullable', 'string', 'max:100'], 'financing_readiness' => ['nullable', Rule::in(['ready', 'needs_assessment', 'not_ready'])], 'needs_summary' => ['nullable', 'string', 'max:2000'], 'main_objection' => ['nullable', 'string', 'max:2000'], 'next_action_at' => ['nullable', 'date'], 'recycle_at' => ['nullable', 'date', 'after:today']]);
        if (in_array($data['stage'], ['contacted', 'nurturing', 'qualified'], true) && empty($data['next_action_at'])) {
            return back()->withErrors(['next_action_at' => 'Lead aktif wajib mempunyai jadwal tindakan berikutnya.']);
        }
        if ($data['stage'] === 'postponed' && empty($data['recycle_at'])) {
            return back()->withErrors(['recycle_at' => 'Lead yang ditunda wajib mempunyai tanggal aktivasi kembali.']);
        }
        $score = $this->qualificationScore($lead, $data);
        if ($data['stage'] === 'qualified' && ($score < 80 || ! $lead->phone)) {
            return back()->withErrors(['stage' => "Lead belum memenuhi standar Qualified. Skor saat ini {$score}/100; minimum 80 dan telepon wajib tersedia."]);
        }
        $old = $lead->stage;
        $isQualified = $data['stage'] === 'qualified';
        $lead->update($data + ['qualification_score' => $score, 'qualification_status' => $isQualified ? 'submitted' : ($data['stage'] === 'lost' ? 'disqualified' : 'in_review'), 'verification_status' => $isQualified ? 'pending' : $lead->verification_status, 'qualified_at' => $isQualified ? now() : null, 'qualified_by' => $isQualified ? $request->user()->id : null, 'submitted_for_verification_at' => $isQualified ? now() : null, 'submitted_for_verification_by' => $isQualified ? $request->user()->id : null, 'lost_reason' => $data['stage'] === 'lost' ? $data['qualification_note'] : null, 'last_activity_at' => now(), 'updated_by' => $request->user()->id]);
        $this->logger->record($request, $lead, $isQualified ? 'lead_submitted_for_verification' : 'lead_stage_changed', $old, $data['stage'], $data['qualification_note'], [], ['qualification_score' => $score]);

        return back()->with('success', 'Tahap CRM Lead diperbarui.');
    }

    public function convert(Request $request, MarketingLead $lead, MarketingLeadConversionService $service): RedirectResponse
    {
        $this->allow($request, 'marketing-lead.convert');
        $this->assertScope($request, $lead);
        $customer = $service->convert($lead, $request->user()->id);

        return redirect()->route('admin.marketing.calon-konsumen.show', $customer->id)->with('success', 'Lead berhasil dikonversi menjadi Customer draft siap reservasi.');
    }

    public function updateConsent(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'marketing-lead.qualify');
        $this->assertScope($request, $lead);
        $data = $request->validate([
            'consent_status' => ['required', Rule::in(['unknown', 'granted', 'denied'])],
            'consent_channels' => ['nullable', 'array'],
            'consent_channels.*' => [Rule::in(['phone', 'whatsapp', 'email'])],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        if ($data['consent_status'] === 'granted' && empty($data['consent_channels'])) {
            return back()->withErrors(['consent_channels' => 'Pilih minimal satu kanal komunikasi yang diizinkan.']);
        }

        $old = $lead->consent_status;
        $denied = $data['consent_status'] === 'denied';
        $lead->forceFill([
            'consent_status' => $data['consent_status'],
            'consent_channels' => $data['consent_status'] === 'granted' ? $data['consent_channels'] : [],
            'consent_at' => $data['consent_status'] === 'unknown' ? null : now(),
            'do_not_contact' => $denied,
            'next_action_at' => $denied ? null : $lead->next_action_at,
            'updated_by' => $request->user()?->id,
        ])->save();
        $this->logger->record($request, $lead, 'lead_consent_changed', $old, $data['consent_status'], $data['note'], [], ['channels' => $lead->consent_channels]);

        return back()->with('success', 'Consent komunikasi diperbarui dan dicatat pada timeline CRM.');
    }

    public function storeContact(Request $request, MarketingVisit $visit): RedirectResponse
    {
        $this->allow($request, 'marketing-activity-contact.create');
        $this->assertVisitScope($request, $visit);
        $data = $request->validate(['name' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'organization' => ['nullable', 'string', 'max:255'], 'outcome' => ['required', Rule::in(['no_contact', 'information_only', 'interested', 'request_follow_up', 'request_survey', 'not_interested'])], 'interest_level' => ['nullable', Rule::in(['cold', 'warm', 'hot'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        if (in_array($data['outcome'], ['interested', 'request_follow_up', 'request_survey'], true) && empty($data['phone']) && empty($data['email'])) {
            return back()->withErrors(['phone' => 'Prospek yang akan ditindaklanjuti harus memiliki telepon atau email.']);
        }
        $visit->contacts()->create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Kontak hasil aktivitas ditambahkan.');
    }

    public function contactToLead(Request $request, MarketingActivityContact $contact): RedirectResponse
    {
        $this->allow($request, 'marketing-activity-contact.convert');
        $contact->load('visit');
        $this->assertVisitScope($request, $contact->visit);
        abort_if($contact->marketing_lead_id, 422, 'Kontak ini sudah menjadi Lead.');
        abort_if(! $contact->phone && ! $contact->email, 422, 'Kontak harus mempunyai telepon atau email.');
        $this->assertNoDuplicate($contact->phone, $contact->email);
        $source = MarketingLeadSource::query()->where('nama_sumber', 'like', '%canvass%')->first();
        $lead = DB::transaction(function () use ($request, $contact, $source) {
            $visit = $contact->visit;
            $lead = MarketingLead::query()->create(['lead_no' => 'LD-'.now()->format('YmdHis').'-'.random_int(100, 999), 'name' => $contact->name ?: 'Prospek '.$contact->id, 'phone' => $contact->phone, 'email' => $contact->email, 'ownership_type' => 'marketing', 'source_channel' => 'canvassing', 'lead_source_id' => $source?->id, 'source_visit_id' => $visit->id, 'source_contact_id' => $contact->id, 'marketing_id' => $visit->marketing_id, 'perumahan_id' => $visit->perumahan_id, 'interest_level' => $contact->interest_level ?: 'warm', 'stage' => 'new', 'qualification_status' => 'unqualified', 'notes' => trim(($contact->notes ?: '')."\nDiperoleh dari {$visit->visit_no} di {$visit->location}."), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $contact->update(['marketing_lead_id' => $lead->id, 'converted_at' => now(), 'updated_by' => $request->user()->id]);

            return $lead;
        });

        return redirect()->route('admin.marketing.leads.show', $lead)->with('success', 'Kontak canvassing berhasil menjadi Lead Marketing tanpa input ulang.');
    }

    private function data(Request $r): array
    {
        return $r->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'identity_no' => ['nullable', 'string', 'max:60'], 'lead_source_id' => ['nullable', 'exists:marketing_lead_sources,id'], 'marketing_campaign_id' => ['nullable', 'exists:marketing_campaigns,id'], 'source_channel' => ['required', 'string', 'max:50'], 'consent_status' => ['required', Rule::in(['unknown', 'granted', 'denied'])], 'consent_channels' => ['nullable', 'array'], 'consent_channels.*' => [Rule::in(['phone', 'whatsapp', 'email'])], 'marketing_id' => ['nullable', 'exists:users,id'], 'perumahan_id' => ['nullable', 'exists:perumahans,id'], 'unit_type_interest' => ['nullable', 'string', 'max:255'], 'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'], 'interest_level' => ['required', Rule::in(['cold', 'warm', 'hot'])], 'preferred_payment_method' => ['nullable', Rule::in(['cash', 'cash_installment', 'kpr'])], 'notes' => ['nullable', 'string', 'max:2000'], 'duplicate_acknowledged_id' => ['nullable', 'integer', 'exists:marketing_leads,id'], 'duplicate_override_reason' => ['nullable', 'string', 'min:10', 'max:2000']]);
    }

    private function qualificationScore(MarketingLead $lead, array $data): int
    {
        $score = 0;
        $score += ! empty($lead->phone) ? 15 : 0;
        $score += ! empty($data['perumahan_id']) ? 15 : 0;
        $score += ! empty($data['preferred_payment_method']) ? 15 : 0;
        $score += ! empty($data['budget_max']) ? 15 : 0;
        $score += ! empty($data['purchase_timeline']) && $data['purchase_timeline'] !== 'unknown' ? 10 : 0;
        $score += ! empty($data['decision_maker']) ? 10 : 0;
        $score += ! empty($data['financing_readiness']) ? 10 : 0;
        $score += ! empty($data['needs_summary']) ? 10 : 0;

        return $score;
    }

    private function options(): array
    {
        return [
            'sources' => MarketingLeadSource::query()->orderBy('nama_sumber')->get(['id', 'nama_sumber']),
            'perumahans' => Perumahan::query()->finalized()->with('cabang:id,nama_cabang')->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan', 'cabang_id']),
            'units' => DetailRumah::query()->whereIn('status_penjualan', ['tersedia', 'available'])->orderBy('perumahan_id')->orderBy('tipe_rumah')->orderBy('nomor_rumah')->limit(1000)->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah', 'harga_jual']),
            'campaigns' => MarketingCampaign::query()->where('status', 'aktif')->orderBy('nama_campaign')->get(['id', 'perumahan_id', 'nama_campaign', 'kanal']),
            'marketings' => User::query()->whereHas('roles', fn ($query) => $query->whereIn('name', ['marketing', 'area_marketing']))->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function normalizePropertyInterest(array $data): array
    {
        $housing = ! empty($data['perumahan_id']) ? Perumahan::query()->findOrFail($data['perumahan_id']) : null;
        $unit = ! empty($data['detail_rumah_id']) ? DetailRumah::query()->findOrFail($data['detail_rumah_id']) : null;
        abort_if($unit && (int) $unit->perumahan_id !== (int) $housing?->id, 422, 'Unit tidak termasuk dalam perumahan yang dipilih.');
        abort_if($unit && ! in_array($unit->status_penjualan, ['tersedia', 'available'], true), 422, 'Unit yang diminati sudah tidak tersedia.');
        $campaign = ! empty($data['marketing_campaign_id']) ? MarketingCampaign::query()->findOrFail($data['marketing_campaign_id']) : null;
        abort_if($campaign?->perumahan_id && (int) $campaign->perumahan_id !== (int) $housing?->id, 422, 'Campaign tidak berlaku untuk perumahan yang dipilih.');
        $data['cabang_perusahaan_id'] = $housing?->cabang_id;
        $data['unit_type_interest'] = $unit?->tipe_rumah ?: ($data['unit_type_interest'] ?? null);

        return $data;
    }

    private function assertNoDuplicate(?string $phone, ?string $email): void
    {
        $duplicate = MarketingLead::query()->whereNotIn('stage', ['lost', 'converted'])->where(fn ($q) => $q->when($phone, fn ($q) => $q->orWhere('phone', $phone))->when($email, fn ($q) => $q->orWhere('email', $email)))->first();
        abort_if($duplicate, 422, 'Lead serupa sudah ada: '.$duplicate?->lead_no);
    }

    private function duplicateQuery(?string $phone, ?string $email, ?string $identityNo = null, ?int $ignoreId = null): Builder
    {
        $query = MarketingLead::query()->whereNull('merged_into_lead_id')->whereNotIn('stage', ['lost', 'converted'])->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId));
        if (! $phone && ! $email && ! $identityNo) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function (Builder $query) use ($phone, $email, $identityNo): void {
                $query->when($phone, fn (Builder $query) => $query->orWhere('phone', $phone))
                    ->when($email, fn (Builder $query) => $query->orWhere('email', $email))
                    ->when($identityNo, fn (Builder $query) => $query->orWhere('identity_no', $identityNo));
            });
    }

    private function scope(Builder $q, Request $r): Builder
    {
        return $r->user()?->hasAnyRole(['super_admin', 'owner', 'manager', 'supervisor_marketing', 'admin_sales']) ? $q : $q->where('marketing_id', $r->user()->id);
    }

    private function assertScope(Request $r, MarketingLead $lead): void
    {
        abort_unless($r->user()?->hasAnyRole(['super_admin', 'owner', 'manager', 'supervisor_marketing', 'admin_sales']) || $lead->marketing_id === $r->user()->id, 403);
    }

    private function assertVisitScope(Request $r, MarketingVisit $visit): void
    {
        abort_unless($r->user()?->hasAnyRole(['super_admin', 'owner', 'manager', 'supervisor_marketing', 'admin_sales']) || $visit->marketing_id === $r->user()->id, 403);
    }

    private function allow(Request $r, string $permission): void
    {
        abort_unless($r->user()?->hasRole('super_admin') || $r->user()?->can($permission), 403);
    }
}
