<?php

use App\Models\SalesActivityLog;
use App\Models\SalesWorkItem;
use App\Services\AdminSalesLeadIntakeService;
use Illuminate\Support\Facades\Route;

it('publishes a separate admin sales workspace and work item pages', function () {
    expect(Route::has('admin.admin-sales.dashboard'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.monitoring'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.work-items.index'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.work-items.create'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.work-items.show'))->toBeTrue()
        ->and(class_exists(SalesWorkItem::class))->toBeTrue()
        ->and(class_exists(SalesActivityLog::class))->toBeTrue();
});

it('keeps admin reviews separate from marketing authored content', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    $monitoring = file_get_contents(resource_path('js/Pages/Admin/AdminSales/Monitoring.jsx'));
    $workItem = file_get_contents(resource_path('js/Pages/Admin/AdminSales/WorkItems/Show.jsx'));
    expect($controller)->toContain("'admin_review_status'")
        ->toContain("'admin_review_note'")
        ->toContain('tanpa mengubah laporan Marketing')
        ->not->toContain("'catatan' => \$data")
        ->not->toContain("'result' => \$data")
        ->and($monitoring)->not->toContain('window.prompt')->not->toContain('router.post')
        ->and($workItem)->not->toContain('window.prompt')->toContain('useForm')
        ->and(Route::has('admin.admin-sales.lead.verify.form'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.review.form'))->toBeTrue()
        ->and(file_exists(resource_path('js/Pages/Admin/AdminSales/Review.jsx')))->toBeTrue();
});

it('requires explicit permissions for every admin sales mutation', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    expect($controller)->toContain('admin-sales.lead.verify')
        ->toContain('admin-sales.follow-up.review')
        ->toContain('admin-sales.visit.review')
        ->toContain('admin-sales.work-item.create')
        ->toContain('admin-sales.work-item.update');
});

it('connects company lead creation assignment response and first contact SLA', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    $followUp = file_get_contents(app_path('Http/Controllers/Admin/Marketing/FollowUpController.php'));
    expect(Route::has('admin.admin-sales.leads.index'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.leads.create'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.leads.assign'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.assignments.respond'))->toBeTrue()
        ->and($controller)->toContain('MarketingLead $lead')
        ->toContain("where('marketing_lead_id', \$lead->id)")
        ->toContain("'assignment_status' => 'offered'")
        ->toContain("=== 'rejected'")
        ->toContain('Distribusikan ulang ')
        ->and($followUp)->toContain("'assignment_status' => \$firstResponse")
        ->toContain('Respons pertama tercatat dari follow-up Marketing');
});

it('keeps company lead and marketing assignment forms on separate pages', function () {
    expect(file_exists(resource_path('js/Pages/Admin/AdminSales/Leads/Form.jsx')))->toBeTrue()
        ->and(file_exists(resource_path('js/Pages/Admin/AdminSales/Leads/Show.jsx')))->toBeTrue()
        ->and(file_exists(resource_path('js/Pages/Admin/Marketing/LeadAssignments/Index.jsx')))->toBeTrue();
});

it('provides a scoped shared marketing activity calendar', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/Marketing/MarketingCalendarController.php'));
    $page = file_get_contents(resource_path('js/Pages/Admin/Marketing/Calendar/Index.jsx'));
    expect(Route::has('admin.marketing.calendar.index'))->toBeTrue()
        ->and($controller)->toContain('marketing-calendar.view')->toContain("'visit'")->toContain("'survey'")->toContain("'follow_up'")->toContain("'reminder'")->toContain("'action_plan'")->toContain("where('marketing_id'")
        ->and($page)->toContain('title={title}')->toContain('Semua Marketing')->toContain('Agenda tim yang dapat dipertanggungjawabkan');
});

it('synchronizes idempotent admin sales work items and notifications', function () {
    $service = file_get_contents(app_path('Services/AdminSalesWorkQueueService.php'));
    expect($service)->toContain("'automation_key' => ")->toContain('followup-overdue-')->toContain('visit-review-')->toContain('document-incomplete-')->toContain('reservation-review-')->toContain('spr-review-')->toContain('kpr-stale-')->toContain('payment-due-')->toContain('AppNotification::query()->create');
});

it('connects dashboard exception cards to matching operational filters', function () {
    $workspace = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    $customers = file_get_contents(app_path('Http/Controllers/Admin/Marketing/CostumerController.php'));
    $reservations = file_get_contents(app_path('Http/Controllers/Admin/Marketing/HousingReservationController.php'));
    $integratedSales = file_get_contents(app_path('Http/Controllers/Admin/IntegratedSalesController.php'));
    $receipts = file_get_contents(resource_path('js/Pages/Admin/CustomerReceipts/Index.jsx'));

    expect($workspace)->toContain('administrative_gap=unit')
        ->toContain('administrative_gap=payment_method')
        ->toContain('queue=expiring')
        ->toContain('urgency=overdue')
        ->toContain('#verifikasi-booking-fee')
        ->toContain('followup-revision')
        ->toContain('visit-revision')
        ->and($customers)->toContain("\$administrativeGap === 'unit'")
        ->toContain("\$administrativeGap === 'payment_method'")
        ->and($reservations)->toContain("query('queue') === 'expiring'")
        ->and($integratedSales)->toContain("\$section === 'bank-applications' && \$request->query('queue') === 'stale'")
        ->and($receipts)->toContain('id="verifikasi-booking-fee"');
});

it('provides a calculated customer administration readiness queue', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    $page = file_get_contents(resource_path('js/Pages/Admin/AdminSales/CustomerReadiness.jsx'));
    $sidebar = file_get_contents(resource_path('js/Sidebar/sidebarMenu.jsx'));

    expect(Route::has('admin.admin-sales.customer-readiness'))->toBeTrue()
        ->and($controller)->toContain('customerReadinessRow')
        ->toContain("\$gap === 'profile'")
        ->toContain("\$gap === 'unit'")
        ->toContain("\$gap === 'payment'")
        ->toContain("\$gap === 'documents'")
        ->toContain('document_problems')
        ->and($page)->toContain('Kekurangan Administrasi')->toContain('Masalah Dokumen Wajib')->toContain('Buka Checklist Dokumen')
        ->and($sidebar)->toContain('Kelengkapan Customer');
});

it('provides a read only sales milestone calendar from transaction sources', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesWorkspaceController.php'));
    $page = file_get_contents(resource_path('js/Pages/Admin/AdminSales/SalesCalendar.jsx'));

    expect(Route::has('admin.admin-sales.sales-calendar'))->toBeTrue()
        ->and($controller)->toContain("'appraisal' => 'OTS / Appraisal'")
        ->toContain("'contract_preparation' => 'Persiapan Akad'")
        ->toContain("'contract_signing' => 'Pelaksanaan Akad'")
        ->toContain("'bank_disbursement' => 'Rencana Pencairan'")
        ->toContain("'customer_handover' => 'BAST / Serah Terima Customer'")
        ->toContain('expected_disbursement_date')
        ->and($page)->toContain('Daftar Agenda Bulan Ini')->toContain('Semua perumahan')
        ->not->toContain('router.post');
});

it('provides auditable lead import duplicate resolution reports and an idempotent channel api', function () {
    $controller = file_get_contents(app_path('Http/Controllers/Admin/AdminSalesLeadIntakeController.php'));
    $service = file_get_contents(app_path('Services/AdminSalesLeadIntakeService.php'));
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect(Route::has('admin.admin-sales.leads.import'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.leads.duplicates'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.leads.export'))->toBeTrue()
        ->and(Route::has('admin.admin-sales.reports.index'))->toBeTrue()
        ->and(Route::has('api.sales.leads.store'))->toBeTrue()
        ->and($controller)->toContain('admin-sales.report.export')->toContain('Idempotency-Key')->toContain('hash_equals')
        ->and($service)->toContain('readXlsx')->toContain('MarketingLead::query()->create')->toContain("'marketing_lead_id' => \$lead?->id")->toContain("'duplicate'")->toContain("'invalid'")
        ->and($bootstrap)->toContain('api/sales/leads')
        ->and(file_exists(resource_path('js/Pages/Admin/AdminSales/Leads/Import.jsx')))->toBeTrue()
        ->and(file_exists(resource_path('js/Pages/Admin/AdminSales/Leads/Duplicates.jsx')))->toBeTrue()
        ->and(file_exists(resource_path('js/Pages/Admin/AdminSales/Reports/Index.jsx')))->toBeTrue();
});

it('reads the documented csv and xlsx intake formats', function () {
    $service = app(AdminSalesLeadIntakeService::class);
    $csv = tempnam(sys_get_temp_dir(), 'lead-csv-');
    file_put_contents($csv, "nama,telepon,email,sumber\nLead CSV,08111,csv@example.test,Website\n");
    expect($service->read($csv, 'csv'))->toHaveCount(1)->and($service->read($csv, 'csv')[0]['nama'])->toBe('Lead CSV');

    $xlsx = tempnam(sys_get_temp_dir(), 'lead-xlsx-');
    $zip = new ZipArchive;
    $zip->open($xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>nama</t></si><si><t>telepon</t></si><si><t>email</t></si><si><t>sumber</t></si><si><t>Lead XLSX</t></si><si><t>08222</t></si><si><t>xlsx@example.test</t></si><si><t>Website</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row><row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>6</v></c><c r="D2" t="s"><v>7</v></c></row></sheetData></worksheet>');
    $zip->close();
    $rows = $service->read($xlsx, 'xlsx');
    @unlink($csv);
    @unlink($xlsx);

    expect($rows)->toHaveCount(1)->and($rows[0]['nama'])->toBe('Lead XLSX')->and($rows[0]['telepon'])->toBe('08222');
});
