<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerDocumentChecklist;
use App\Models\HousingReservation;
use App\Models\InternalHandover;
use App\Models\KprSubmission;
use App\Models\MarketingLead;
use App\Models\MarketingLeadAssignment;
use App\Models\MarketingLeadSource;
use App\Models\MarketingVisit;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\SalesActivityLog;
use App\Models\SalesProcessStep;
use App\Models\SalesWorkItem;
use App\Models\Spr;
use App\Models\User;
use App\Services\SalesActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesWorkspaceController extends Controller
{
    public function __construct(private readonly SalesActivityLogger $logger) {}

    public function dashboard(Request $request): Response
    {
        $this->allow($request, 'admin-sales.dashboard.view');
        $work = $this->scopeWork(SalesWorkItem::query(), $request);
        $cards = [
            $this->card('Lead Qualified Belum Diverifikasi', MarketingLead::query()->where('qualification_status', 'submitted')->where('verification_status', 'pending')->count(), 'lead-unverified'),
            $this->card('Lead Belum Dibagikan', MarketingLead::query()->where('ownership_type', 'company')->whereNull('marketing_id')->count(), 'lead-unassigned'),
            $this->card('Respons Marketing Terlambat', MarketingLead::query()->whereNull('first_contacted_at')->where('first_response_due_at', '<', now())->count(), 'response-overdue'),
            $this->card('Follow-up Perlu Diperiksa', CostumerFollowUp::query()->where('admin_review_status', 'pending')->count(), 'followup-review'),
            $this->card('Laporan Kunjungan Masuk', MarketingVisit::query()->where('status', 'completed')->where('admin_review_status', 'pending')->count(), 'visit-review'),
            ['label' => 'Customer Tanpa Unit Pilihan', 'count' => Costumer::query()->whereDoesntHave('unitInterests')->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->count(), 'href' => '/admin/marketing/calon-konsumen?administrative_gap=unit'],
            ['label' => 'Customer Tanpa Metode Pembayaran', 'count' => Costumer::query()->whereNull('preferred_payment_method')->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->count(), 'href' => '/admin/marketing/calon-konsumen?administrative_gap=payment_method'],
            ['label' => 'Administrasi Customer Belum Lengkap', 'count' => Costumer::query()->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->where(function (Builder $query) {
                $query->whereNull('no_identitas')->orWhereNull('alamat')->orWhereNull('pekerjaan')->orWhereNull('penghasilan')->orWhereNull('preferred_payment_method')->orWhereDoesntHave('unitInterests')->orWhereDoesntHave('documentChecklists', fn (Builder $checklist) => $checklist->where('validation_status', 'complete'));
            })->count(), 'href' => '/admin/admin-sales/kelengkapan-customer'],
            ['label' => 'Dokumen Belum Lengkap', 'count' => CustomerDocumentChecklist::query()->where('validation_status', '!=', 'complete')->count(), 'href' => '/admin/marketing/crm/document-checklists?validation_status=incomplete'],
            ['label' => 'Reservasi Perlu Diproses', 'count' => HousingReservation::query()->whereIn('status', ['draft', 'pending', 'submitted'])->count(), 'href' => '/admin/marketing/reservasi-perumahan'],
            ['label' => 'Reservasi Mendekati Kedaluwarsa', 'count' => HousingReservation::query()->where('payment_status', '!=', 'paid')->whereBetween('payment_due_at', [now(), now()->addDays(3)])->count(), 'href' => '/admin/marketing/reservasi-perumahan?queue=expiring'],
            ['label' => 'Booking Fee Menunggu Verifikasi', 'count' => HousingReservation::query()->where('payment_approval_status', 'pending')->count(), 'href' => '/admin/keuangan/penerimaan-customer#verifikasi-booking-fee'],
            ['label' => 'SPR Perlu Diproses', 'count' => Spr::query()->whereNotIn('status', [Spr::STATUS_DISETUJUI, Spr::STATUS_DITOLAK])->count(), 'href' => '/admin/marketing/spr'],
            ['label' => 'Pembayaran Terlambat', 'count' => PaymentSchedule::query()->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', '<', today())->count(), 'href' => '/admin/keuangan/monitoring-jatuh-tempo?urgency=overdue'],
            ['label' => 'KPR Tidak Diperbarui 3 Hari', 'count' => KprSubmission::query()->whereNotIn('status', ['approved', 'rejected', 'cancelled', 'disbursed'])->where('updated_at', '<', now()->subDays(3))->count(), 'href' => '/admin/penjualan-terintegrasi/bank-applications?queue=stale'],
            ['label' => 'Agenda Penjualan 30 Hari', 'count' => SalesProcessStep::query()->whereIn('code', ['appraisal', 'contract_preparation', 'contract_signing', 'bank_disbursement', 'internal_handover', 'customer_handover'])->whereBetween('planned_date', [today(), today()->addDays(30)])->whereNotIn('status', ['completed', 'skipped'])->count(), 'href' => '/admin/admin-sales/kalender-penjualan'],
            ['label' => 'Follow-up Perlu Revisi', 'count' => CostumerFollowUp::query()->where('admin_review_status', 'needs_revision')->count(), 'href' => '/admin/admin-sales/monitoring?queue=followup-revision'],
            ['label' => 'Kunjungan Perlu Revisi', 'count' => MarketingVisit::query()->where('admin_review_status', 'needs_revision')->count(), 'href' => '/admin/admin-sales/monitoring?queue=visit-revision'],
            ['label' => 'Tugas Terlambat', 'count' => (clone $work)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(), 'href' => route('admin.admin-sales.work-items.index', ['status' => 'overdue'], false)],
        ];

        return Inertia::render('Admin/AdminSales/Dashboard', [
            'title' => 'Meja Kerja Admin Sales', 'cards' => $cards,
            'workItems' => $this->scopeWork(SalesWorkItem::query()->with(['customer:id,nama,kode_costumer', 'lead:id,name,lead_no', 'assignee:id,name']), $request)->whereNotIn('status', ['completed', 'cancelled'])->orderBy('due_at')->limit(12)->get()->map(fn ($x) => $this->workRow($x)),
            'recentLogs' => SalesActivityLog::query()->with('user:id,name')->latest()->limit(15)->get()->map(fn ($x) => ['id' => $x->id, 'event' => $x->event, 'status' => $x->new_status, 'reason' => $x->reason, 'user' => $x->user?->name, 'at' => $x->created_at?->format('d/m/Y H:i')]),
        ]);
    }

    public function monitoring(Request $request): Response
    {
        $this->allow($request, 'admin-sales.monitoring.view');
        $queue = (string) $request->query('queue', 'lead-unverified');
        $rows = match ($queue) {
            'lead-unassigned' => MarketingLead::query()->where('ownership_type', 'company')->whereNull('marketing_id')->with(['source:id,nama_sumber', 'marketing:id,name'])->latest()->paginate(25),
            'response-overdue' => MarketingLead::query()->whereNull('first_contacted_at')->where('first_response_due_at', '<', now())->with(['source:id,nama_sumber', 'marketing:id,name'])->oldest('first_response_due_at')->paginate(25),
            'followup-review' => CostumerFollowUp::query()->where('admin_review_status', 'pending')->with(['lead:id,name,lead_no', 'user:id,name'])->latest('followed_up_at')->paginate(25),
            'followup-revision' => CostumerFollowUp::query()->where('admin_review_status', 'needs_revision')->with(['lead:id,name,lead_no', 'user:id,name'])->latest('admin_reviewed_at')->paginate(25),
            'visit-review' => MarketingVisit::query()->where('status', 'completed')->where('admin_review_status', 'pending')->with(['costumer:id,nama,kode_costumer', 'marketing:id,name'])->latest('finished_at')->paginate(25),
            'visit-revision' => MarketingVisit::query()->where('admin_review_status', 'needs_revision')->with(['costumer:id,nama,kode_costumer', 'marketing:id,name'])->latest('admin_reviewed_at')->paginate(25),
            default => MarketingLead::query()->where('qualification_status', 'submitted')->where('verification_status', 'pending')->with(['source:id,nama_sumber', 'marketing:id,name'])->latest()->paginate(25),
        };

        return Inertia::render('Admin/AdminSales/Monitoring', ['title' => 'Monitoring Admin Sales', 'queue' => $queue, 'rows' => $rows]);
    }

    public function customerReadiness(Request $request): Response
    {
        $this->allow($request, 'admin-sales.monitoring.view');
        $search = trim((string) $request->query('search', ''));
        $gap = (string) $request->query('gap', '');
        $query = Costumer::query()->with(['assignedMarketing:id,name', 'adminSales:id,name', 'perumahan:id,nama_perusahaan', 'unitInterests.unit:id,kode_nlok,nomor_rumah', 'documentChecklists:id,costumer_id,process_stage,items,completion_percentage,validation_status'])
            ->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('nama', 'like', "%{$search}%")->orWhere('kode_costumer', 'like', "%{$search}%")->orWhere('telepon', 'like', "%{$search}%")->orWhere('no_identitas', 'like', "%{$search}%")))
            ->when($gap === 'profile', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('no_identitas')->orWhereNull('alamat')->orWhereNull('tanggal_lahir')->orWhereNull('pekerjaan')->orWhereNull('penghasilan')))
            ->when($gap === 'unit', fn (Builder $query) => $query->whereDoesntHave('unitInterests'))
            ->when($gap === 'payment', fn (Builder $query) => $query->whereNull('preferred_payment_method'))
            ->when($gap === 'documents', fn (Builder $query) => $query->whereDoesntHave('documentChecklists', fn (Builder $checklist) => $checklist->where('validation_status', 'complete')));

        $rows = $query->orderByRaw('last_activity_at IS NULL DESC')->orderBy('last_activity_at')->paginate(20)->withQueryString()->through(fn (Costumer $customer) => $this->customerReadinessRow($customer));

        return Inertia::render('Admin/AdminSales/CustomerReadiness', [
            'title' => 'Kelengkapan Administrasi Customer', 'rows' => $rows, 'filters' => ['search' => $search, 'gap' => $gap],
            'summary' => [
                'total' => Costumer::query()->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->count(),
                'without_unit' => Costumer::query()->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->whereDoesntHave('unitInterests')->count(),
                'without_payment' => Costumer::query()->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->whereNull('preferred_payment_method')->count(),
                'documents_incomplete' => Costumer::query()->whereNotIn('customer_stage', ['legacy', 'completed', 'cancelled'])->whereDoesntHave('documentChecklists', fn (Builder $checklist) => $checklist->where('validation_status', 'complete'))->count(),
            ],
        ]);
    }

    public function salesCalendar(Request $request): Response
    {
        $this->allow($request, 'admin-sales.monitoring.view');
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : now()->format('Y-m');
        $start = now()->createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $type = (string) $request->query('type', '');
        $perumahanId = $request->integer('perumahan_id') ?: null;
        $events = collect();
        $stepTypes = ['appraisal' => 'OTS / Appraisal', 'contract_preparation' => 'Persiapan Akad', 'contract_signing' => 'Pelaksanaan Akad', 'bank_disbursement' => 'Rencana Pencairan', 'internal_handover' => 'Serah Terima Internal', 'customer_handover' => 'BAST / Serah Terima Customer'];

        if ($type === '' || array_key_exists($type, $stepTypes)) {
            SalesProcessStep::query()->with(['salesTransaction.customer:id,nama,kode_costumer', 'salesTransaction.housingProject:id,nama_perusahaan', 'salesTransaction.housingUnit:id,kode_nlok,nomor_rumah', 'assignee:id,name'])
                ->whereIn('code', $type ? [$type] : array_keys($stepTypes))->whereBetween('planned_date', [$start, $end])
                ->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('salesTransaction', fn (Builder $transaction) => $transaction->where('perumahan_id', $id)))
                ->get()->each(function (SalesProcessStep $step) use ($events, $stepTypes): void {
                    $transaction = $step->salesTransaction;
                    $events->push(['id' => 'step-'.$step->id, 'date' => $step->planned_date?->format('Y-m-d'), 'type' => $step->code, 'type_label' => $stepTypes[$step->code] ?? $step->label, 'title' => $step->label, 'customer' => $transaction?->customer?->nama, 'reference' => $transaction?->transaction_no, 'housing' => $transaction?->housingProject?->nama_perusahaan, 'unit' => trim(($transaction?->housingUnit?->kode_nlok ?? '').' / '.($transaction?->housingUnit?->nomor_rumah ?? ''), ' /'), 'pic' => $step->assignee?->name, 'status' => $step->status, 'overdue' => $step->planned_date?->isPast() && ! in_array($step->status, ['completed', 'skipped'], true), 'url' => route('admin.sales-process.workspace', $step, false)]);
                });
        }
        if ($type === '' || $type === 'expected_disbursement') {
            KprSubmission::query()->with(['spr.costumer:id,nama,kode_costumer', 'spr.detailRumah.perumahan:id,nama_perusahaan', 'spr.detailRumah:id,perumahan_id,kode_nlok,nomor_rumah', 'handler:id,name', 'financing'])->whereHas('financing', fn (Builder $query) => $query->whereBetween('expected_disbursement_date', [$start, $end]))
                ->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('spr.detailRumah', fn (Builder $unit) => $unit->where('perumahan_id', $id)))
                ->get()->each(function (KprSubmission $submission) use ($events): void {
                    $date = $submission->financing?->expected_disbursement_date;
                    $events->push(['id' => 'kpr-'.$submission->id, 'date' => $date?->format('Y-m-d'), 'type' => 'expected_disbursement', 'type_label' => 'Estimasi Pencairan KPR', 'title' => 'Estimasi pencairan '.$submission->kode_kpr, 'customer' => $submission->spr?->costumer?->nama, 'reference' => $submission->kode_kpr, 'housing' => $submission->spr?->detailRumah?->perumahan?->nama_perusahaan, 'unit' => trim(($submission->spr?->detailRumah?->kode_nlok ?? '').' / '.($submission->spr?->detailRumah?->nomor_rumah ?? '')), 'pic' => $submission->handler?->name, 'status' => $submission->status, 'overdue' => $date?->isPast() && ! in_array($submission->status, ['approved', 'disbursed', 'completed'], true), 'url' => route('admin.kpr.show', $submission->id, false)]);
                });
        }
        if ($type === '' || $type === 'internal_handover_record') {
            InternalHandover::query()->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah'])->whereBetween('tanggal', [$start, $end])->when($perumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))->get()->each(function (InternalHandover $handover) use ($events): void {
                $events->push(['id' => 'handover-'.$handover->id, 'date' => $handover->tanggal?->format('Y-m-d'), 'type' => 'internal_handover_record', 'type_label' => 'Serah Terima Internal', 'title' => $handover->kode_serah_terima, 'customer' => null, 'reference' => $handover->kode_serah_terima, 'housing' => $handover->perumahan?->nama_perusahaan, 'unit' => trim(($handover->detailRumah?->kode_nlok ?? '').' / '.($handover->detailRumah?->nomor_rumah ?? '')), 'pic' => null, 'status' => $handover->status, 'overdue' => false, 'url' => '/admin/pengawasan/serah-terima-internal']);
            });
        }

        return Inertia::render('Admin/AdminSales/SalesCalendar', [
            'title' => 'Kalender Proses Penjualan', 'month' => $month, 'events' => $events->sortBy([['date', 'asc'], ['title', 'asc']])->values(),
            'filters' => ['type' => $type, 'perumahan_id' => $perumahanId],
            'typeOptions' => collect($stepTypes)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->push(['value' => 'expected_disbursement', 'label' => 'Estimasi Pencairan KPR'])->push(['value' => 'internal_handover_record', 'label' => 'Realisasi Serah Terima Internal']),
            'perumahanOptions' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]),
        ]);
    }

    public function leads(Request $request): Response|RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.view');

        return redirect()->route('admin.marketing.leads.index', ['ownership_type' => 'company']);
    }

    public function createLead(Request $request): Response|RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.create');

        return redirect()->route('admin.marketing.leads.create')->with('warning', 'Lead Perusahaan sekarang memakai master Lead terpisah dari Customer.');
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.create');

        return redirect()->route('admin.marketing.leads.create')->with('warning', 'Gunakan form Lead baru. Customer hanya dibuat setelah Lead Qualified.');
    }

    public function showLead(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.view');
        abort_unless($lead->ownership_type === 'company', 404);

        return redirect()->route('admin.marketing.leads.show', $lead);
    }

    public function assignLead(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.assign');
        abort_unless($lead->ownership_type === 'company', 422);
        abort_unless($lead->verification_status === 'verified', 422, 'Lead harus diverifikasi sebelum dibagikan.');
        $data = $request->validate(['marketing_id' => ['required', 'exists:users,id'], 'response_hours' => ['required', 'integer', 'min:1', 'max:72'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $marketing = User::query()->whereKey($data['marketing_id'])->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['marketing', 'area_marketing']))->firstOrFail();
        DB::transaction(function () use ($request, $lead, $marketing, $data): void {
            MarketingLeadAssignment::query()->where('marketing_lead_id', $lead->id)->where('status', 'offered')->update(['status' => 'transferred', 'responded_at' => now(), 'response_note' => 'Digantikan assignment baru.']);
            $due = now()->addHours((int) $data['response_hours']);
            $assignment = MarketingLeadAssignment::query()->create(['marketing_lead_id' => $lead->id, 'from_marketing_id' => $lead->marketing_id, 'to_marketing_id' => $marketing->id, 'reason' => $data['reason'], 'status' => 'offered', 'assigned_by' => $request->user()?->id, 'assigned_at' => now(), 'response_due_at' => $due]);
            $old = $lead->assignment_status;
            $lead->forceFill(['marketing_id' => $marketing->id, 'assigned_at' => now(), 'assignment_status' => 'offered', 'first_response_due_at' => $due, 'updated_by' => $request->user()?->id])->save();
            $this->logger->record($request, $lead, 'company_lead_assigned', $old, 'offered', $data['reason'], [], ['assignment_id' => $assignment->id, 'marketing_id' => $marketing->id, 'response_due_at' => $due]);
            SalesWorkItem::query()->create(['work_no' => $this->nextWorkNo(), 'category' => 'lead', 'title' => 'Pantau respons '.$lead->lead_no, 'description' => 'Pastikan Marketing menerima dan menghubungi Lead sebelum SLA.', 'subject_type' => $lead->getMorphClass(), 'subject_id' => $lead->id, 'marketing_lead_id' => $lead->id, 'assigned_to' => $lead->admin_sales_id ?? $request->user()?->id, 'assigned_by' => $request->user()?->id, 'priority' => in_array($lead->priority, ['urgent', 'high'], true) ? $lead->priority : 'high', 'status' => 'waiting', 'due_at' => $due, 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
        });

        return back()->with('success', 'Lead ditawarkan kepada '.$marketing->name.'.');
    }

    public function respondAssignment(Request $request, MarketingLeadAssignment $assignment): RedirectResponse
    {
        $this->allow($request, 'marketing.lead-assignment.respond');
        abort_unless($assignment->to_marketing_id === $request->user()?->id, 403);
        abort_unless($assignment->status === 'offered', 422, 'Assignment ini sudah diproses.');
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'rejected'])], 'note' => [Rule::requiredIf(fn () => $request->input('decision') === 'rejected'), 'nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $assignment, $data): void {
            $assignment->forceFill(['status' => $data['decision'], 'responded_at' => now(), 'response_note' => $data['note'] ?? null])->save();
            $lead = MarketingLead::query()->findOrFail($assignment->marketing_lead_id);
            $old = $lead->assignment_status;
            $updates = ['assignment_status' => $data['decision'], 'updated_by' => $request->user()?->id];
            if ($data['decision'] === 'rejected') {
                $updates += ['marketing_id' => null, 'assigned_at' => null, 'first_response_due_at' => null];
            }
            $lead->forceFill($updates)->save();
            $this->logger->record($request, $lead, 'lead_assignment_'.$data['decision'], $old, $data['decision'], $data['note'] ?? null, [], ['assignment_id' => $assignment->id]);
            if ($data['decision'] === 'rejected') {
                SalesWorkItem::query()->create(['work_no' => $this->nextWorkNo(), 'category' => 'lead', 'title' => 'Distribusikan ulang '.$lead->lead_no, 'description' => $data['note'], 'subject_type' => $lead->getMorphClass(), 'subject_id' => $lead->id, 'marketing_lead_id' => $lead->id, 'assigned_to' => $lead->admin_sales_id, 'assigned_by' => $request->user()?->id, 'priority' => 'high', 'status' => 'open', 'due_at' => now()->addHour(), 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
            }
        });

        return back()->with('success', $data['decision'] === 'accepted' ? 'Lead diterima. Segera lakukan follow-up pertama.' : 'Lead dikembalikan kepada Admin Sales.');
    }

    public function assignments(Request $request): Response
    {
        $this->allow($request, 'marketing.lead-assignment.view');
        $rows = MarketingLeadAssignment::query()->where('to_marketing_id', $request->user()?->id)->with(['lead:id,lead_no,name,phone,priority,first_response_due_at', 'assigner:id,name'])->latest('assigned_at')->paginate(25);

        return Inertia::render('Admin/Marketing/LeadAssignments/Index', ['title' => 'Penugasan Lead Saya', 'rows' => $rows]);
    }

    public function index(Request $request): Response
    {
        $this->allow($request, 'admin-sales.work-item.view');
        $status = (string) $request->query('status', '');
        $query = $this->scopeWork(SalesWorkItem::query()->with(['customer:id,nama,kode_costumer', 'lead:id,name,lead_no', 'assignee:id,name']), $request)
            ->when($status === 'overdue', fn (Builder $q) => $q->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now()))
            ->when($status && $status !== 'overdue', fn (Builder $q) => $q->where('status', $status));

        return Inertia::render('Admin/AdminSales/WorkItems/Index', ['title' => 'Tugas Admin Sales', 'rows' => $query->latest()->paginate(25)->through(fn ($x) => $this->workRow($x)), 'filters' => ['status' => $status], 'canCreate' => $request->user()?->can('admin-sales.work-item.create')]);
    }

    public function create(Request $request): Response
    {
        $this->allow($request, 'admin-sales.work-item.create');

        return Inertia::render('Admin/AdminSales/WorkItems/Form', ['title' => 'Tambah Tugas Admin Sales', 'options' => $this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request, 'admin-sales.work-item.create');
        $data = $this->workData($request);
        $item = DB::transaction(function () use ($request, $data) {
            $item = SalesWorkItem::query()->create($data + ['work_no' => 'AS-'.now()->format('YmdHis').'-'.random_int(100, 999), 'assigned_by' => $request->user()?->id, 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);
            $this->logger->record($request, $item, 'work_item_created', null, $item->status, $item->description);

            return $item;
        });

        return redirect()->route('admin.admin-sales.work-items.show', $item)->with('success', 'Tugas Admin Sales berhasil dibuat.');
    }

    public function show(Request $request, SalesWorkItem $workItem): Response
    {
        $this->allow($request, 'admin-sales.work-item.view');
        $this->assertScope($request, $workItem);

        return Inertia::render('Admin/AdminSales/WorkItems/Show', ['title' => 'Detail Tugas Admin Sales', 'item' => $this->workRow($workItem->load(['customer', 'assignee'])), 'logs' => SalesActivityLog::query()->where('subject_type', $workItem->getMorphClass())->where('subject_id', $workItem->id)->with('user:id,name')->latest()->get()]);
    }

    public function updateStatus(Request $request, SalesWorkItem $workItem): RedirectResponse
    {
        $this->allow($request, 'admin-sales.work-item.update');
        $this->assertScope($request, $workItem);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'waiting', 'completed', 'cancelled'])], 'resolution_note' => ['nullable', 'string', 'max:2000']]);
        $old = $workItem->status;
        $workItem->forceFill($data + ['started_at' => $data['status'] === 'in_progress' ? ($workItem->started_at ?? now()) : $workItem->started_at, 'completed_at' => $data['status'] === 'completed' ? now() : null, 'updated_by' => $request->user()?->id])->save();
        $this->logger->record($request, $workItem, 'work_item_status_changed', $old, $workItem->status, $data['resolution_note'] ?? null);

        return back()->with('success', 'Status tugas diperbarui.');
    }

    public function verifyLead(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.verify');
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'duplicate', 'spam', 'needs_revision'])], 'note' => ['required', 'string', 'min:5', 'max:2000']]);
        abort_unless($lead->qualification_status === 'submitted' || $data['status'] !== 'verified', 422, 'Lead harus diajukan Marketing sebelum dapat diverifikasi.');
        $old = $lead->verification_status;
        $lead->forceFill(['verification_status' => $data['status'], 'verification_note' => $data['note'], 'qualification_status' => $data['status'] === 'verified' ? 'qualified' : ($data['status'] === 'needs_revision' ? 'in_review' : 'disqualified'), 'stage' => $data['status'] === 'needs_revision' ? 'nurturing' : $lead->stage, 'verified_by' => $request->user()?->id, 'verified_at' => now(), 'admin_sales_id' => $lead->admin_sales_id ?? $request->user()?->id, 'updated_by' => $request->user()?->id])->save();
        SalesWorkItem::query()->where('marketing_lead_id', $lead->id)->where('category', 'lead')->where('title', 'like', 'Verifikasi lead%')->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'completed', 'completed_at' => now(), 'resolution_note' => $data['note'], 'updated_by' => $request->user()?->id]);
        $this->logger->record($request, $lead, 'lead_verified', $old, $data['status'], $data['note']);

        return back()->with('success', 'Pemeriksaan lead tersimpan dalam riwayat.');
    }

    public function verificationForm(Request $request, MarketingLead $lead): Response
    {
        $this->allow($request, 'admin-sales.lead.verify');
        $lead->load(['source:id,nama_sumber', 'marketing:id,name', 'perumahan:id,nama_perusahaan', 'unit:id,kode_nlok,nomor_rumah,tipe_rumah', 'campaign:id,nama_campaign']);

        return Inertia::render('Admin/AdminSales/Review', [
            'title' => 'Verifikasi Lead '.$lead->lead_no, 'kind' => 'lead',
            'backUrl' => route('admin.admin-sales.monitoring', ['queue' => 'lead-unverified'], false),
            'submitUrl' => route('admin.admin-sales.lead.verify', $lead, false),
            'statusOptions' => [['value' => 'verified', 'label' => 'Terverifikasi / Qualified'], ['value' => 'needs_revision', 'label' => 'Perlu Perbaikan Marketing'], ['value' => 'duplicate', 'label' => 'Duplikat'], ['value' => 'spam', 'label' => 'Spam / Tidak Valid']],
            'currentReview' => ['status' => $lead->verification_status, 'note' => $lead->verification_note, 'reviewed_at' => $lead->verified_at?->format('d/m/Y H:i')],
            'sections' => [
                ['title' => 'Identitas Lead', 'items' => [['label' => 'Nomor Lead', 'value' => $lead->lead_no], ['label' => 'Nama', 'value' => $lead->name], ['label' => 'Telepon', 'value' => $lead->phone], ['label' => 'Email', 'value' => $lead->email], ['label' => 'NIK', 'value' => $lead->identity_no], ['label' => 'Sumber', 'value' => $lead->source?->nama_sumber], ['label' => 'Kanal', 'value' => $lead->lead_source_channel], ['label' => 'PIC Marketing', 'value' => $lead->marketing?->name]]],
                ['title' => 'Kualifikasi dan Minat', 'items' => [['label' => 'Tahap', 'value' => $lead->stage], ['label' => 'Status Kualifikasi', 'value' => $lead->qualification_status], ['label' => 'Skor', 'value' => $lead->qualification_score], ['label' => 'Tingkat Minat', 'value' => $lead->interest_level], ['label' => 'Perumahan', 'value' => $lead->perumahan?->nama_perusahaan], ['label' => 'Unit', 'value' => $lead->unit ? trim($lead->unit->kode_nlok.' / '.$lead->unit->nomor_rumah.' / '.$lead->unit->tipe_rumah, ' /') : null], ['label' => 'Campaign', 'value' => $lead->campaign?->nama_campaign], ['label' => 'Rencana Pembayaran', 'value' => $lead->preferred_payment_method], ['label' => 'Catatan Kualifikasi', 'value' => $lead->qualification_note]]],
            ],
            'evidence' => [], 'logs' => $this->reviewLogs($lead),
        ]);
    }

    public function mergeLead(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.verify');
        abort_if($lead->converted_costumer_id || $lead->merged_into_lead_id, 422, 'Lead sumber sudah dikonversi atau digabungkan.');
        $data = $request->validate(['target_lead_id' => ['required', Rule::notIn([$lead->id]), 'exists:marketing_leads,id'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $target = MarketingLead::query()->whereKey($data['target_lead_id'])->whereNull('merged_into_lead_id')->firstOrFail();
        abort_if($target->converted_costumer_id, 422, 'Gabungkan ke Lead aktif, bukan Lead yang sudah menjadi Customer.');

        $oldStage = $lead->stage;
        DB::transaction(function () use ($request, $lead, $target, $data, $oldStage): void {
            CostumerFollowUp::query()->where('marketing_lead_id', $lead->id)->update(['marketing_lead_id' => $target->id]);
            MarketingVisit::query()->where('marketing_lead_id', $lead->id)->update(['marketing_lead_id' => $target->id]);
            MarketingLeadAssignment::query()->where('marketing_lead_id', $lead->id)->update(['marketing_lead_id' => $target->id]);
            SalesWorkItem::query()->where('marketing_lead_id', $lead->id)->update(['marketing_lead_id' => $target->id, 'subject_id' => $target->id]);
            $lead->forceFill(['stage' => 'lost', 'qualification_status' => 'disqualified', 'verification_status' => 'duplicate', 'verification_note' => $data['reason'], 'merged_into_lead_id' => $target->id, 'merged_at' => now(), 'merged_by' => $request->user()?->id, 'do_not_contact' => true, 'updated_by' => $request->user()?->id])->save();
            $this->logger->record($request, $lead, 'lead_merged', $oldStage, 'lost', $data['reason'], [], ['target_lead_id' => $target->id]);
            $this->logger->record($request, $target, 'duplicate_lead_absorbed', $target->stage, $target->stage, $data['reason'], [], ['source_lead_id' => $lead->id]);
        });

        return redirect()->route('admin.marketing.leads.show', $target)->with('success', 'Lead duplikat digabungkan; seluruh riwayat operasional dipindahkan ke Lead utama.');
    }

    public function recycleLead(Request $request, MarketingLead $lead): RedirectResponse
    {
        $this->allow($request, 'admin-sales.lead.assign');
        abort_unless(in_array($lead->stage, ['lost', 'postponed'], true), 422, 'Hanya Lead lost atau ditunda yang dapat diaktifkan kembali.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000'], 'next_action_at' => ['required', 'date', 'after:now']]);
        $old = $lead->stage;
        abort_if($lead->do_not_contact, 422, 'Lead menolak komunikasi. Consent harus diperbarui terlebih dahulu sebelum recycle.');
        $lead->forceFill(['stage' => 'nurturing', 'qualification_status' => 'in_review', 'verification_status' => 'pending', 'verification_note' => null, 'lost_reason' => null, 'recycle_at' => null, 'recycle_count' => $lead->recycle_count + 1, 'next_action_at' => $data['next_action_at'], 'updated_by' => $request->user()?->id])->save();
        $this->logger->record($request, $lead, 'lead_recycled', $old, 'nurturing', $data['reason'], [], ['next_action_at' => $data['next_action_at'], 'recycle_count' => $lead->recycle_count]);

        return back()->with('success', 'Lead diaktifkan kembali ke tahap nurturing.');
    }

    public function review(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['follow-up', 'visit'], true), 404);
        $this->allow($request, $type === 'follow-up' ? 'admin-sales.follow-up.review' : 'admin-sales.visit.review');
        $data = $request->validate(['status' => ['required', Rule::in(['complete', 'needs_revision', 'invalid', 'escalated'])], 'note' => ['required', 'string', 'min:5', 'max:2000']]);
        /** @var Model $row */ $row = ($type === 'follow-up' ? CostumerFollowUp::query() : MarketingVisit::query())->findOrFail($id);
        $old = $row->admin_review_status;
        $row->forceFill(['admin_review_status' => $data['status'], 'admin_review_note' => $data['note'], 'admin_reviewed_by' => $request->user()?->id, 'admin_reviewed_at' => now()])->save();
        $this->logger->record($request, $row, $type.'_reviewed', $old, $data['status'], $data['note']);

        return back()->with('success', 'Catatan pemeriksaan disimpan tanpa mengubah laporan Marketing.');
    }

    public function reviewForm(Request $request, string $type, int $id): Response
    {
        abort_unless(in_array($type, ['follow-up', 'visit'], true), 404);
        $this->allow($request, $type === 'follow-up' ? 'admin-sales.follow-up.review' : 'admin-sales.visit.review');
        /** @var CostumerFollowUp|MarketingVisit $row */
        $row = ($type === 'follow-up'
            ? CostumerFollowUp::query()->with(['lead:id,lead_no,name,phone', 'user:id,name'])
            : MarketingVisit::query()->with(['lead:id,lead_no,name,phone', 'costumer:id,kode_costumer,nama,telepon', 'marketing:id,name', 'perumahan:id,nama_perusahaan']))->findOrFail($id);
        $isFollowUp = $type === 'follow-up';
        $subject = $isFollowUp ? $row->lead : ($row->lead ?? $row->costumer);
        $sections = $isFollowUp ? [
            ['title' => 'Data Follow-up Marketing', 'items' => [['label' => 'Lead', 'value' => $row->lead?->lead_no.' - '.$row->lead?->name], ['label' => 'Marketing', 'value' => $row->user?->name], ['label' => 'Waktu', 'value' => ($row->followed_up_at ?? $row->tanggal_follow_up)?->format('d/m/Y H:i')], ['label' => 'Media', 'value' => $row->metode_follow_up], ['label' => 'Hasil', 'value' => $row->result_code], ['label' => 'Tingkat Minat', 'value' => $row->interest_level], ['label' => 'Status', 'value' => $row->status]]],
            ['title' => 'Isi Laporan', 'items' => [['label' => 'Catatan', 'value' => $row->catatan], ['label' => 'Hambatan', 'value' => $row->obstacle], ['label' => 'Tindak Lanjut', 'value' => $row->next_action], ['label' => 'Jadwal Berikutnya', 'value' => $row->rencana_follow_up_at?->format('d/m/Y')]]],
        ] : [
            ['title' => 'Data Aktivitas Lapangan', 'items' => [['label' => 'Prospek / Customer', 'value' => $subject ? (($subject->lead_no ?? $subject->kode_costumer).' - '.($subject->name ?? $subject->nama)) : ($row->contact_name ?: $row->organization_name)], ['label' => 'Marketing', 'value' => $row->marketing?->name], ['label' => 'Jenis', 'value' => $row->visit_type], ['label' => 'Status', 'value' => $row->status], ['label' => 'Rencana', 'value' => $row->planned_at?->format('d/m/Y H:i')], ['label' => 'Check-in', 'value' => $row->started_at?->format('d/m/Y H:i')], ['label' => 'Check-out', 'value' => $row->finished_at?->format('d/m/Y H:i')], ['label' => 'Lokasi', 'value' => $row->location], ['label' => 'Akurasi GPS', 'value' => $row->location_accuracy_m ? $row->location_accuracy_m.' meter' : null]]],
            ['title' => 'Isi Laporan', 'items' => [['label' => 'Tujuan', 'value' => $row->objective], ['label' => 'Hasil', 'value' => $row->result], ['label' => 'Respons Prospek', 'value' => $row->customer_response], ['label' => 'Keberatan', 'value' => $row->objections], ['label' => 'Tingkat Minat', 'value' => $row->interest_level], ['label' => 'Tindak Lanjut', 'value' => $row->next_action], ['label' => 'Jadwal Berikutnya', 'value' => $row->next_action_at?->format('d/m/Y H:i')]]],
        ];

        return Inertia::render('Admin/AdminSales/Review', [
            'title' => ($isFollowUp ? 'Pemeriksaan Follow-up' : 'Pemeriksaan Kunjungan').' #'.$row->id, 'kind' => $type,
            'backUrl' => route('admin.admin-sales.monitoring', ['queue' => $isFollowUp ? 'followup-review' : 'visit-review'], false),
            'submitUrl' => route('admin.admin-sales.review', [$type, $row->id], false),
            'statusOptions' => [['value' => 'complete', 'label' => 'Lengkap'], ['value' => 'needs_revision', 'label' => 'Perlu Perbaikan'], ['value' => 'invalid', 'label' => 'Tidak Sesuai'], ['value' => 'escalated', 'label' => 'Diteruskan ke Manager']],
            'currentReview' => ['status' => $row->admin_review_status, 'note' => $row->admin_review_note, 'reviewed_at' => $row->admin_reviewed_at?->format('d/m/Y H:i')],
            'sections' => $sections,
            'evidence' => $isFollowUp ? array_values(array_filter([['label' => 'Bukti Follow-up', 'url' => $row->attachment_path ? route('admin.marketing.jejak-follow-up.evidence', $row->id, false) : null]], fn (array $item) => $item['url'])) : array_values(array_filter([['label' => 'Foto Check-in', 'url' => $row->check_in_photo_path ? route('admin.marketing.visit-evidence', [$row->id, 'check-in'], false) : null], ['label' => 'Foto Check-out', 'url' => $row->check_out_photo_path ? route('admin.marketing.visit-evidence', [$row->id, 'check-out'], false) : null]], fn (array $item) => $item['url'])),
            'mapUrl' => ! $isFollowUp && $row->latitude !== null && $row->longitude !== null ? 'https://www.openstreetmap.org/?mlat='.$row->latitude.'&mlon='.$row->longitude.'#map=18/'.$row->latitude.'/'.$row->longitude : null,
            'logs' => $this->reviewLogs($row),
        ]);
    }

    private function reviewLogs(Model $row): array
    {
        return SalesActivityLog::query()->where('subject_type', $row->getMorphClass())->where('subject_id', $row->getKey())->with('user:id,name')->latest()->limit(25)->get()->map(fn (SalesActivityLog $log) => ['id' => $log->id, 'event' => $log->event, 'status' => $log->new_status, 'note' => $log->reason, 'user' => $log->user?->name, 'at' => $log->created_at?->format('d/m/Y H:i')])->all();
    }

    private function customerReadinessRow(Costumer $customer): array
    {
        $requirements = [
            'Nama lengkap' => filled($customer->nama), 'Nomor telepon' => filled($customer->telepon), 'NIK' => filled($customer->no_identitas),
            'Alamat' => filled($customer->alamat), 'Tempat dan tanggal lahir' => filled($customer->tempat_lahir) && filled($customer->tanggal_lahir),
            'Status perkawinan' => filled($customer->status_perkawinan), 'Pekerjaan' => filled($customer->pekerjaan), 'Pendapatan' => (float) $customer->penghasilan > 0,
            'Marketing penanggung jawab' => filled($customer->assigned_marketing_id), 'Perumahan atau unit diminati' => $customer->unitInterests->isNotEmpty(),
            'Metode pembayaran' => filled($customer->preferred_payment_method), 'Checklist dokumen lengkap' => $customer->documentChecklists->contains('validation_status', 'complete'),
        ];
        if (in_array($customer->status_perkawinan, ['menikah', 'kawin'], true)) {
            $requirements['Nama pasangan'] = filled($customer->nama_lengkap_pasangan);
            $requirements['NIK pasangan'] = filled($customer->no_identitas_pasangan);
        }
        $missing = collect($requirements)->filter(fn (bool $complete) => ! $complete)->keys()->values();
        $requiredDocuments = $customer->documentChecklists->flatMap(fn (CustomerDocumentChecklist $checklist) => collect($checklist->items ?? [])->filter(fn (array $item) => (bool) ($item['required'] ?? false)));
        $documentProblems = $requiredDocuments->filter(fn (array $item) => ! in_array($item['status'] ?? 'missing', ['valid'], true))->map(fn (array $item) => ['name' => $item['name'] ?? 'Dokumen', 'status' => $item['status'] ?? 'missing', 'note' => $item['note'] ?? null])->values();
        $completion = count($requirements) > 0 ? (int) round((collect($requirements)->filter()->count() / count($requirements)) * 100) : 0;

        return [
            'id' => $customer->id, 'code' => $customer->kode_costumer, 'name' => $customer->nama, 'phone' => $customer->telepon,
            'stage' => $customer->customer_stage, 'marketing' => $customer->assignedMarketing?->name, 'admin_sales' => $customer->adminSales?->name,
            'housing' => $customer->perumahan?->nama_perusahaan, 'payment_method' => $customer->preferred_payment_method,
            'unit_count' => $customer->unitInterests->count(), 'checklist_count' => $customer->documentChecklists->count(),
            'completion' => $completion, 'missing' => $missing->all(), 'document_problems' => $documentProblems->all(),
            'last_activity_at' => $customer->last_activity_at?->format('d/m/Y H:i'),
            'customer_url' => route('admin.marketing.calon-konsumen.show', $customer->id, false),
            'checklist_url' => route('admin.marketing.crm.index', ['resource' => 'document-checklists', 'search' => $customer->kode_costumer], false),
        ];
    }

    private function workData(Request $r): array
    {
        return $r->validate(['category' => ['required', Rule::in(['lead', 'customer', 'visit', 'document', 'reservation', 'booking_fee', 'spr', 'kpr', 'payment', 'closing', 'other'])], 'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:3000'], 'marketing_lead_id' => ['nullable', 'exists:marketing_leads,id'], 'costumer_id' => ['nullable', 'exists:costumers,id'], 'assigned_to' => ['required', 'exists:users,id'], 'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])], 'status' => ['required', Rule::in(['open', 'in_progress', 'waiting'])], 'due_at' => ['nullable', 'date']]);
    }

    private function options(): array
    {
        return ['leads' => MarketingLead::query()->whereNotIn('stage', ['converted', 'lost'])->orderBy('name')->limit(500)->get(['id', 'name', 'lead_no'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->lead_no.' - '.$x->name]), 'customers' => Costumer::query()->orderBy('nama')->limit(500)->get(['id', 'nama', 'kode_costumer'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->kode_costumer.' - '.$x->nama]), 'adminSales' => User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin_sales'))->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->name])];
    }

    private function leadOptions(): array
    {
        return ['sources' => MarketingLeadSource::query()->orderBy('nama_sumber')->get(['id', 'nama_sumber'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_sumber]), 'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_perusahaan]), 'marketings' => User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['marketing', 'area_marketing']))->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->name])];
    }

    private function nextWorkNo(): string
    {
        return 'AS-'.now()->format('YmdHisv').'-'.random_int(10, 99);
    }

    private function workRow(SalesWorkItem $x): array
    {
        return ['id' => $x->id, 'work_no' => $x->work_no, 'category' => $x->category, 'title' => $x->title, 'description' => $x->description, 'customer' => $x->lead?->name ?? $x->customer?->nama, 'reference' => $x->lead?->lead_no ?? $x->customer?->kode_costumer, 'assignee' => $x->assignee?->name, 'priority' => $x->priority, 'status' => $x->status, 'due_at' => $x->due_at?->format('d/m/Y H:i'), 'overdue' => $x->due_at?->isPast() && ! in_array($x->status, ['completed', 'cancelled'], true), 'resolution_note' => $x->resolution_note];
    }

    private function card(string $label, int $count, string $queue): array
    {
        return compact('label', 'count') + ['href' => route('admin.admin-sales.monitoring', ['queue' => $queue], false)];
    }

    private function scopeWork(Builder $q, Request $r): Builder
    {
        return $r->user()?->hasAnyRole(['super_admin', 'owner', 'manager']) ? $q : $q->where('assigned_to', $r->user()?->id);
    }

    private function assertScope(Request $r, SalesWorkItem $x): void
    {
        abort_unless($r->user()?->hasAnyRole(['super_admin', 'owner', 'manager']) || $x->assigned_to === $r->user()?->id, 403);
    }

    private function allow(Request $r, string $permission): void
    {
        abort_unless($r->user()?->hasRole('super_admin') || $r->user()?->can($permission), 403);
    }
}
