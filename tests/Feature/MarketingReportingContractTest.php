<?php

use App\Models\CustomerUnitInterest;
use App\Models\MarketingReferenceOption;
use App\Services\Marketing\MarketingReportService;
use App\Support\ApprovalResources;
use Illuminate\Support\Facades\Route;

it('publishes all nine connected marketing reports and exports', function () {
    expect(MarketingReportService::TYPES)->toHaveCount(9)
        ->toHaveKeys(['activities', 'follow-ups', 'visits', 'inactive-customers', 'pipeline', 'conversion', 'targets', 'cancellations', 'performance'])
        ->and(Route::has('admin.marketing.reports.show'))->toBeTrue()
        ->and(Route::has('admin.marketing.reports.export'))->toBeTrue();
});

it('registers configurable marketing references with approval lifecycle', function () {
    expect(ApprovalResources::modules()['marketing-reference-option']['model'])->toBe(MarketingReferenceOption::class)
        ->and(Route::has('admin.marketing.references.index'))->toBeTrue()
        ->and(Route::has('admin.marketing.references.create'))->toBeTrue()
        ->and(Route::has('admin.marketing.references.edit'))->toBeTrue()
        ->and(Route::has('admin.marketing.references.lock'))->toBeTrue()
        ->and(Route::has('admin.marketing.references.unlock'))->toBeTrue()
        ->and(Route::has('admin.marketing.references.review'))->toBeTrue();
});

it('keeps report data scoped and exports permission protected', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingReportController.php'));
    $service = file_get_contents(app_path('Services/Marketing/MarketingReportService.php'));
    expect($controller)->toContain("can('marketing-report.view')")->toContain("can('marketing-report.export')")
        ->and($service)->toContain("can('marketing.activity.view-all')")->toContain('paginate(25)')->toContain('limit(5000)');
});

it('aggregates target realization before mapping report rows', function () {
    $service = file_get_contents(app_path('Services/Marketing/MarketingReportService.php'));

    expect($service)
        ->toContain('private function targetMetrics(Collection $targets)')
        ->toContain('private function metricIndex(Collection $rows)')
        ->toContain('COUNT(*) as total')
        ->toContain('COALESCE(SUM(sale_price_snapshot), 0) as value_total')
        ->and(substr($service, strpos($service, 'private function targetRow')))
        ->not->toContain('Costumer::query()')
        ->not->toContain('SalesTransaction::query()');
});

it('detects the required overdue marketing conditions', function () {
    $service = file_get_contents(app_path('Services/Marketing/MarketingOperationsService.php'));
    expect($service)->toContain('MarketingLead::query()')
        ->toContain("whereNotIn('stage', ['converted', 'lost'])")
        ->toContain('stale-lead')
        ->toContain('laporan_kunjungan')
        ->toContain('dokumen_belum_lengkap')
        ->toContain('reservasi_jatuh_tempo')
        ->toContain('spr_belum_diproses');
});

it('requires an assignment reason and records the assignment history', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingToolsController.php'));
    expect($controller)->toContain("'reason' => ['required', 'string', 'min:5', 'max:1000']")
        ->toContain("'marketing_lead_id' => ['required', 'exists:marketing_leads,id']")
        ->toContain("'marketing_lead_id' => \$lead->id")
        ->toContain('MarketingLeadAssignment::query()->create')->toContain("'from_marketing_id'")->toContain("'to_marketing_id'");
});

it('uses leads rather than customers for hot aging and distribution tools', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingToolsController.php'));
    $tools = file_get_contents(resource_path('js/Pages/Admin/Marketing/Tools/Index.jsx'));
    $canvassing = file_get_contents(resource_path('js/Pages/Admin/Marketing/CrmWorkspace/FormPage.jsx'));

    expect(substr($controller, strpos($controller, 'protected function hotLeadData'), strpos($controller, 'protected function leaderboardData') - strpos($controller, 'protected function hotLeadData')))
        ->toContain('$this->leadQuery($request)')
        ->toContain('MarketingLead $lead')
        ->not->toContain('$this->customerQuery($request)')
        ->and($tools)->toContain('marketing_lead_id')->toContain('{ key: "lead", label: "Lead" }')
        ->and($canvassing)->toContain('Tahap 1 · Pencarian prospek / canvassing')->not->toContain('Tahap 3 · Kunjungan lapangan');
});

it('counts actual marketing leads in activity monitoring and leaderboard', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingToolsController.php'));

    expect($controller)->toContain("'marketingLeads as lead_count'")
        ->toContain("'type' => 'Lead Baru'")
        ->not->toContain("'costumers as lead_count'")
        ->not->toContain("'type' => 'Calon Customer Baru'");
});

it('enforces qualification scoring admin sales verification consent and a unified lead timeline', function () {
    $leadController = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $conversion = file_get_contents(app_path('Services/Marketing/MarketingLeadConversionService.php'));
    $show = file_get_contents(resource_path('js/Pages/Admin/Marketing/Leads/Show.jsx'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/Leads/Form.jsx'));

    expect($leadController)->toContain('qualificationScore')->toContain('lead_submitted_for_verification')
        ->toContain("'qualification_status' => \$isQualified ? 'submitted'")
        ->and($conversion)->toContain("\$lead->verification_status !== 'verified'")
        ->and($show)->toContain('Checklist Kualifikasi Lead')->toContain('Timeline CRM')->toContain('Gerbang Verifikasi Admin Sales')
        ->and($form)->toContain('consent_status')->toContain('consent_channels');
});

it('supports duplicate merge recycle and automated crm escalation', function () {
    $adminSales = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    $queue = file_get_contents(app_path('Services/AdminSalesWorkQueueService.php'));
    $show = file_get_contents(resource_path('js/Pages/Admin/Marketing/Leads/Show.jsx'));

    expect($adminSales)->toContain('function mergeLead')->toContain('function recycleLead')
        ->toContain("'lead_merged'")->toContain("'lead_recycled'")
        ->and($queue)->toContain('lead-verification-')->toContain('lead-recycle-')
        ->and($show)->toContain('Kandidat Duplikat')->toContain('Recycle Lead');
});

it('captures customer unit interest as a first class marketing signal', function () {
    $customer = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CostumerController.php'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/Costumer/FormPage.jsx'));

    expect(Route::has('admin.marketing.calon-konsumen.store'))->toBeTrue()
        ->and(class_exists(CustomerUnitInterest::class))->toBeTrue()
        ->and($customer)->toContain('syncUnitInterests')->toContain('unitOptions')->toContain("'unit_interests'")
        ->and($form)->toContain('UnitInterestFields')->toContain('Tambah Minat Unit');
});

it('allows visit activity for canvassing or partner contact before a customer exists', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));
    $model = file_get_contents(app_path('Models/MarketingVisit.php'));
    $report = file_get_contents(app_path('Services/Marketing/MarketingReportService.php'));

    expect($controller)->toContain("'costumer_id' => ['nullable', 'exists:costumers,id']")
        ->toContain("'contact_name' => [Rule::requiredIf(fn () => ! request()->filled('costumer_id'))")
        ->toContain("'canvassing' => 'Canvassing'")
        ->toContain("'evidence_path' => ['required', 'image', 'max:8192']")
        ->toContain('$payload[\'verification_status\'] = \'pending_review\'')
        ->toContain('if ($row->costumer_id)')
        ->and($model)->toContain("'contact_name'")->toContain("'organization_name'");
    expect($report)->toContain('Customer/Prospek')->toContain('organization_name')->toContain('lead_source_note');
});

it('keeps unit interest in customer detail while pipeline monitoring uses leads', function () {
    $show = file_get_contents(resource_path('js/Pages/Admin/Marketing/Costumer/Show.jsx'));
    $report = file_get_contents(app_path('Services/Marketing/MarketingReportService.php'));

    expect($show)->toContain('Minat Unit')->toContain('unit_interests')
        ->and($report)->toContain('MarketingLead::query()')->toContain('Tahap Pipeline');
});

it('keeps marketing reports filterable by source campaign unit and location map', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingReportController.php'));
    $service = file_get_contents(app_path('Services/Marketing/MarketingReportService.php'));
    $page = file_get_contents(resource_path('js/Pages/Admin/Marketing/Reports/Index.jsx'));

    expect($controller)
        ->toContain('lead_source_id')
        ->toContain('campaign_id')
        ->toContain('payment_plan')
        ->toContain('unit_id')
        ->toContain('visitTypes')
        ->toContain('visitStatuses')
        ->and($service)
        ->toContain('scopeCustomerFilters')
        ->toContain('https://www.google.com/maps?q=')
        ->toContain("'Map'")
        ->and($page)
        ->toContain('Sumber lead')
        ->toContain('Campaign')
        ->toContain('Unit diminati');
});

it('protects marketing pages from unsafe layout title access', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js/Pages/Admin/Marketing')));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'jsx') {
            expect(file_get_contents($file->getPathname()))->not->toContain('page.props.title');
        }
    }
});

it('keeps marketing report and evaluation filters safe when option payloads are partial', function () {
    $reports = file_get_contents(resource_path('js/Pages/Admin/Marketing/Reports/Index.jsx'));
    $owner = file_get_contents(resource_path('js/Pages/Admin/Marketing/CrmOwnerReport/Index.jsx'));
    $evaluationIndex = file_get_contents(resource_path('js/Pages/Admin/Marketing/Evaluations/Index.jsx'));
    $evaluationForm = file_get_contents(resource_path('js/Pages/Admin/Marketing/Evaluations/Form.jsx'));

    expect($reports)
        ->toContain('options = {}')
        ->toContain('...(options.marketings || [])')
        ->toContain('...(options.customers || [])')
        ->toContain('...(options.statuses || [])')
        ->and($owner)
        ->toContain('options = {}')
        ->toContain('statuses = []')
        ->toContain('...(options.sources || [])')
        ->and($evaluationIndex)
        ->toContain('options = {}')
        ->toContain('...(options.perumahans || [])')
        ->and($evaluationForm)
        ->toContain('options = {}')
        ->toContain('...(options.marketings || [])');
});

it('keeps dashboard mutations and operational/report scopes behind the same access boundary', function () {
    $dashboard = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingController.php'));
    $operations = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingOperationsController.php'));
    $report = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingReportController.php'));
    $ownerReport = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmOwnerReportController.php'));
    $evaluation = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingEvaluationController.php'));

    expect(strpos($dashboard, "authorizeSection(\$request, 'marketing')"))
        ->toBeLessThan(strpos($dashboard, 'syncAutomaticReminders'))
        ->and($operations)->toContain("can('dashboard.view')")
        ->and($report)->toContain('use ScopesActivePerumahan')
        ->toContain('$activePerumahanId')
        ->and($ownerReport)->toContain('use ScopesActivePerumahan')
        ->toContain('followUpMetrics')
        ->and($evaluation)->toContain('ensureEvaluationScope')
        ->toContain('ensureActivePerumahan');
});

it('lets field activity contacts become separate marketing leads', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));
    $leads = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $show = file_get_contents(resource_path('js/Pages/Admin/Marketing/CrmWorkspace/Show.jsx'));

    expect($controller)
        ->toContain('contactStoreUrl')
        ->toContain("\$data['convert_customer_url'] = null")
        ->toContain('map_url')
        ->and($leads)
        ->toContain('contactToLead')
        ->toContain("'source_channel' => 'canvassing'")
        ->and($show)
        ->toContain('Tambah Kontak Hasil Aktivitas')
        ->toContain('Jadikan Lead')
        ->toContain('Buka Map');
});

it('enforces communication consent and prevents follow up from bypassing qualification', function () {
    $leadController = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $followUp = file_get_contents(app_path('Http/Controllers/Admin/Marketing/FollowUpController.php'));
    $queue = file_get_contents(app_path('Services/AdminSalesWorkQueueService.php'));
    $reminders = file_get_contents(app_path('Services/Marketing/MarketingOperationsService.php'));
    $adminSales = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));

    expect($leadController)->toContain('updateConsent')
        ->toContain('lead_consent_changed')
        ->and($followUp)->toContain('Lead menolak komunikasi')
        ->toContain('Kanal komunikasi ini tidak termasuk consent Lead')
        ->toContain("default => \$lead->stage === 'qualified' ? 'qualified' : 'nurturing'")
        ->and($queue)->toContain("where('do_not_contact', false)")
        ->and($reminders)->toContain("where('do_not_contact', false)")
        ->and($adminSales)->toContain('Consent harus diperbarui terlebih dahulu sebelum recycle.');
});

it('requires an auditable duplicate decision for direct lead entry', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/Leads/Form.jsx'));
    $migration = file_get_contents(database_path('migrations/2026_08_10_000015_add_duplicate_review_to_marketing_leads.php'));

    expect($controller)->toContain('checkDuplicates')->toContain('lead_duplicate_overridden')
        ->toContain('duplicate_override_reason')
        ->and($form)->toContain('Periksa duplikasi Lead')->toContain('Buka Lead lama')
        ->and($migration)->toContain('possible_duplicate_lead_id')->toContain('duplicate_checked_by');
});

it('provides separate lead editing dependent property interest and a duplicate center', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/Leads/Form.jsx'));
    $duplicates = file_get_contents(resource_path('js/Pages/Admin/AdminSales/Leads/Duplicates.jsx'));
    $conversion = file_get_contents(app_path('Services/Marketing/MarketingLeadConversionService.php'));

    expect($controller)->toContain('public function edit')->toContain('normalizePropertyInterest')
        ->toContain('Unit tidak termasuk dalam perumahan yang dipilih')
        ->and($form)->toContain('Tipe unit diminati')->toContain('Unit diminati')->toContain('Campaign promosi')
        ->and($duplicates)->toContain('Keputusan data berbeda dari input langsung')
        ->and($conversion)->toContain('CustomerUnitInterest')->toContain('marketing_campaign_id');
});
