<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\CashInstallmentContract;
use App\Models\CashInstallmentScheme;
use App\Models\DeveloperKprApplication;
use App\Models\DeveloperKprProduct;
use App\Models\KprSubmission;
use App\Models\SalesTransaction;
use App\Services\ApprovalWorkflowService;
use App\Services\CustomerReceivableService;
use App\Services\SalesProcessService;
use App\Support\SalesProcessDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IntegratedSalesController extends Controller
{
    public function index(Request $request, string $section = 'transactions'): Response
    {
        $config = $this->config($section);
        $this->authorizePage($request, $config['permission'], 'view');
        $search = trim((string) $request->query('search', ''));
        $filters = collect(['status', 'payment_method', 'perumahan_id', 'date_from', 'date_to'])->mapWithKeys(fn ($key) => [$key => $request->query($key)])->all();
        $query = DB::table($config['table'])->whereNull($config['table'].'.deleted_at');
        if (Schema::hasColumn($config['table'], 'record_status') && ! in_array($section, ['schemes', 'developer-products', 'contracts', 'developer-applications'], true)) {
            $query->where($config['table'].'.record_status', 'locked');
        }
        foreach ($config['joins'] ?? [] as $join) {
            $query->leftJoin(...$join);
        }
        foreach ($config['conditions'] ?? [] as [$column,$operator,$value]) {
            $query->where($column, $operator, $value);
        }
        $filterColumns = $section === 'transactions' ? ['status' => 'sales_transactions.status', 'payment_method' => 'sales_transactions.payment_method', 'perumahan_id' => 'sales_transactions.perumahan_id', 'date_from' => 'sales_transactions.created_at', 'date_to' => 'sales_transactions.created_at'] : ($config['filter_columns'] ?? []);
        foreach ($filterColumns as $key => $column) {
            $value = $filters[$key] ?? null;
            if (filled($value)) {
                $query->where($column, $key === 'date_from' ? '>=' : ($key === 'date_to' ? '<=' : '='), $value);
            }
        }
        if ($search !== '') {
            $query->where(function ($q) use ($config, $search) {
                foreach ($config['search'] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }
        $analytics = $section === 'transactions'
            ? $this->transactionAnalytics(clone $query)
            : null;
        $rows = $query->select($config['select'])->orderByDesc($config['table'].'.id')->paginate(12)->withQueryString();
        if (in_array($section, ['schemes', 'developer-products', 'contracts', 'developer-applications'], true)) {
            $class = match ($section) {
                'schemes' => CashInstallmentScheme::class,'developer-products' => DeveloperKprProduct::class,'contracts' => CashInstallmentContract::class,default => DeveloperKprApplication::class
            };
            $rows->through(function ($row) use ($class) {
                $approval = ApprovalRequest::query()->where(['model_type' => $class, 'model_id' => $row->id])->latest()->first();
                $row->record_status = $row->record_status ?? 'draft';
                $row->approval_status = $approval?->status ?? 'Belum Diajukan';
                $row->approval_stage = $approval ? ($approval->status === 'pending' ? "Tahap {$approval->current_step}/{$approval->total_steps}" : str($approval->status)->title()) : '-';
                $row->can_review = $approval ? app(ApprovalWorkflowService::class)->canReview($approval) : false;

                return $row;
            });
            $config['columns'] = [...$config['columns'], ['name' => 'record_status', 'label' => 'Lock'], ['name' => 'approval_status', 'label' => 'Status Approval'], ['name' => 'approval_stage', 'label' => 'Tahap Aktif']];
        }

        return Inertia::render('Admin/OperationsModule/Index', [
            'title' => 'Penjualan Terintegrasi', 'module' => 'sales', 'section' => $section, 'sectionTitle' => $config['title'], 'baseUrl' => '/admin/penjualan-terintegrasi',
            'menu' => $this->menu($request), 'fields' => $config['fields'], 'columns' => $config['columns'], 'rows' => $rows, 'filters' => ['search' => $search, ...$filters],
            'analytics' => $analytics,
            'filterOptions' => ['housing' => $this->options()['housing'], 'paymentMethods' => [['value' => 'cash', 'label' => 'Cash'], ['value' => 'cash_bertahap', 'label' => 'Cash Bertahap'], ['value' => 'kpr_bank', 'label' => 'KPR Bank'], ['value' => 'kpr_developer', 'label' => 'KPR Developer']], 'statuses' => [['value' => 'active', 'label' => 'Aktif'], ['value' => 'draft', 'label' => 'Draf'], ['value' => 'approved', 'label' => 'Disetujui'], ['value' => 'cancelled', 'label' => 'Dibatalkan']]],
            'permissions' => collect(['create', 'update', 'delete', 'export', 'print', 'submit'])->mapWithKeys(fn ($action) => [$action => (! in_array($action, ['create', 'update', 'delete'], true) || ($config['editable'] ?? false)) && ($request->user()?->can($config['permission'].'.'.$action) || $request->user()?->hasRole('super_admin'))])->all(),
        ]);
    }

    private function transactionAnalytics($query): array
    {
        $methodLabels = [
            'cash' => 'Cash',
            'cash_bertahap' => 'Cash Bertahap',
            'kpr_bank' => 'KPR Bank',
            'kpr_developer' => 'KPR Developer',
        ];
        $statusLabels = [
            'active' => 'Aktif',
            'draft' => 'Draf',
            'approved' => 'Disetujui',
            'cancelled' => 'Dibatalkan',
            'completed' => 'Selesai',
            'closed_lost' => 'Batal / Gagal',
        ];
        $total = (clone $query)->count('sales_transactions.id');
        $completedQuery = (clone $query)->where('sales_transactions.status', 'completed');
        $pipelineQuery = (clone $query)->whereNotIn('sales_transactions.status', ['completed', 'cancelled', 'closed_lost']);
        $completed = (clone $completedQuery)->count('sales_transactions.id');
        $completedValue = (float) (clone $completedQuery)->sum('sales_transactions.sale_price_snapshot');
        $pipeline = (clone $pipelineQuery)->count('sales_transactions.id');
        $pipelineValue = (float) (clone $pipelineQuery)->sum('sales_transactions.sale_price_snapshot');

        $distribution = function (string $column, array $labels) use ($query): array {
            return (clone $query)
                ->selectRaw("sales_transactions.{$column} as chart_key, COUNT(DISTINCT sales_transactions.id) as total")
                ->groupBy("sales_transactions.{$column}")
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'key' => (string) $row->chart_key,
                    'label' => $labels[$row->chart_key] ?? str((string) $row->chart_key)->replace('_', ' ')->title()->toString(),
                    'value' => (int) $row->total,
                ])->values()->all();
        };

        $trend = (clone $query)
            ->selectRaw("SUBSTR(COALESCE(sales_transactions.approved_at, sales_transactions.created_at), 1, 7) as period,
                COUNT(DISTINCT CASE WHEN sales_transactions.status = 'completed' THEN sales_transactions.id END) as completed_count,
                COUNT(DISTINCT CASE WHEN sales_transactions.status NOT IN ('completed','cancelled','closed_lost') THEN sales_transactions.id END) as pipeline_count,
                SUM(CASE WHEN sales_transactions.status = 'completed' THEN sales_transactions.sale_price_snapshot ELSE 0 END) as completed_value,
                SUM(CASE WHEN sales_transactions.status NOT IN ('completed','cancelled','closed_lost') THEN sales_transactions.sale_price_snapshot ELSE 0 END) as pipeline_value")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->period,
                'label' => filled($row->period) ? now()->createFromFormat('Y-m', $row->period)->translatedFormat('M Y') : '-',
                'count' => (int) $row->completed_count,
                'pipeline_count' => (int) $row->pipeline_count,
                'value' => (float) $row->completed_value,
                'pipeline_value' => (float) $row->pipeline_value,
            ])->values()->all();

        $housing = (clone $query)
            ->selectRaw("sales_transactions.perumahan_id as chart_key, perumahans.nama_perusahaan as label,
                COUNT(DISTINCT CASE WHEN sales_transactions.status = 'completed' THEN sales_transactions.id END) as completed_count,
                COUNT(DISTINCT CASE WHEN sales_transactions.status NOT IN ('completed','cancelled','closed_lost') THEN sales_transactions.id END) as pipeline_count,
                SUM(CASE WHEN sales_transactions.status = 'completed' THEN sales_transactions.sale_price_snapshot ELSE 0 END) as completed_value,
                SUM(CASE WHEN sales_transactions.status NOT IN ('completed','cancelled','closed_lost') THEN sales_transactions.sale_price_snapshot ELSE 0 END) as pipeline_value")
            ->groupBy('sales_transactions.perumahan_id', 'perumahans.nama_perusahaan')
            ->orderByDesc('completed_value')
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->chart_key,
                'label' => (string) $row->label,
                'count' => (int) $row->completed_count,
                'pipeline_count' => (int) $row->pipeline_count,
                'value' => (float) $row->completed_value,
                'pipeline_value' => (float) $row->pipeline_value,
            ])->values()->all();

        return [
            'summary' => [
                'total' => (int) $total,
                'completed' => (int) $completed,
                'pipeline' => (int) $pipeline,
                'sales_value' => $completedValue,
                'pipeline_value' => $pipelineValue,
                'average_value' => $completed > 0 ? $completedValue / $completed : 0,
            ],
            'trend' => $trend,
            'methods' => $distribution('payment_method', $methodLabels),
            'statuses' => $distribution('status', $statusLabels),
            'housing' => $housing,
        ];
    }

    public function create(Request $request, string $section): Response|RedirectResponse
    {
        $config = $this->config($section);
        if (($config['editable'] ?? false) !== true) {
            return to_route('admin.integrated-sales.index', $section)->with('info', 'Data '.$config['title'].' dibuat otomatis dari proses sumber. Buka data terkait melalui tombol Detail.');
        }$this->authorizePage($request, $config['permission'], 'create');

        return $this->form($section, $config, null);
    }

    public function show(Request $request, string $section, string $id): Response
    {
        $config = $this->config($section);
        $this->authorizePage($request, $config['permission'], 'view');
        [$kind,$record,$tabs] = match ($section) {
            'transactions' => ['transaction', SalesTransaction::query()->with(['spr', 'customer', 'housingProject', 'housingUnit', 'marketing', 'paymentSchedules', 'customerReceipts.allocations', 'workflowHistories', 'processSteps.checklistItems', 'processSteps.documents', 'processSteps.assignee'])->findOrFail($id), ['Ringkasan', 'Proses sampai Huni', 'Jadwal & Tagihan', 'Pembayaran', 'Pembangunan', 'Serah Terima', 'After Sales', 'Histori']],
            'schemes' => ['cash-scheme', CashInstallmentScheme::query()->with(['steps'])->findOrFail($id), ['Ringkasan', 'Perumahan', 'Tahapan Pembayaran', 'Biaya & Denda', 'Syarat Customer', 'Dokumen Wajib', 'Versi & Riwayat']],
            'contracts' => ['cash-contract', CashInstallmentContract::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'salesTransaction.paymentSchedules', 'salesTransaction.processSteps.checklistItems', 'salesTransaction.processSteps.documents', 'salesTransaction.processSteps.assignee'])->findOrFail($id), ['Ringkasan', 'Proses sampai Huni', 'Jadwal Pembayaran', 'Tagihan', 'Pembayaran', 'Tunggakan', 'Pelunasan', 'Restrukturisasi', 'Pembatalan', 'Histori']],
            'developer-products' => ['developer-product', DeveloperKprProduct::query()->findOrFail($id), ['Ringkasan', 'Perumahan', 'Ketentuan Pembiayaan', 'Margin', 'Biaya', 'Persyaratan', 'Dokumen', 'Risiko & Approval', 'Denda', 'Versi & Riwayat']],
            'developer-applications' => ['developer-application', DeveloperKprApplication::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'salesTransaction.paymentSchedules', 'salesTransaction.processSteps.checklistItems', 'salesTransaction.processSteps.documents', 'salesTransaction.processSteps.assignee'])->findOrFail($id), ['Ringkasan', 'Proses sampai Huni', 'Analisis Kemampuan Bayar', 'Validasi Dokumen', 'Persetujuan Internal', 'Kontrak', 'Jadwal Angsuran', 'Piutang & Tunggakan', 'Pembayaran', 'Restrukturisasi', 'Histori']],
            'bank-applications' => ['bank-application', KprSubmission::query()->with(['spr.costumer', 'spr.detailRumah.perumahan', 'spr.salesTransaction.processSteps.checklistItems', 'spr.salesTransaction.processSteps.documents', 'spr.salesTransaction.processSteps.assignee', 'bank', 'bankBranch', 'bankCreditProduct', 'stageHistories', 'financing', 'disbursements'])->findOrFail($id), ['Ringkasan', 'Proses sampai Huni', 'Dokumen', 'SLIK', 'Appraisal / Survei', 'Keputusan Bank', 'SP3K', 'Persiapan Akad', 'Jadwal & Pelaksanaan Akad', 'Pencairan', 'Perubahan Bank', 'Histori']],
            default => abort(404),
        };
        if ($kind === 'transaction') {
            app(SalesProcessService::class)->initialize($record);
            $record->unsetRelation('processSteps')->load([
                'processSteps.checklistItems',
                'processSteps.documents',
                'processSteps.assignee',
            ]);

            $permissionMap = ['Ringkasan' => 'summary', 'Jadwal & Tagihan' => 'schedules', 'Pembayaran' => 'payments', 'Pembangunan' => 'construction', 'Serah Terima' => 'handover', 'After Sales' => 'after-sales', 'Histori' => 'history'];
            $tabs = collect($tabs)->filter(fn ($tab) => $tab === 'Proses sampai Huni' ? ($request->user()?->can('sales-process.view') || $request->user()?->hasRole('super_admin')) : ($request->user()?->can('sales.transaction-detail.'.$permissionMap[$tab].'.view') || $request->user()?->hasRole('super_admin')))->values()->all();
        }

        return Inertia::render('Admin/IntegratedSales/Show', ['title' => 'Detail '.$config['title'], 'kind' => $kind, 'section' => $section, 'indexUrl' => route('admin.integrated-sales.index', $section, absolute: false), 'record' => $this->detailPayload($kind, $record), 'tabs' => $tabs]);
    }

    public function preview(Request $request, string $section, string $id)
    {
        return $this->printable($request, $section, $id, false);
    }

    public function print(Request $request, string $section, string $id)
    {
        return $this->printable($request, $section, $id, true);
    }

    private function printable(Request $request, string $section, string $id, bool $autoPrint)
    {
        $config = $this->config($section);
        $this->authorizePage($request, $config['permission'], 'view');
        [$kind, $record] = match ($section) {
            'transactions' => ['transaction', SalesTransaction::query()->with(['spr', 'customer', 'housingProject', 'housingUnit', 'marketing', 'paymentSchedules', 'customerReceipts', 'workflowHistories', 'processSteps.assignee'])->findOrFail($id)],
            'schemes' => ['cash-scheme', CashInstallmentScheme::query()->with('steps')->findOrFail($id)],
            'contracts' => ['cash-contract', CashInstallmentContract::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'salesTransaction.paymentSchedules'])->findOrFail($id)],
            'developer-products' => ['developer-product', DeveloperKprProduct::findOrFail($id)],
            'developer-applications' => ['developer-application', DeveloperKprApplication::query()->with(['salesTransaction.customer', 'salesTransaction.housingProject', 'salesTransaction.housingUnit', 'salesTransaction.paymentSchedules'])->findOrFail($id)],
            'bank-applications' => ['bank-application', KprSubmission::query()->with(['spr.costumer', 'spr.detailRumah.perumahan', 'bank', 'bankBranch', 'bankCreditProduct', 'stageHistories', 'financing', 'disbursements'])->findOrFail($id)],
            default => abort(404),
        };

        return view('reports.sales-erp-detail', ['title' => 'Detail '.$config['title'], 'record' => $this->detailPayload($kind, $record), 'autoPrint' => $autoPrint]);
    }

    public function edit(Request $request, string $section, string $id): Response|RedirectResponse
    {
        $config = $this->config($section);
        if (($config['editable'] ?? false) !== true) {
            return to_route('admin.integrated-sales.show', [$section, $id])->with('info', 'Data proses otomatis ditinjau melalui halaman Detail.');
        }$this->authorizePage($request, $config['permission'], 'update');
        $row = DB::table($config['table'])->whereNull('deleted_at')->find($id);
        abort_unless($row, 404);
        abort_if(($row->record_status ?? 'draft') === 'locked', 422, 'Data sudah final. Buka lock terlebih dahulu untuk mengubahnya.');

        return $this->form($section, $config, (array) $row);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $config = $this->config($section);
        $this->ensureEditable($config);
        $this->authorizePage($request, $config['permission'], 'create');
        if (in_array($section, ['schemes', 'developer-products'], true)) {
            return $this->saveSalesMaster($request, $section);
        }$payload = $request->validate($config['rules']);
        $payload = $this->normalize($section, $payload);
        DB::table($config['table'])->insert([...$payload, 'created_by' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now()]);

        return to_route('admin.integrated-sales.index', $section)->with('success', $config['title'].' berhasil ditambahkan.');
    }

    public function update(Request $request, string $section, string $id): RedirectResponse
    {
        $config = $this->config($section);
        $this->ensureEditable($config);
        $this->authorizePage($request, $config['permission'], 'update');
        if (in_array($section, ['schemes', 'developer-products'], true)) {
            return $this->saveSalesMaster($request, $section, $id);
        }$payload = $request->validate($this->updateRules($config['rules'], $id, $config['table']));
        $payload = $this->normalize($section, $payload);
        DB::table($config['table'])->where('id', $id)->whereNull('deleted_at')->update([...$payload, 'updated_at' => now()]);

        return to_route('admin.integrated-sales.index', $section)->with('success', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(Request $request, string $section, string $id): RedirectResponse
    {
        $config = $this->config($section);
        $this->ensureEditable($config);
        $this->authorizePage($request, $config['permission'], 'delete');
        if (Schema::hasColumn($config['table'], 'record_status')) {
            abort_if(DB::table($config['table'])->where('id', $id)->value('record_status') === 'locked', 422, 'Data sudah final. Buka lock terlebih dahulu untuk menghapusnya.');
        }
        DB::table($config['table'])->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);

        return back()->with('success', $config['title'].' berhasil diarsipkan.');
    }

    public function lockProcess(Request $request, string $section, string $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        [$model,$key] = $this->approvalProcess($section, $id);
        abort_unless($request->user()?->can($this->config($section)['permission'].'.submit') || $request->user()?->hasRole('super_admin'), 403);
        abort_unless(($model->record_status ?? 'draft') === 'draft', 422);
        $model->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()?->id]);
        $approval = $workflow->submitLocked($model, $key);

        return back()->with('success', $approval->status === 'approved' ? 'Dokumen disetujui otomatis dan jadwal resmi dibuat.' : "Dokumen masuk approval tahap {$approval->current_step}/{$approval->total_steps}.");
    }

    public function unlockProcess(Request $request, string $section, string $id, ApprovalWorkflowService $workflow): RedirectResponse
    {
        [$model] = $this->approvalProcess($section, $id);
        abort_unless($request->user()?->can($this->config($section)['permission'].'.update') || $request->user()?->hasRole('super_admin'), 403);
        if (in_array($section, ['contracts', 'developer-applications'], true)) {
            abort_if($model->status === 'active' || $model->status === 'approved', 422, 'Dokumen sudah disetujui final; gunakan revisi/restrukturisasi.');
        }
        $workflow->cancelPendingLock($model);
        $model->update(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Dokumen kembali menjadi draf.');
    }

    public function reviewProcess(Request $request, string $section, string $id, string $decision, ApprovalWorkflowService $workflow): RedirectResponse
    {
        [$model] = $this->approvalProcess($section, $id);
        $approval = ApprovalRequest::query()->where(['model_type' => $model::class, 'model_id' => $model->id, 'status' => 'pending'])->latest()->firstOrFail();
        abort_unless($workflow->canReview($approval), 403);
        $decision === 'approve' ? $workflow->approve($approval) : $workflow->reject($approval, $request->validate(['note' => 'required|string'])['note']);

        return back()->with('success', 'Approval berhasil diproses.');
    }

    private function approvalProcess(string $section, string $id): array
    {
        return match ($section) {
            'schemes' => [CashInstallmentScheme::findOrFail($id), 'cash-installment-scheme'],'developer-products' => [DeveloperKprProduct::findOrFail($id), 'developer-kpr-product'],'contracts' => [CashInstallmentContract::findOrFail($id), 'cash-installment-contract'],'developer-applications' => [DeveloperKprApplication::findOrFail($id), 'developer-kpr-contract'],default => abort(404)
        };
    }

    private function form(string $section, array $config, ?array $row): Response
    {
        $props = ['title' => ($row ? 'Edit ' : 'Tambah ').$config['title'], 'module' => 'sales', 'section' => $section, 'sectionTitle' => $config['title'], 'indexUrl' => route('admin.integrated-sales.index', $section, absolute: false), 'actionUrl' => $row ? route('admin.integrated-sales.update', [$section, $row['id']], absolute: false) : route('admin.integrated-sales.store', $section, absolute: false), 'method' => $row ? 'put' : 'post', 'fields' => $config['fields'], 'options' => $this->options(), 'row' => $row];
        if (in_array($section, ['schemes', 'developer-products'], true)) {
            if ($row) {
                $model = $section === 'schemes' ? CashInstallmentScheme::query()->with(['housings', 'steps'])->findOrFail($row['id']) : DeveloperKprProduct::query()->with('housings')->findOrFail($row['id']);
                $props['row'] = [...$model->toArray(), 'perumahan_ids' => $model->housings->pluck('id')->map(fn ($id) => (string) $id)->all()];
            }

            return Inertia::render('Admin/IntegratedSales/MasterWizard', $props);
        }

        return Inertia::render('Admin/OperationsModule/Form', $props);
    }

    private function options(): array
    {
        return [
            'branches' => DB::table('cabang_perusahaans')->whereNull('deleted_at')->orderBy('nama_cabang')->get()->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->kode_cabang.' — '.$r->nama_cabang])->all(),
            'housing' => DB::table('perumahans')->whereNull('deleted_at')->orderBy('nama_perusahaan')->get()->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->kode_proyek.' — '.$r->nama_perusahaan, 'cabang_perusahaan_id' => (string) $r->cabang_id])->all(),
            'schemes' => CashInstallmentScheme::query()->orderBy('name')->get()->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->code.' — '.$r->name])->all(),
        ];
    }

    private function menu(Request $request): array
    {
        $primary = ['transactions', 'schemes', 'contracts', 'billings', 'reports', 'developer-products', 'developer-applications', 'developer-receivables', 'developer-reports', 'bank-applications', 'bank-document-validation', 'bank-contract-schedule', 'bank-reports'];

        return collect($this->configs())->only($primary)->filter(fn ($c) => $request->user()?->can($c['permission'].'.view'))->map(fn ($c, $key) => ['key' => $key, 'label' => $c['title']])->values()->all();
    }

    private function authorizePage(Request $request, string $permission, string $action): void
    {
        abort_unless($request->user()?->can($permission.'.'.$action) || $request->user()?->hasRole('super_admin'), 403);
    }

    private function ensureEditable(array $config): void
    {
        abort_if(($config['editable'] ?? false) !== true, 405, 'Halaman ini dibentuk otomatis dari proses sumber dan tidak dapat ditambah manual.');
    }

    private function normalize(string $section, array $payload): array
    {
        foreach (['requirements', 'allowed_tenors'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = filled($payload[$key]) ? json_encode(array_values(array_filter(array_map('trim', explode(',', $payload[$key]))))) : null;
            }
        }if ($section === 'scheme-steps' && isset($payload['value'])) {
            $payload['value'] = (string) $payload['value'];
        }

        return $payload;
    }

    private function saveSalesMaster(Request $request, string $section, ?string $id = null): RedirectResponse
    {
        $isCash = $section === 'schemes';
        $table = $isCash ? 'cash_installment_schemes' : 'developer_kpr_products';
        $common = ['code' => ['required', 'string', 'max:50', Rule::unique($table, 'code')->ignore($id)], 'name' => 'required|string|max:255', 'cabang_perusahaan_id' => 'required|exists:cabang_perusahaans,id', 'perumahan_ids' => 'required|array|min:1', 'perumahan_ids.*' => 'integer|exists:perumahans,id', 'unit_types' => 'nullable|array', 'unit_types.*' => 'string|max:100', 'effective_from' => 'required|date', 'effective_until' => 'nullable|date|after_or_equal:effective_from', 'status' => 'required|in:draft,aktif,nonaktif', 'notes' => 'nullable|string'];
        $cash = ['minimum_booking_fee' => 'required|numeric|min:0', 'booking_fee_deducts' => 'required|in:down_payment,sale_price,none', 'dp_type' => 'required|in:percentage,nominal', 'minimum_dp' => 'required|numeric|min:0', 'payment_model' => 'required|in:equal_monthly,equal_weekly,percentage_steps,progress_steps,custom', 'maximum_tenor_months' => 'required|integer|min:1', 'installment_count' => 'required|integer|min:1', 'grace_period_days' => 'required|integer|min:0', 'penalty_method' => 'required|in:none,fixed,invoice_percentage,daily_percentage,monthly_percentage', 'penalty_value' => 'nullable|numeric|min:0', 'schedule_config' => 'nullable|array', 'schedule_config.tenor_value' => 'nullable|integer|min:1', 'handover_config' => 'nullable|array', 'advanced_config' => 'nullable|array', 'advanced_config.early_settlement' => 'nullable|boolean', 'advanced_config.early_settlement_terms' => 'nullable|string|max:5000', 'advanced_config.cancellation' => 'nullable|boolean', 'advanced_config.cancellation_terms' => 'nullable|string|max:5000', 'requirements' => 'nullable|array', 'document_requirements' => 'nullable|array', 'steps' => 'nullable|array', 'steps.*.name' => 'required_with:steps|string|max:255', 'steps.*.calculation_type' => 'required_with:steps|in:fixed,percentage_sale,percentage_final,percentage_remaining,remaining', 'steps.*.value' => 'nullable|numeric|min:0'];
        $kpr = ['dp_type' => 'required|in:percentage,nominal', 'minimum_dp' => 'required|numeric|min:0', 'financing_type' => 'required|in:percentage,nominal', 'maximum_financing' => 'required|numeric|min:0', 'financing_basis' => 'required|in:sale_price,final_price,final_less_booking,final_less_booking_dp', 'tenor_mode' => 'required|in:range,custom', 'minimum_tenor_months' => 'required|integer|min:1', 'maximum_tenor_months' => 'required|integer|gte:minimum_tenor_months', 'tenor_increment' => 'required|integer|min:1', 'allowed_tenors' => 'nullable|array', 'allowed_tenors.*' => 'integer|min:1', 'margin_method' => 'required|in:none,flat,effective,annuity,internal_fixed', 'margin_scope' => 'required|in:all,per_tenor', 'annual_margin' => 'required|numeric|min:0', 'margin_tiers' => 'nullable|array', 'fees' => 'nullable|array', 'grace_period_days' => 'required|integer|min:0', 'penalty_method' => 'required|in:none,fixed,installment_percentage,daily_percentage,monthly_percentage', 'penalty_value' => 'nullable|numeric|min:0', 'minimum_income' => 'required|numeric|min:0', 'maximum_age' => 'nullable|integer|min:1', 'eligibility_config' => 'nullable|array', 'schedule_config' => 'nullable|array', 'handover_config' => 'nullable|array', 'document_requirements' => 'nullable|array', 'advanced_config' => 'nullable|array'];
        $data = $request->validate([...$common, ...($isCash ? $cash : $kpr)]);
        if ($data['dp_type'] === 'percentage' && (float) $data['minimum_dp'] > 100) {
            throw ValidationException::withMessages(['minimum_dp' => 'Minimum uang muka persentase tidak boleh lebih dari 100%.']);
        }
        if (($data['financing_type'] ?? null) === 'percentage' && (float) $data['maximum_financing'] > 100) {
            throw ValidationException::withMessages(['maximum_financing' => 'Maksimum pembiayaan persentase tidak boleh lebih dari 100%.']);
        }
        if (($data['penalty_method'] ?? 'none') === 'none') {
            $data['penalty_value'] = 0;
        }
        if ($isCash) {
            $automatic = in_array($data['payment_model'], ['equal_monthly', 'equal_weekly'], true);
            if ($automatic) {
                $tenor = max(1, (int) ($data['schedule_config']['tenor_value'] ?? $data['installment_count']));
                $data['installment_count'] = $tenor;
                $data['maximum_tenor_months'] = $data['payment_model'] === 'equal_weekly' ? max(1, (int) ceil($tenor / 4)) : $tenor;
                $data['schedule_config'] = [...($data['schedule_config'] ?? []), 'tenor_value' => $tenor, 'tenor_unit' => $data['payment_model'] === 'equal_weekly' ? 'week' : 'month'];
                $data['steps'] = [];
            } elseif (empty($data['steps'])) {
                throw ValidationException::withMessages(['steps' => 'Tambahkan minimal satu tahap pembayaran untuk model yang dipilih.']);
            } else {
                foreach ($data['steps'] as $index => &$step) {
                    if ($step['calculation_type'] === 'remaining') {
                        $step['value'] = 0;
                    } elseif (! isset($step['value']) || $step['value'] === '') {
                        throw ValidationException::withMessages(["steps.{$index}.value" => 'Nilai tahap harus diisi sesuai metode perhitungannya.']);
                    }
                }$data['installment_count'] = count($data['steps']);
            }
        }
        $invalidHousing = DB::table('perumahans')->whereIn('id', $data['perumahan_ids'])->where('cabang_id', '!=', $data['cabang_perusahaan_id'])->exists();
        if ($invalidHousing) {
            throw ValidationException::withMessages(['perumahan_ids' => 'Semua perumahan harus berasal dari cabang yang dipilih.']);
        }
        $housingIds = array_values(array_unique(array_map('intval', $data['perumahan_ids'])));
        unset($data['perumahan_ids']);
        $steps = $data['steps'] ?? [];
        unset($data['steps']);
        $jsonKeys = $isCash ? ['unit_types', 'schedule_config', 'handover_config', 'requirements'] : ['unit_types', 'allowed_tenors', 'margin_tiers', 'fees', 'eligibility_config', 'schedule_config', 'handover_config', 'advanced_config'];
        foreach ($jsonKeys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = json_encode($data[$key]);
            }
        }
        $data['document_requirements'] = null; // Dokumen customer dikelola terpusat melalui menu Dokumen Pelanggan.
        DB::transaction(function () use ($isCash, $id, $data, $housingIds, $steps, $request) {
            $model = $isCash ? CashInstallmentScheme::query()->find($id) : DeveloperKprProduct::query()->find($id);
            if ($model) {
                abort_if($model->record_status === 'locked', 422, 'Data sudah final. Buka lock terlebih dahulu untuk mengubahnya.');
                $model->update($data);
            } else {
                $model = $isCash ? CashInstallmentScheme::create([...$data, 'perumahan_id' => $housingIds[0], 'created_by' => $request->user()?->id]) : DeveloperKprProduct::create([...$data, 'perumahan_id' => $housingIds[0], 'created_by' => $request->user()?->id]);
            }$model->update(['perumahan_id' => $housingIds[0]]);
            $model->housings()->sync($housingIds);
            if ($isCash && array_key_exists('steps', $request->all())) {
                $model->steps()->withTrashed()->forceDelete();
                foreach (array_values($steps) as $index => $step) {
                    $model->steps()->create([...$step, 'sequence' => $index + 1, 'due_offset_months' => $step['due_offset_months'] ?? $index + 1, 'created_by' => $request->user()?->id]);
                }
            }
        });

        return to_route('admin.integrated-sales.index', $section)->with('success', ($isCash ? 'Skema Cash Bertahap' : 'Produk KPR Developer').' berhasil disimpan.');
    }

    private function updateRules(array $rules, string $id, string $table): array
    {
        if (isset($rules['code'])) {
            $rules['code'] = ['required', 'string', 'max:50', Rule::unique($table, 'code')->ignore($id)];
        }

        return $rules;
    }

    private function detailPayload(string $kind, mixed $record): array
    {
        $human = fn ($value) => match ((string) $value) {
            'draft' => 'Draf','active','aktif' => 'Aktif','approved','disetujui' => 'Disetujui','rejected','ditolak' => 'Ditolak','belum_dibayar' => 'Belum Dibayar','jatuh_tempo' => 'Jatuh Tempo',default => str((string) $value)->replace('_', ' ')->title()->toString()
        };
        $payload = match ($kind) {
            'transaction' => $this->transactionPayload($record, $human),
            'cash-scheme' => ['heading' => $record->code.' — '.$record->name, 'subtitle' => 'Skema Cash Bertahap', 'summary' => ['Kode' => $record->code, 'Nama Skema' => $record->name, 'Jumlah Tahap' => $record->installment_count.' Tahap', 'Tenor Maksimal' => $record->maximum_tenor_months.' Bulan', 'Minimum Booking Fee' => 'Rp '.number_format((float) $record->minimum_booking_fee, 0, ',', '.'), 'Minimum Uang Muka' => 'Rp '.number_format((float) $record->minimum_dp, 0, ',', '.'), 'Masa Tenggang' => $record->grace_period_days.' Hari', 'Metode Denda' => $human($record->penalty_method), 'Nilai Denda' => $record->penalty_value, 'Status' => $human($record->status)], 'schedules' => $record->steps->map(fn ($r) => ['description' => $r->sequence.'. '.$r->name, 'due_date' => 'Bulan ke-'.$r->due_offset_months, 'amount' => $r->calculation_type === 'fixed' ? 'Rp '.number_format((float) $r->value, 0, ',', '.') : $r->value.'%', 'paid' => '-', 'status' => $human($r->calculation_type)])->all(), 'timeline' => [['title' => 'Versi '.$record->version, 'date' => $record->updated_at?->format('d/m/Y H:i'), 'notes' => 'Versi aktif skema']]],
            'cash-contract' => ['heading' => $record->contract_no, 'subtitle' => ($record->salesTransaction?->customer?->nama ?? 'Customer').' — Kontrak Cash Bertahap', 'summary' => ['Nomor Kontrak' => $record->contract_no, 'Transaksi' => $record->salesTransaction?->transaction_no, 'Customer' => $record->salesTransaction?->customer?->nama, 'Perumahan' => $record->salesTransaction?->housingProject?->nama_perusahaan, 'Unit' => $record->salesTransaction?->housingUnit?->nomor_rumah, 'Nilai Kontrak' => 'Rp '.number_format((float) $record->contract_value, 0, ',', '.'), 'Tanggal Mulai' => $record->start_date?->format('d/m/Y'), 'Status' => $human($record->status)], 'schedules' => $record->salesTransaction?->paymentSchedules->map(fn ($r) => ['description' => $r->description, 'due_date' => $r->due_date?->format('d/m/Y'), 'amount' => 'Rp '.number_format((float) $r->amount, 0, ',', '.'), 'paid' => 'Rp '.number_format((float) $r->paid_amount, 0, ',', '.'), 'status' => $human($r->status)])->all() ?? [], 'timeline' => []],
            'developer-product' => ['heading' => $record->code.' — '.$record->name, 'subtitle' => 'Produk KPR Developer', 'summary' => ['Kode Produk' => $record->code, 'Nama Produk' => $record->name, 'Minimum Uang Muka' => 'Rp '.number_format((float) $record->minimum_dp, 0, ',', '.'), 'Maksimum Pembiayaan' => 'Rp '.number_format((float) $record->maximum_financing, 0, ',', '.'), 'Tenor' => $record->minimum_tenor_months.'–'.$record->maximum_tenor_months.' Bulan', 'Margin Tahunan' => $record->annual_margin.'%', 'Metode Margin' => $human($record->margin_method), 'Biaya Administrasi' => 'Rp '.number_format((float) $record->administration_fee, 0, ',', '.'), 'Biaya Akad' => 'Rp '.number_format((float) $record->contract_fee, 0, ',', '.'), 'Penghasilan Minimum' => 'Rp '.number_format((float) $record->minimum_income, 0, ',', '.'), 'Status' => $human($record->status)], 'schedules' => [], 'timeline' => [['title' => 'Versi '.$record->version, 'date' => $record->updated_at?->format('d/m/Y H:i'), 'notes' => 'Versi aktif produk']]],
            'developer-application' => ['heading' => $record->application_no, 'subtitle' => ($record->salesTransaction?->customer?->nama ?? 'Customer').' — Pengajuan KPR Developer', 'summary' => ['Nomor Pengajuan' => $record->application_no, 'Transaksi' => $record->salesTransaction?->transaction_no, 'Customer' => $record->salesTransaction?->customer?->nama, 'Perumahan' => $record->salesTransaction?->housingProject?->nama_perusahaan, 'Unit' => $record->salesTransaction?->housingUnit?->nomor_rumah, 'Nilai Pembiayaan' => 'Rp '.number_format((float) $record->financing_amount, 0, ',', '.'), 'Tenor' => $record->tenor_months.' Bulan', 'Estimasi Angsuran' => 'Rp '.number_format((float) $record->estimated_installment, 0, ',', '.'), 'Status Analisis' => $human($record->analysis_status), 'Status' => $human($record->status)], 'schedules' => $record->salesTransaction?->paymentSchedules->map(fn ($r) => ['description' => $r->description, 'due_date' => $r->due_date?->format('d/m/Y'), 'amount' => 'Rp '.number_format((float) $r->amount, 0, ',', '.'), 'paid' => 'Rp '.number_format((float) $r->paid_amount, 0, ',', '.'), 'status' => $human($r->status)])->all() ?? [], 'timeline' => []],
            'bank-application' => ['heading' => $record->kode_kpr, 'subtitle' => ($record->spr?->costumer?->nama ?? 'Customer').' — '.($record->bank?->nama_bank ?? 'Bank'), 'summary' => ['Nomor Pengajuan' => $record->kode_kpr, 'Nomor SPR' => $record->spr?->kode_spr, 'Customer' => $record->spr?->costumer?->nama, 'Perumahan' => $record->spr?->detailRumah?->perumahan?->nama_perusahaan, 'Unit' => $record->spr?->detailRumah?->nomor_rumah, 'Bank' => $record->bank?->nama_bank, 'Cabang Bank' => $record->bankBranch?->branch_name, 'Produk' => $record->bankCreditProduct?->product_name, 'Nilai Pengajuan' => 'Rp '.number_format((float) $record->nilai_pengajuan, 0, ',', '.'), 'Status' => $human($record->status)], 'schedules' => [], 'timeline' => $record->stageHistories->map(fn ($r) => ['title' => $human($r->status), 'date' => $r->tanggal_status?->format('d/m/Y H:i'), 'notes' => $r->catatan])->all()],
        };
        if ($kind === 'cash-scheme') {
            $payload['summary']['Nilai Denda'] = $record->penalty_method === 'fixed'
                ? 'Rp '.number_format((float) $record->penalty_value, 0, ',', '.')
                : number_format((float) $record->penalty_value, 2, ',', '.').'%';
        }
        $transaction = match ($kind) {
            'cash-contract','developer-application' => $record->salesTransaction,'bank-application' => $record->spr?->salesTransaction,default => null
        };
        if ($transaction) {
            $payload['processSteps'] = $this->stepPayload($transaction->processSteps, $human);
        }

        $payload['id'] = $record->id;

        return $payload;
    }

    private function stepPayload($steps, callable $human): array
    {
        $users = DB::table('users')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name])->all();

        return $steps->map(function ($step) use ($human, $users) {
            $approval = ApprovalRequest::where(['model_type' => $step::class, 'model_id' => $step->id])->latest()->first();
            $definition = SalesProcessDefinitions::get($step->code);

            return ['id' => $step->id, 'sequence' => $step->sequence, 'code' => $step->code, 'label' => $step->label, 'category' => $human($step->category), 'description' => $step->description, 'assigned_to' => (string) ($step->assigned_to ?? ''), 'assignee' => $step->assignee?->name, 'assignees' => $users, 'planned_date' => $step->planned_date?->format('Y-m-d'), 'actual_date' => $step->actual_date?->format('Y-m-d'), 'outcome' => $step->outcome, 'status' => $step->status, 'status_label' => $human($step->status), 'notes' => $step->notes, 'record_status' => $step->record_status, 'metadata' => $step->metadata['data'] ?? [], 'fields' => $definition['fields'], 'document_types' => $definition['documents'], 'checklist' => $step->checklistItems->map(fn ($item) => ['key' => $item->item_key, 'label' => $item->label, 'required' => $item->is_required, 'completed' => $item->is_completed, 'notes' => $item->notes])->all(), 'documents' => $step->documents->map(fn ($doc) => ['id' => $doc->id, 'type' => $doc->document_type, 'label' => collect($definition['documents'])->firstWhere('type', $doc->document_type)['label'] ?? $doc->document_type, 'number' => $doc->document_number, 'date' => $doc->document_date?->format('d/m/Y'), 'expires' => $doc->expires_at?->format('d/m/Y'), 'name' => $doc->original_name, 'status' => $human($doc->validation_status), 'url' => route('admin.sales-process.document.show', [$step, $doc], absolute: false), 'delete_url' => route('admin.sales-process.document.destroy', [$step, $doc], absolute: false)])->all(), 'approval_stage' => $approval?->status === 'pending' ? "Tahap {$approval->current_step}/{$approval->total_steps}" : null, 'can_edit' => auth()->user()?->can('sales-process.update') && $step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true), 'can_lock' => auth()->user()?->can('sales-process.lock') && $step->record_status === 'draft' && in_array($step->status, ['available', 'in_progress'], true), 'can_unlock' => auth()->user()?->can('sales-process.unlock') && $step->record_status === 'locked' && $step->status !== 'completed', 'can_review' => $approval ? app(ApprovalWorkflowService::class)->canReview($approval) : false];
        })->all();
    }

    private function transactionPayload(SalesTransaction $record, callable $human): array
    {
        $money = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        $progress = DB::table('progress_pembangunans')->leftJoin('tahapan_pembangunans', 'tahapan_pembangunans.id', '=', 'progress_pembangunans.tahapan_pembangunan_id')->where('progress_pembangunans.detail_rumah_id', $record->detail_rumah_id)->whereNull('progress_pembangunans.deleted_at')->orderByDesc('progress_pembangunans.tanggal')->get(['progress_pembangunans.nama_progress', 'progress_pembangunans.tanggal', 'progress_pembangunans.persentase_total', 'progress_pembangunans.keterangan', 'tahapan_pembangunans.nama_tahapan'])->map(fn ($r) => ['label' => $r->nama_progress ?: $r->nama_tahapan, 'date' => $r->tanggal, 'value' => ($r->persentase_total ?? 0).'%', 'notes' => $r->keterangan]);
        $handover = DB::table('internal_handovers')->where('detail_rumah_id', $record->detail_rumah_id)->whereNull('deleted_at')->orderByDesc('tanggal')->get()->map(fn ($r) => ['label' => $r->kode_serah_terima, 'date' => $r->tanggal, 'value' => $human($r->status ?? $r->record_status), 'notes' => $r->catatan ?? $r->keterangan ?? null]);
        $after = DB::table('field_defects')->where('detail_rumah_id', $record->detail_rumah_id)->whereNull('deleted_at')->orderByDesc('tanggal')->get()->map(fn ($r) => ['label' => $r->kode_defect.' — '.$r->kategori, 'date' => $r->tanggal, 'value' => $human($r->status), 'notes' => $r->temuan.' '.($r->instruksi_perbaikan ?? '')]);
        $steps = $this->stepPayload($record->processSteps, $human);

        return $this->enrichedTransactionPayload($record, $human, $money, $steps, $progress, $handover, $after);

        return ['heading' => $record->transaction_no, 'subtitle' => ($record->customer?->nama ?? 'Customer').' — '.($record->housingUnit?->nomor_rumah ?? 'Unit'), 'summary' => ['Nomor Transaksi' => $record->transaction_no, 'Nomor SPR' => $record->spr?->kode_spr, 'Customer' => $record->customer?->nama, 'No. Identitas' => $record->customer?->no_identitas, 'Telepon' => $record->customer?->telepon, 'Perumahan' => $record->housingProject?->nama_perusahaan, 'Unit' => trim(($record->housingUnit?->kode_nlok ?? '').' / '.($record->housingUnit?->nomor_rumah ?? '')), 'Tipe Unit' => $record->housingUnit?->tipe_rumah, 'Marketing' => $record->marketing?->name, 'Metode Pembayaran' => $human($record->payment_method), 'Harga Jual' => $money($record->sale_price_snapshot), 'Tanggal Disetujui' => $record->approved_at?->format('d/m/Y H:i'), 'Status' => $human($record->status)], 'processSteps' => $steps, 'schedules' => $record->paymentSchedules->map(fn ($r) => ['invoice' => $r->invoice_no, 'description' => $r->description, 'due_date' => $r->due_date?->format('d/m/Y'), 'amount' => $money($r->amount), 'paid' => $money($r->paid_amount), 'status' => $human($r->status), 'url' => $r->invoice_no ? route('admin.receivables.invoice', $r, absolute: false) : null])->all(), 'payments' => $record->customerReceipts->map(fn ($r) => ['label' => $r->receipt_no, 'date' => $r->payment_date?->format('d/m/Y'), 'value' => $money($r->amount), 'status' => $human($r->status), 'notes' => $r->bank_reference, 'url' => route('admin.customer-receipts.preview', $r, absolute: false)])->all(), 'construction' => $progress->all(), 'handover' => $handover->all(), 'afterSales' => $after->all(), 'timeline' => $record->workflowHistories->sortByDesc('occurred_at')->map(fn ($r) => ['title' => $human($r->process).' — '.$human($r->to_status), 'date' => $r->occurred_at?->format('d/m/Y H:i'), 'notes' => $r->notes])->all()];
    }

    private function enrichedTransactionPayload(SalesTransaction $record, callable $human, callable $money, array $steps, $progress, $handover, $after): array
    {
        $record->loadMissing(['paymentSchedules.source', 'customerReceipts.allocations.schedule', 'customerReceipts.bankAccount', 'customerReceipts.pettyCashAccount']);
        $receivables = app(CustomerReceivableService::class);
        $purposeLabels = ['booking_fee' => 'Booking Fee', 'down_payment' => 'Uang Muka / DP', 'invoice_payment' => 'Pembayaran Tagihan', 'accelerated_payment' => 'Percepatan Tagihan', 'overpayment' => 'Pembayaran Lebih', 'other' => 'Penerimaan Lainnya'];
        $schedules = $record->paymentSchedules->map(function ($row) use ($money, $human, $receivables) {
            $assessedPenalty = (float) (($row->calculation_snapshot ?? [])['penalty_assessed_amount'] ?? 0);
            $bill = max(0, (float) $row->amount - $assessedPenalty);
            $penalty = $assessedPenalty + $receivables->calculateSchedulePenalty($row, today());
            $total = $bill + $penalty;
            $remaining = max(0, $total - (float) $row->paid_amount);

            return ['invoice' => $row->invoice_no, 'description' => $row->description, 'due_date' => $row->due_date?->format('d/m/Y'), 'amount' => $money($bill), 'penalty' => $money($penalty), 'total_due' => $money($total), 'paid' => $money($row->paid_amount), 'remaining' => $money($remaining), 'status' => $human($row->status), 'url' => $row->invoice_no ? route('admin.receivables.invoice', $row, absolute: false) : null];
        })->all();
        $payments = $record->customerReceipts->sortByDesc('payment_date')->map(function ($row) use ($money, $human, $purposeLabels) {
            $destination = $row->payment_method === 'cash' ? ($row->pettyCashAccount?->name ?? 'Kas Kecil') : trim(($row->bankAccount?->nama_bank ?? '').' - '.($row->bankAccount?->nomor_rekening ?? ''), ' -');

            return ['label' => $row->receipt_no, 'date' => $row->payment_date?->format('d/m/Y'), 'value' => $money($row->amount), 'status' => $human($row->status), 'purpose' => $purposeLabels[$row->receipt_purpose] ?? $human($row->receipt_purpose), 'method' => $row->payment_method === 'cash' ? 'Cash' : 'Transfer', 'destination' => $destination ?: '-', 'sender' => $row->sender_name ?: '-', 'reference' => $row->bank_reference ?: '-', 'notes' => $row->notes, 'allocations' => $row->allocations->map(fn ($allocation) => ['label' => $allocation->schedule ? trim(($allocation->schedule->invoice_no ?? '').' - '.($allocation->schedule->description ?? ''), ' -') : 'Deposit belum dialokasikan', 'amount' => $money($allocation->amount)])->all(), 'url' => route('admin.customer-receipts.preview', $row, absolute: false)];
        })->values()->all();

        return ['heading' => $record->transaction_no, 'subtitle' => ($record->customer?->nama ?? 'Customer').' — '.($record->housingUnit?->nomor_rumah ?? 'Unit'), 'summary' => ['Nomor Transaksi' => $record->transaction_no, 'Nomor SPR' => $record->spr?->kode_spr, 'Customer' => $record->customer?->nama, 'No. Identitas' => $record->customer?->no_identitas, 'Telepon' => $record->customer?->telepon, 'Perumahan' => $record->housingProject?->nama_perusahaan, 'Unit' => trim(($record->housingUnit?->kode_nlok ?? '').' / '.($record->housingUnit?->nomor_rumah ?? '')), 'Tipe Unit' => $record->housingUnit?->tipe_rumah, 'Marketing' => $record->marketing?->name, 'Metode Pembayaran' => $human($record->payment_method), 'Harga Jual' => $money($record->sale_price_snapshot), 'Tanggal Disetujui' => $record->approved_at?->format('d/m/Y H:i'), 'Status' => $human($record->status)], 'processSteps' => $steps, 'schedules' => $schedules, 'payments' => $payments, 'construction' => $progress->all(), 'handover' => $handover->all(), 'afterSales' => $after->all(), 'timeline' => $record->workflowHistories->sortByDesc('occurred_at')->map(fn ($row) => ['title' => $human($row->process).' — '.$human($row->to_status), 'date' => $row->occurred_at?->format('d/m/Y H:i'), 'notes' => $row->notes])->all()];
    }

    private function config(string $section): array
    {
        return $this->configs()[$section] ?? abort(404);
    }

    private function configs(): array
    {
        $text = fn ($name, $label, $required = true) => ['name' => $name, 'label' => $label, 'type' => 'text', 'required' => $required];
        $num = fn ($name, $label) => ['name' => $name, 'label' => $label, 'type' => 'number', 'required' => true];
        $date = fn ($name, $label, $required = true) => ['name' => $name, 'label' => $label, 'type' => 'date', 'required' => $required];
        $select = fn ($name, $label, $key, $required = true) => ['name' => $name, 'label' => $label, 'type' => 'select', 'optionsKey' => $key, 'required' => $required];
        $col = fn ($name, $label) => ['name' => $name, 'label' => $label];
        $configs = [
            'transactions' => ['title' => 'Transaksi Penjualan', 'permission' => 'sales.transactions', 'table' => 'sales_transactions', 'editable' => false, 'fields' => [], 'columns' => [$col('transaction_no', 'Nomor Transaksi'), $col('customer_name', 'Customer'), $col('housing_name', 'Perumahan'), $col('unit_name', 'Unit'), $col('payment_method_label', 'Metode Pembayaran'), $col('sale_price_snapshot', 'Harga Jual'), $col('status_label', 'Status')], 'joins' => [['costumers', 'costumers.id', '=', 'sales_transactions.costumer_id'], ['perumahans', 'perumahans.id', '=', 'sales_transactions.perumahan_id'], ['detail_rumahs', 'detail_rumahs.id', '=', 'sales_transactions.detail_rumah_id']], 'select' => ['sales_transactions.id', 'sales_transactions.transaction_no', 'costumers.nama as customer_name', 'perumahans.nama_perusahaan as housing_name', 'detail_rumahs.nomor_rumah as unit_name', DB::raw("CASE sales_transactions.payment_method WHEN 'cash' THEN 'Cash' WHEN 'cash_bertahap' THEN 'Cash Bertahap' WHEN 'kpr_bank' THEN 'KPR Bank' WHEN 'kpr_developer' THEN 'KPR Developer' ELSE sales_transactions.payment_method END as payment_method_label"), 'sales_transactions.sale_price_snapshot', DB::raw("CASE sales_transactions.status WHEN 'active' THEN 'Aktif' WHEN 'cancelled' THEN 'Dibatalkan' ELSE sales_transactions.status END as status_label")], 'search' => ['sales_transactions.transaction_no', 'costumers.nama', 'perumahans.nama_perusahaan']],
            'schemes' => ['title' => 'Skema Cash Bertahap', 'permission' => 'cash-installment.schemes', 'table' => 'cash_installment_schemes', 'editable' => true, 'fields' => [$text('code', 'Kode Skema'), $text('name', 'Nama Skema'), $select('cabang_perusahaan_id', 'Cabang', 'branches', false), $select('perumahan_id', 'Perumahan', 'housing', false), $num('minimum_booking_fee', 'Minimum Booking Fee'), $num('minimum_dp', 'Minimum Uang Muka'), $num('installment_count', 'Jumlah Tahap'), $num('maximum_tenor_months', 'Tenor Maksimal (Bulan)'), $num('grace_period_days', 'Masa Tenggang (Hari)'), $text('penalty_method', 'Metode Denda'), $num('penalty_value', 'Nilai Denda'), $date('effective_from', 'Mulai Berlaku'), $date('effective_until', 'Berakhir', false), ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draf', 'aktif' => 'Aktif', 'nonaktif' => 'Tidak Aktif'], 'required' => true], $text('handover_terms', 'Syarat Serah Terima', false), $text('requirements', 'Persyaratan (pisahkan koma)', false), $text('notes', 'Catatan', false)], 'columns' => [$col('code', 'Kode'), $col('name', 'Nama Skema'), $col('housing_name', 'Perumahan'), $col('installment_count', 'Jumlah Tahap'), $col('maximum_tenor_months', 'Tenor Maksimal'), $col('status_label', 'Status')], 'joins' => [['perumahans', 'perumahans.id', '=', 'cash_installment_schemes.perumahan_id']], 'select' => ['cash_installment_schemes.*', 'perumahans.nama_perusahaan as housing_name', DB::raw("CASE cash_installment_schemes.status WHEN 'aktif' THEN 'Aktif' WHEN 'draft' THEN 'Draf' ELSE 'Tidak Aktif' END as status_label")], 'rules' => ['code' => 'required|string|max:50|unique:cash_installment_schemes,code', 'name' => 'required|string|max:255', 'cabang_perusahaan_id' => 'nullable|exists:cabang_perusahaans,id', 'perumahan_id' => 'nullable|exists:perumahans,id', 'minimum_booking_fee' => 'required|numeric|min:0', 'minimum_dp' => 'required|numeric|min:0', 'installment_count' => 'required|integer|min:1', 'maximum_tenor_months' => 'required|integer|min:1', 'grace_period_days' => 'required|integer|min:0', 'penalty_method' => 'required|string', 'penalty_value' => 'required|numeric|min:0', 'effective_from' => 'required|date', 'effective_until' => 'nullable|date|after_or_equal:effective_from', 'status' => 'required|in:draft,aktif,nonaktif', 'handover_terms' => 'nullable|string', 'requirements' => 'nullable|string', 'notes' => 'nullable|string'], 'search' => ['cash_installment_schemes.code', 'cash_installment_schemes.name', 'perumahans.nama_perusahaan']],
            'scheme-steps' => ['title' => 'Tahapan Cash Bertahap', 'permission' => 'cash-installment.scheme-steps', 'table' => 'cash_installment_scheme_steps', 'editable' => true, 'fields' => [$select('cash_installment_scheme_id', 'Skema', 'schemes'), $num('sequence', 'Urutan'), $text('name', 'Nama Tahap'), ['name' => 'calculation_type', 'label' => 'Jenis Perhitungan', 'type' => 'select', 'options' => ['fixed' => 'Nominal Tetap', 'percentage_sale' => 'Persentase Harga Jual', 'remaining' => 'Sisa Pelunasan Otomatis'], 'required' => true], $num('value', 'Nominal / Persentase'), $num('due_offset_months', 'Jatuh Tempo Setelah (Bulan)'), $text('required_before', 'Wajib Sebelum Proses', false), $text('notes', 'Catatan', false)], 'columns' => [$col('scheme_name', 'Skema'), $col('sequence', 'Urutan'), $col('name', 'Nama Tahap'), $col('calculation_label', 'Perhitungan'), $col('value', 'Nilai'), $col('due_offset_months', 'Jarak Jatuh Tempo')], 'joins' => [['cash_installment_schemes', 'cash_installment_schemes.id', '=', 'cash_installment_scheme_steps.cash_installment_scheme_id']], 'select' => ['cash_installment_scheme_steps.*', 'cash_installment_schemes.name as scheme_name', DB::raw("CASE cash_installment_scheme_steps.calculation_type WHEN 'fixed' THEN 'Nominal Tetap' WHEN 'percentage_sale' THEN 'Persentase Harga Jual' ELSE 'Sisa Pelunasan' END as calculation_label")], 'rules' => ['cash_installment_scheme_id' => 'required|exists:cash_installment_schemes,id', 'sequence' => 'required|integer|min:1', 'name' => 'required|string|max:255', 'calculation_type' => 'required|in:fixed,percentage_sale,remaining', 'value' => 'required|numeric|min:0', 'due_offset_months' => 'required|integer|min:0', 'required_before' => 'nullable|string', 'notes' => 'nullable|string'], 'search' => ['cash_installment_schemes.name', 'cash_installment_scheme_steps.name']],
            'contracts' => ['title' => 'Kontrak Cash Bertahap', 'permission' => 'cash-installment.contracts', 'table' => 'cash_installment_contracts', 'editable' => false, 'fields' => [], 'columns' => [$col('contract_no', 'Nomor Kontrak'), $col('transaction_no', 'Transaksi'), $col('customer_name', 'Customer'), $col('scheme_name', 'Skema'), $col('contract_value', 'Nilai Kontrak'), $col('status_label', 'Status')], 'joins' => [['sales_transactions', 'sales_transactions.id', '=', 'cash_installment_contracts.sales_transaction_id'], ['costumers', 'costumers.id', '=', 'sales_transactions.costumer_id'], ['cash_installment_schemes', 'cash_installment_schemes.id', '=', 'cash_installment_contracts.cash_installment_scheme_id']], 'select' => ['cash_installment_contracts.id', 'cash_installment_contracts.contract_no', 'cash_installment_contracts.record_status', 'sales_transactions.transaction_no', 'costumers.nama as customer_name', 'cash_installment_schemes.name as scheme_name', 'cash_installment_contracts.contract_value', DB::raw("CASE cash_installment_contracts.status WHEN 'draft' THEN 'Draf' WHEN 'active' THEN 'Aktif' ELSE cash_installment_contracts.status END as status_label")], 'search' => ['cash_installment_contracts.contract_no', 'sales_transactions.transaction_no', 'costumers.nama']],
            'developer-products' => ['title' => 'Produk KPR Developer', 'permission' => 'developer-kpr.products', 'table' => 'developer_kpr_products', 'editable' => true, 'fields' => [$text('code', 'Kode Produk'), $text('name', 'Nama Produk'), $select('cabang_perusahaan_id', 'Cabang', 'branches', false), $select('perumahan_id', 'Perumahan', 'housing', false), $num('minimum_dp', 'Minimum Uang Muka'), $num('maximum_financing', 'Maksimum Pembiayaan'), $num('minimum_tenor_months', 'Tenor Minimum'), $num('maximum_tenor_months', 'Tenor Maksimum'), $text('allowed_tenors', 'Pilihan Tenor (pisahkan koma)', false), $num('annual_margin', 'Margin Tahunan (%)'), ['name' => 'margin_method', 'label' => 'Metode Margin', 'type' => 'select', 'options' => ['flat' => 'Tetap', 'effective' => 'Efektif', 'annuity' => 'Anuitas'], 'required' => true], $num('administration_fee', 'Biaya Administrasi'), $num('contract_fee', 'Biaya Akad'), $num('grace_period_days', 'Masa Tenggang (Hari)'), $text('penalty_method', 'Metode Denda'), $num('penalty_value', 'Nilai Denda'), $num('minimum_income', 'Penghasilan Minimum'), $num('maximum_age', 'Usia Maksimum'), $date('effective_from', 'Mulai Berlaku'), $date('effective_until', 'Berakhir', false), ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draf', 'aktif' => 'Aktif', 'nonaktif' => 'Tidak Aktif'], 'required' => true], $text('requirements', 'Persyaratan (pisahkan koma)', false), $text('handover_terms', 'Syarat Serah Terima', false), $text('notes', 'Catatan', false)], 'columns' => [$col('code', 'Kode'), $col('name', 'Nama Produk'), $col('housing_name', 'Perumahan'), $col('maximum_tenor_months', 'Tenor Maksimum'), $col('annual_margin', 'Margin'), $col('status_label', 'Status')], 'joins' => [['perumahans', 'perumahans.id', '=', 'developer_kpr_products.perumahan_id']], 'select' => ['developer_kpr_products.*', 'perumahans.nama_perusahaan as housing_name', DB::raw("CASE developer_kpr_products.status WHEN 'aktif' THEN 'Aktif' WHEN 'draft' THEN 'Draf' ELSE 'Tidak Aktif' END as status_label")], 'rules' => ['code' => 'required|string|max:50|unique:developer_kpr_products,code', 'name' => 'required|string|max:255', 'cabang_perusahaan_id' => 'nullable|exists:cabang_perusahaans,id', 'perumahan_id' => 'nullable|exists:perumahans,id', 'minimum_dp' => 'required|numeric|min:0', 'maximum_financing' => 'required|numeric|min:0', 'minimum_tenor_months' => 'required|integer|min:1', 'maximum_tenor_months' => 'required|integer|gte:minimum_tenor_months', 'allowed_tenors' => 'nullable|string', 'annual_margin' => 'required|numeric|min:0', 'margin_method' => 'required|in:flat,effective,annuity', 'administration_fee' => 'required|numeric|min:0', 'contract_fee' => 'required|numeric|min:0', 'grace_period_days' => 'required|integer|min:0', 'penalty_method' => 'required|string', 'penalty_value' => 'required|numeric|min:0', 'minimum_income' => 'required|numeric|min:0', 'maximum_age' => 'required|integer|min:1', 'requirements' => 'nullable|string', 'handover_terms' => 'nullable|string', 'effective_from' => 'required|date', 'effective_until' => 'nullable|date|after_or_equal:effective_from', 'status' => 'required|in:draft,aktif,nonaktif', 'notes' => 'nullable|string'], 'search' => ['developer_kpr_products.code', 'developer_kpr_products.name', 'perumahans.nama_perusahaan']],
            'developer-applications' => ['title' => 'Pengajuan KPR Developer', 'permission' => 'developer-kpr.applications', 'table' => 'developer_kpr_applications', 'editable' => false, 'fields' => [], 'columns' => [$col('application_no', 'Nomor Pengajuan'), $col('transaction_no', 'Transaksi'), $col('customer_name', 'Customer'), $col('product_name', 'Produk'), $col('financing_amount', 'Nilai Pembiayaan'), $col('tenor_months', 'Tenor'), $col('status_label', 'Status')], 'joins' => [['sales_transactions', 'sales_transactions.id', '=', 'developer_kpr_applications.sales_transaction_id'], ['costumers', 'costumers.id', '=', 'sales_transactions.costumer_id'], ['developer_kpr_products', 'developer_kpr_products.id', '=', 'developer_kpr_applications.developer_kpr_product_id']], 'select' => ['developer_kpr_applications.id', 'developer_kpr_applications.application_no', 'developer_kpr_applications.record_status', 'sales_transactions.transaction_no', 'costumers.nama as customer_name', 'developer_kpr_products.name as product_name', 'developer_kpr_applications.financing_amount', 'developer_kpr_applications.tenor_months', DB::raw("CASE developer_kpr_applications.status WHEN 'draft' THEN 'Draf' WHEN 'approved' THEN 'Disetujui' ELSE developer_kpr_applications.status END as status_label")], 'search' => ['developer_kpr_applications.application_no', 'sales_transactions.transaction_no', 'costumers.nama']],
        ];

        $scheduleConfig = ['title' => 'Jadwal Pembayaran', 'permission' => 'cash-installment.schedules', 'table' => 'payment_schedules', 'editable' => false, 'fields' => [], 'columns' => [$col('transaction_no', 'Transaksi'), $col('customer_name', 'Customer'), $col('description', 'Tagihan'), $col('due_date', 'Jatuh Tempo'), $col('amount', 'Nominal'), $col('paid_amount', 'Dibayar'), $col('status_label', 'Status')], 'joins' => [['sales_transactions', 'sales_transactions.id', '=', 'payment_schedules.sales_transaction_id'], ['costumers', 'costumers.id', '=', 'sales_transactions.costumer_id']], 'select' => ['payment_schedules.id', 'sales_transactions.transaction_no', 'costumers.nama as customer_name', 'payment_schedules.description', 'payment_schedules.due_date', 'payment_schedules.amount', 'payment_schedules.paid_amount', DB::raw("CASE payment_schedules.status WHEN 'belum_dibayar' THEN 'Belum Dibayar' WHEN 'sebagian' THEN 'Dibayar Sebagian' WHEN 'lunas' THEN 'Lunas' WHEN 'jatuh_tempo' THEN 'Jatuh Tempo' ELSE payment_schedules.status END as status_label")], 'search' => ['sales_transactions.transaction_no', 'costumers.nama', 'payment_schedules.description']];
        $aliases = [
            'scheme-detail' => ['schemes', 'Detail Skema Cash Bertahap', 'cash-installment.scheme-detail'],
            'scheme-housing' => ['schemes', 'Perumahan Pengguna Skema', 'cash-installment.scheme-housing'],
            'scheme-fees' => ['schemes', 'Biaya dan Denda Cash Bertahap', 'cash-installment.scheme-fees'],
            'scheme-requirements' => ['schemes', 'Syarat Customer Cash Bertahap', 'cash-installment.scheme-requirements'],
            'scheme-documents' => ['schemes', 'Dokumen Wajib Cash Bertahap', 'cash-installment.scheme-documents'],
            'scheme-versions' => ['schemes', 'Versi Skema Cash Bertahap', 'cash-installment.scheme-versions'],
            'scheme-history' => ['schemes', 'Riwayat Perubahan Skema', 'cash-installment.scheme-history'],
            'scheme-reports' => ['schemes', 'Laporan Skema Cash Bertahap', 'cash-installment.scheme-reports'],
            'contract-detail' => ['contracts', 'Detail Kontrak Cash Bertahap', 'cash-installment.contract-detail'],
            'approvals' => ['contracts', 'Persetujuan Skema Cash Bertahap', 'cash-installment.approvals'],
            'settlements' => ['contracts', 'Pelunasan Cash Bertahap', 'cash-installment.settlements'],
            'restructuring' => ['contracts', 'Restrukturisasi Cash Bertahap', 'cash-installment.restructuring'],
            'cancellations' => ['contracts', 'Pembatalan Kontrak Cash Bertahap', 'cash-installment.cancellations'],
            'reports' => ['contracts', 'Laporan Cash Bertahap', 'cash-installment.reports'],
            'developer-product-detail' => ['developer-products', 'Detail Produk KPR Developer', 'developer-kpr.product-detail'],
            'developer-product-housing' => ['developer-products', 'Perumahan Pengguna Produk', 'developer-kpr.product-housing'],
            'developer-financing-terms' => ['developer-products', 'Ketentuan Pembiayaan', 'developer-kpr.financing-terms'],
            'developer-margins' => ['developer-products', 'Margin KPR Developer', 'developer-kpr.margins'],
            'developer-fees' => ['developer-products', 'Biaya KPR Developer', 'developer-kpr.fees'],
            'developer-requirements' => ['developer-products', 'Persyaratan Customer KPR Developer', 'developer-kpr.requirements'],
            'developer-documents' => ['developer-products', 'Dokumen Wajib KPR Developer', 'developer-kpr.documents'],
            'developer-risk-approval' => ['developer-products', 'Approval dan Batas Risiko', 'developer-kpr.risk-approval'],
            'developer-penalties' => ['developer-products', 'Denda dan Masa Tenggang', 'developer-kpr.penalties'],
            'developer-early-settlement' => ['developer-products', 'Pelunasan Dipercepat', 'developer-kpr.early-settlement'],
            'developer-product-versions' => ['developer-products', 'Versi Produk KPR Developer', 'developer-kpr.product-versions'],
            'developer-product-history' => ['developer-products', 'Riwayat Produk KPR Developer', 'developer-kpr.product-history'],
            'developer-product-reports' => ['developer-products', 'Laporan Produk KPR Developer', 'developer-kpr.product-reports'],
            'developer-application-detail' => ['developer-applications', 'Detail Pengajuan KPR Developer', 'developer-kpr.application-detail'],
            'developer-affordability-analysis' => ['developer-applications', 'Analisis Kemampuan Bayar', 'developer-kpr.affordability-analysis'],
            'developer-document-validation' => ['developer-applications', 'Validasi Dokumen KPR Developer', 'developer-kpr.document-validation'],
            'developer-internal-approval' => ['developer-applications', 'Persetujuan Internal KPR Developer', 'developer-kpr.internal-approval'],
            'developer-contracts' => ['developer-applications', 'Kontrak KPR Developer', 'developer-kpr.contracts'],
            'developer-receivables' => ['developer-applications', 'Monitoring Piutang KPR Developer', 'developer-kpr.receivables'],
            'developer-arrears' => ['developer-applications', 'Monitoring Tunggakan KPR Developer', 'developer-kpr.arrears'],
            'developer-payments' => ['developer-applications', 'Pembayaran dan Pelunasan KPR Developer', 'developer-kpr.payments'],
            'developer-restructuring' => ['developer-applications', 'Restrukturisasi KPR Developer', 'developer-kpr.restructuring'],
            'developer-cancellations' => ['developer-applications', 'Pembatalan KPR Developer', 'developer-kpr.cancellations'],
            'developer-reports' => ['developer-applications', 'Laporan KPR Developer', 'developer-kpr.reports'],
        ];
        foreach ($aliases as $key => [$source,$title,$permission]) {
            $configs[$key] = [...$configs[$source], 'title' => $title, 'permission' => $permission, 'editable' => false];
        }
        $bankApplications = ['title' => 'Pengajuan KPR Bank', 'permission' => 'bank-kpr.applications', 'table' => 'kpr_submissions', 'editable' => false, 'fields' => [], 'columns' => [$col('kode_kpr', 'Nomor Pengajuan'), $col('kode_spr', 'Nomor SPR'), $col('customer_name', 'Customer'), $col('housing_name', 'Perumahan'), $col('unit_name', 'Unit'), $col('bank_name', 'Bank'), $col('status_label', 'Status')], 'joins' => [['sprs', 'sprs.id', '=', 'kpr_submissions.spr_id'], ['costumers', 'costumers.id', '=', 'sprs.costumer_id'], ['detail_rumahs', 'detail_rumahs.id', '=', 'sprs.detail_rumah_id'], ['perumahans', 'perumahans.id', '=', 'detail_rumahs.perumahan_id'], ['bank_kredits', 'bank_kredits.id', '=', 'kpr_submissions.bank_kredit_id']], 'select' => ['kpr_submissions.id', 'kpr_submissions.kode_kpr', 'sprs.kode_spr', 'costumers.nama as customer_name', 'perumahans.nama_perusahaan as housing_name', 'detail_rumahs.nomor_rumah as unit_name', 'bank_kredits.nama_bank as bank_name', DB::raw("CASE kpr_submissions.status WHEN 'pengumpulan_dokumen' THEN 'Pengumpulan Dokumen' WHEN 'slik_menunggu' THEN 'Menunggu SLIK' WHEN 'analisa_diproses' THEN 'Analisis Diproses' WHEN 'disetujui' THEN 'Disetujui' WHEN 'ditolak' THEN 'Ditolak' ELSE kpr_submissions.status END as status_label")], 'search' => ['kpr_submissions.kode_kpr', 'sprs.kode_spr', 'costumers.nama', 'bank_kredits.nama_bank']];
        $bankPages = ['bank-applications' => ['Pengajuan KPR Bank', 'applications'], 'bank-application-detail' => ['Detail Pengajuan KPR Bank', 'application-detail'], 'bank-document-validation' => ['Validasi Dokumen KPR', 'document-validation'], 'bank-slik' => ['Proses SLIK', 'slik'], 'bank-appraisal' => ['Appraisal / Survei', 'appraisal'], 'bank-decision' => ['Keputusan Bank', 'bank-decision'], 'bank-sp3k' => ['SP3K', 'sp3k'], 'bank-contract-preparation' => ['Persiapan Akad', 'contract-preparation'], 'bank-contract-schedule' => ['Jadwal Akad', 'contract-schedule'], 'bank-contract-execution' => ['Pelaksanaan Akad', 'contract-execution'], 'bank-disbursement' => ['Monitoring Pencairan', 'disbursement'], 'bank-change' => ['Perubahan Bank', 'bank-change'], 'bank-rejections' => ['Pengajuan Ditolak', 'rejections'], 'bank-reports' => ['Laporan KPR Bank', 'reports']];
        foreach ($bankPages as $key => [$title,$permission]) {
            $configs[$key] = [...$bankApplications, 'title' => $title, 'permission' => 'bank-kpr.'.$permission];
        }
        $configs['schedules'] = $scheduleConfig;
        $configs['billings'] = [...$scheduleConfig, 'title' => 'Monitoring Tagihan Cash Bertahap', 'permission' => 'cash-installment.billings'];
        $configs['arrears'] = [...$scheduleConfig, 'title' => 'Monitoring Tunggakan Cash Bertahap', 'permission' => 'cash-installment.arrears', 'conditions' => [['payment_schedules.status', '=', 'jatuh_tempo']]];
        $configs['payment-history'] = [...$scheduleConfig, 'title' => 'Riwayat Pembayaran Cash Bertahap', 'permission' => 'cash-installment.payment-history'];
        $configs['developer-schedules'] = [...$scheduleConfig, 'title' => 'Jadwal Angsuran KPR Developer', 'permission' => 'developer-kpr.schedules'];

        return $configs;
    }
}
