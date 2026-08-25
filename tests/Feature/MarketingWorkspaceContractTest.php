<?php

use App\Models\CustomerDocumentChecklist;
use App\Models\MarketingActionPlan;
use App\Models\MarketingActivityContact;
use App\Models\MarketingEvaluation;
use App\Models\MarketingLead;
use App\Models\MarketingScoreSetting;
use App\Models\MarketingVisit;
use App\Support\ApprovalResources;
use App\Support\MarketingPermissions;
use Illuminate\Support\Facades\Route;

it('registers the operational marketing approval resources and routes', function () {
    $resources = ApprovalResources::modules();

    expect($resources['marketing-visit']['model'])->toBe(MarketingVisit::class)
        ->and($resources['marketing-action-plan']['model'])->toBe(MarketingActionPlan::class)
        ->and($resources['customer-document-checklist']['model'])->toBe(CustomerDocumentChecklist::class)
        ->and(Route::has('admin.marketing.crm.lock'))->toBeTrue()
        ->and(Route::has('admin.marketing.crm.unlock'))->toBeTrue()
        ->and(Route::has('admin.marketing.crm.review'))->toBeTrue();
    expect($resources['marketing-evaluation']['model'])->toBe(MarketingEvaluation::class)
        ->and($resources['marketing-score-setting']['model'])->toBe(MarketingScoreSetting::class)
        ->and(Route::has('admin.marketing.evaluations.review'))->toBeTrue();
});

it('keeps individual marketing ownership checks on CRM writes', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));

    expect($controller)
        ->toContain('assertPayloadWithinMarketingScope')
        ->toContain("where('assigned_marketing_id', \$request->user()?->id)")
        ->toContain("\$payload['marketing_id'] = \$request->user()?->id")
        ->toContain('Customer tidak berada dalam penugasan Marketing Anda.');
});

it('gives marketing a focused operational permission contract', function () {
    $seeder = file_get_contents(database_path('seeders/RolePermissionSeeder.php'));
    $permissions = MarketingPermissions::operational();

    expect($seeder)
        ->toContain("Role::findOrCreate('marketing', 'web')->givePermissionTo")
        ->and($permissions)->toContain('marketing-visit.create')
        ->toContain('marketing-survey.create')
        ->toContain('marketing-action-plan.create')
        ->toContain('customer-document-checklist.create')
        ->toContain('cash-sale.view')
        ->toContain('cash-sale.lock')
        ->not->toContain('marketing-survey.unlock')
        ->not->toContain('marketing.owner-report.view')
        ->not->toContain('marketing.lead-distribution.manage');
});

it('keeps team monitoring and survey unlock behind managerial permissions', function () {
    $tools = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingToolsController.php'));
    $survey = file_get_contents(app_path('Http/Controllers/Admin/Marketing/SurveyScheduleController.php'));
    $migration = file_get_contents(database_path('migrations/2026_08_04_000001_harden_marketing_monitoring_and_survey_permissions.php'));

    expect($tools)
        ->toContain("'monitoring-aktivitas' => 'marketing.activity.view-all'")
        ->and($survey)
        ->toContain('protected function lockableQuery()')
        ->toContain("where('marketing_id', request()->user()?->id)")
        ->toContain("authorizePermission(request(), 'lock')")
        ->and($migration)
        ->toContain("foreach (['marketing', 'area_marketing'] as \$roleName)")
        ->toContain('revokePermissionTo([$surveyUnlock, $monitorAll, $cashUnlock])');
});

it('protects cash sales with explicit permissions and scoped lock queries', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CashSaleController.php'));

    expect($controller)
        ->toContain('HandlesCrudLock::lock as private traitLock')
        ->toContain('HandlesCrudLock::unlock as private traitUnlock')
        ->toContain("authorizePermission(\$request, 'view')")
        ->toContain("authorizePermission(\$request, 'update')")
        ->toContain('protected function lockableQuery()')
        ->toContain('can("cash-sale.{$action}")');
});

it('uses separate form routes for daily and operational marketing input', function () {
    $operations = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingOperationsController.php'));
    $operationsPage = file_get_contents(resource_path('js/Pages/Admin/Marketing/Operations/Index.jsx'));

    expect(Route::has('admin.marketing.operasional.create'))->toBeTrue()
        ->and(Route::has('admin.marketing.operasional.edit'))->toBeTrue()
        ->and(Route::has('admin.marketing.jadwal-survey.result'))->toBeTrue()
        ->and($operations)
        ->toContain("Inertia::render('Admin/Marketing/Operations/FormPage'")
        ->toContain("'options' => \$this->formOptions(\$request, \$type)")
        ->and($operationsPage)
        ->toContain('`${baseUrl}/create${type ? `?type=${type}` : ""}`')
        ->not->toContain('<CrudModal');
});

it('provides a daily workspace timeline deduplication and automatic lead reminder', function () {
    $dashboard = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingController.php'));
    $customer = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CostumerController.php'));
    $operations = file_get_contents(app_path('Services/Marketing/MarketingOperationsService.php'));

    expect($dashboard)->toContain('protected function today')
        ->and($customer)->toContain('ensureCustomerIsNotDuplicate')
        ->and($customer)->toContain('customerTimeline')
        ->and($operations)->toContain("'judul' => 'Hubungi lead baru'")
        ->and($operations)->toContain('addHours(2)')
        ->toContain('MarketingVisit::query()')
        ->toContain('MarketingActionPlan::query()');
});

it('loads the customer document master from the model namespace', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingController.php'));

    expect($controller)
        ->toContain('use App\\Models\\DokumenCostumer;')
        ->toContain('DokumenCostumer::query()->count()');
});

it('keeps the marketing dashboard focused despite supporting property permissions', function () {
    $dashboard = file_get_contents(app_path('Services/RoleDashboardService.php'));

    expect($dashboard)
        ->toContain('$isMarketing = $user->hasAnyRole')
        ->toContain('$isMarketing => [')
        ->toContain('if (! $isMarketing && ($isExecutive || $isFinance')
        ->toContain('if (! $isMarketing && ! $isWarehouse')
        ->toContain('if ($isMarketing || $isExecutive');
});

it('turns the marketing home into an auditable daily workdesk', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingController.php'));
    $page = file_get_contents(resource_path('js/Pages/Admin/Marketing/Index.jsx'));

    expect($controller)
        ->toContain("'quick_actions' => \$quickActions")
        ->toContain("'permission' => 'marketing-visit.create'")
        ->toContain("'activities' => ".'$activities')
        ->toContain("'Catat Kunjungan'")
        ->toContain("'Catat Aktivitas Lain'")
        ->and($page)
        ->toContain('Buku Kerja Harian Marketing')
        ->toContain('Aktivitas Hari Ini')
        ->toContain('Monitoring tim');
});

it('keeps marketing sidebar ordered by the operational workflow', function () {
    $sidebar = file_get_contents(resource_path('js/Sidebar/sidebarMenu.jsx'));

    expect($sidebar)
        ->toContain('1. Cari Prospek & Kelola Lead')
        ->toContain('2. Survey & Persiapan Transaksi')
        ->toContain('3. Transaksi Penjualan')
        ->toContain('7. Tools Marketing')
        ->toContain('9. Laporan & Monitoring')
        ->toContain('10. Manajer & Master')
        ->toContain('Aktivitas Lapangan / Canvassing')
        ->toContain('Lead & Prospek')
        ->toContain('Customer Terkonversi')
        ->toContain('Jadwal & Hasil Survey Unit')
        ->toContain('marketing.activity.view-all');
});

it('keeps pipeline navigation and backend permissions synchronized', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingOperationsController.php'));
    $sidebar = file_get_contents(resource_path('js/Sidebar/sidebarMenu.jsx'));

    expect($sidebar)->toContain('marketing.pipeline.view')->toContain('marketing.pipeline-report.view')
        ->and($controller)->toContain("['marketing.pipeline.view', 'marketing.pipeline-report.view']");
});

it('keeps the marketing dashboard payload bounded for daily use', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingController.php'));
    $survey = file_get_contents(app_path('Http/Controllers/Admin/Marketing/SurveyScheduleController.php'));
    $crm = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));

    expect($controller)
        ->toContain("summary(Request \$request, string \$slug = 'marketing')")
        ->toContain("if (\$slug === 'marketing')")
        ->toContain('private function authorizeSection')
        ->toContain("'permission' => 'marketing-survey.create'")
        ->toContain("->latest('id')->limit(30)->get()")
        ->toContain("->sortByDesc('time')->take(40)->values()")
        ->and($survey)
        ->toContain('->limit(150)')
        ->toContain('->limit(400)')
        ->and($crm)
        ->toContain("->latest('id')->limit(200)->get");
});

it('captures visit evidence and gps for manager monitoring', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));
    $migration = file_get_contents(database_path('migrations/2026_08_02_000005_complete_marketing_visit_and_activity_audit.php'));
    $monitoring = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingToolsController.php'));

    expect($controller)
        ->toContain('public function executeVisit')
        ->toContain("store('marketing/visit-'.".'$phase')
        ->toContain("'started_at' => now()")
        ->toContain("'finished_at' => now()")
        ->and($migration)->toContain("\$table->decimal('check_in_latitude', 10, 7)")
        ->toContain("\$table->decimal('check_out_latitude', 10, 7)")
        ->and($monitoring)->toContain('marketingVisits as visit_count')
        ->toContain('marketingActionPlans as other_activity_count')
        ->toContain("'recent_activities' => ".'$recentActivities');
});

it('keeps the daily canvassing form aligned with its required validation', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/CrmWorkspace/FormPage.jsx'));

    expect($controller)
        ->toContain("'result:textarea:Hasil Aktivitas'")
        ->toContain("'interest_level:select:Tingkat Minat Prospek:interest'")
        ->toContain("'lead_source_note:select:Sumber / Kanal Aktivitas (opsional):activity_source'")
        ->toContain("'interest_level' => ['required', Rule::in(array_column(\$this->referenceOptions('interest_level'")
        ->toContain("\$payload['planned_at'] = now()")
        ->toContain("'planned_at' => \$resource === 'visits' ? now()->format('Y-m-d\\\\TH:i') : null")
        ->and($form)
        ->toContain('const requiredField = (name)')
        ->toContain('readOnly={isDailyVisit && name === "planned_at"}')
        ->toContain('canvassing door to door, brosur, event, komunitas, atau partner');
});

it('links document checklist uploads into the customer document repository', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CrmWorkspaceController.php'));
    $requirements = file_get_contents(app_path('Services/CustomerDocumentRequirementService.php'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/CrmWorkspace/FormPage.jsx'));
    $show = file_get_contents(resource_path('js/Pages/Admin/Marketing/CrmWorkspace/Show.jsx'));

    expect($controller)
        ->toContain("'items.*.file_upload' => ['nullable', 'file'")
        ->toContain('storeCustomerDocumentFromChecklist')
        ->toContain("store('customer-repository/'.")
        ->toContain('CustomerDocument::query()->create')
        ->toContain('mergeChecklistItems')
        ->toContain("where('costumer_id', \$payload['costumer_id'])")
        ->toContain('checklistDocument')
        ->toContain('admin.marketing.checklist-document')
        ->and($form)
        ->toContain('Upload Berkas Customer')
        ->toContain('Repository Dokumen Customer')
        ->toContain('file_upload')
        ->toContain('File baru:')
        ->toContain('Sudah upload:')
        ->and($show)
        ->toContain('file_name')
        ->toContain('Lihat Berkas');
    expect($requirements)
        ->toContain('public function forChecklist(Costumer $customer)')
        ->toContain("collect(['spr', \$spr->metode_pembayaran])")
        ->toContain('bank_credit_product_id')
        ->toContain('requirement_item_id');
});

it('bounds heavy marketing list payloads and throttles reminder synchronization', function () {
    $operations = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingOperationsController.php'));
    $reminders = file_get_contents(app_path('Services/Marketing/MarketingOperationsService.php'));

    expect($operations)
        ->toContain("if (in_array(\$section, ['dashboard', 'reminder'], true))")
        ->toContain('->limit(500)')
        ->toContain('->limit(300)')
        ->toContain('->limit(150)')
        ->toContain('->limit(200)')
        ->and($reminders)
        ->toContain("'marketing-reminder-sync:'")
        ->toContain('Cache::add($syncKey, true, now()->addMinutes(5))')
        ->toContain('->limit(200)');
});

it('separates field activity contacts leads and converted customers', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $conversion = file_get_contents(app_path('Services/Marketing/MarketingLeadConversionService.php'));
    $customer = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CostumerController.php'));
    $reservation = file_get_contents(app_path('Services/HousingReservationService.php'));

    expect(Route::has('admin.marketing.leads.index'))->toBeTrue()
        ->and(Route::has('admin.marketing.field-activities.contacts.store'))->toBeTrue()
        ->and(Route::has('admin.marketing.field-activities.contacts.convert'))->toBeTrue()
        ->and($controller)->toContain('Kontak canvassing berhasil menjadi Lead Marketing tanpa input ulang')
        ->and($conversion)->toContain("stage !== 'qualified'")->toContain("'customer_stage' => 'pre_reservation'")
        ->and($reservation)->toContain('Reservasi hanya dapat dibuat untuk Customer hasil konversi Lead yang sudah Qualified')
        ->toContain("'customer_stage' => 'booking_fee_paid'")
        ->toContain("'customer_stage' => 'spr_approved'")
        ->and($customer)->toContain('Customer tidak dibuat langsung')
        ->and(class_exists(MarketingLead::class))->toBeTrue()
        ->and(class_exists(MarketingActivityContact::class))->toBeTrue();
});

it('allows a direct lead when its detailed source is not known yet', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingLeadController.php'));
    $form = file_get_contents(resource_path('js/Pages/Admin/Marketing/Leads/Form.jsx'));

    expect($controller)->toContain("'lead_source_id' => ['nullable', 'exists:marketing_lead_sources,id']")
        ->and($form)->toContain('Sumber Lead (opsional)')
        ->toContain('Kanal Lead *')
        ->toContain('Belum diketahui');
});
