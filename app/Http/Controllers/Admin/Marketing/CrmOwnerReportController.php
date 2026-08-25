<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerDocumentChecklist;
use App\Models\MarketingActionPlan;
use App\Models\MarketingLeadActivity;
use App\Models\MarketingLeadSource;
use App\Models\MarketingTarget;
use App\Models\MarketingVisit;
use App\Models\Perumahan;
use App\Models\Spr;
use App\Models\User;
use App\Services\Marketing\MarketingLeadStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmOwnerReportController extends Controller
{
    use ScopesActivePerumahan;

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing.owner-report.view'), 403);
        $from = Carbon::parse($request->query('date_from') ?: now()->startOfWeek()->toDateString())->startOfDay();
        $to = Carbon::parse($request->query('date_to') ?: now()->endOfWeek()->toDateString())->endOfDay();
        $marketingId = $request->integer('marketing_id');
        $perumahanId = $this->shouldScopeToActivePerumahan($request) ? $this->activePerumahanId($request) : $request->integer('perumahan_id');
        $status = (string) $request->query('status', '');
        $sourceId = $request->integer('source_id');

        $customers = Costumer::query()
            ->with(['assignedMarketing:id,name', 'leadSource:id,nama_sumber', 'perumahan:id,nama_perusahaan'])
            ->when($marketingId, fn (Builder $query) => $query->where('assigned_marketing_id', $marketingId))
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($status, fn (Builder $query) => $query->where('status_lead', $status))
            ->when($sourceId, fn (Builder $query) => $query->where('marketing_lead_source_id', $sourceId))
            ->get();

        $periodCustomers = $customers->filter(fn (Costumer $customer) => $customer->created_at?->between($from, $to));
        $customerIds = $customers->pluck('id');
        $statusOptions = MarketingLeadStatusService::statusOptions();
        $pipeline = collect($statusOptions)->map(fn (array $option) => [
            ...$option,
            'total' => $customers->where('status_lead', $option['value'])->count(),
            'period_total' => $periodCustomers->where('status_lead', $option['value'])->count(),
        ])->values();

        $followUpMetrics = CostumerFollowUp::query()
            ->whereBetween('followed_up_at', [$from, $to])
            ->when($perumahanId, fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $query->where('perumahan_id', $perumahanId)))
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')->get()->keyBy('user_id');
        $visitMetrics = MarketingVisit::query()
            ->whereBetween('planned_at', [$from, $to])
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->selectRaw("marketing_id, COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified")
            ->groupBy('marketing_id')->get()->keyBy('marketing_id');
        $actionMetrics = MarketingActionPlan::query()
            ->whereBetween('due_at', [$from, $to])
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->selectRaw("marketing_id, COUNT(*) as total, SUM(CASE WHEN status NOT IN ('completed', 'cancelled') AND due_at < NOW() THEN 1 ELSE 0 END) as overdue")
            ->groupBy('marketing_id')->get()->keyBy('marketing_id');
        $sprMetrics = Spr::query()
            ->whereBetween('tanggal_spr', [$from, $to])
            ->when($perumahanId, fn (Builder $query) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $perumahanId)))
            ->selectRaw("created_by as marketing_id, COUNT(*) as total, SUM(CASE WHEN status = '".Spr::STATUS_DISETUJUI."' THEN 1 ELSE 0 END) as closing")
            ->groupBy('created_by')->get()->keyBy('marketing_id');
        $targets = MarketingTarget::query()
            ->where('tahun', $from->year)->where('bulan', $from->month)
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->get()->keyBy('user_id');

        $performance = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing', 'admin_sales']))
            ->when($marketingId, fn (Builder $query) => $query->whereKey($marketingId))
            ->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($id)))
            ->get(['id', 'name'])
            ->map(function (User $user) use ($periodCustomers, $followUpMetrics, $visitMetrics, $actionMetrics, $sprMetrics, $targets): array {
                $periodLeads = $periodCustomers->where('assigned_marketing_id', $user->id);
                $responded = $periodLeads->whereNotNull('first_contacted_at');
                $slaCompliant = $responded->filter(fn (Costumer $lead) => $lead->first_contacted_at->lte(($lead->lead_received_at ?? $lead->created_at)->copy()->addHours(2)))->count();
                $visit = $visitMetrics->get($user->id);
                $action = $actionMetrics->get($user->id);
                $spr = $sprMetrics->get($user->id);
                $target = $targets->get($user->id);
                $leadCount = $periodLeads->count();
                $followUpCount = (int) ($followUpMetrics->get($user->id)?->total ?? 0);
                $visitCompleted = (int) ($visit?->completed ?? 0);
                $closing = (int) ($spr?->closing ?? 0);
                $targetLead = (int) ($target?->target_lead ?? 0);
                $targetVisit = (int) ($target?->target_visit ?? 0);
                $targetClosing = (int) ($target?->target_closing ?? 0);

                return [
                    'id' => $user->id, 'name' => $user->name,
                    'new_customers' => $leadCount, 'contacted' => $responded->count(),
                    'sla_percent' => $responded->count() ? round($slaCompliant / $responded->count() * 100, 1) : 0,
                    'follow_ups' => $followUpCount, 'visits_planned' => (int) ($visit?->total ?? 0),
                    'visits_completed' => $visitCompleted, 'visits_verified' => (int) ($visit?->verified ?? 0),
                    'actions_due' => (int) ($action?->total ?? 0), 'actions_overdue' => (int) ($action?->overdue ?? 0),
                    'spr' => (int) ($spr?->total ?? 0), 'closing' => $closing,
                    'target_lead' => $targetLead, 'target_visit' => $targetVisit, 'target_closing' => $targetClosing,
                    'target_achievement' => round(collect([$targetLead ? min(100, $leadCount / $targetLead * 100) : null, $targetVisit ? min(100, $visitCompleted / $targetVisit * 100) : null, $targetClosing ? min(100, $closing / $targetClosing * 100) : null])->filter(fn ($value) => $value !== null)->avg() ?? 0, 1),
                    'performance_score' => max(0, round(($leadCount * 2) + ($followUpCount * 2) + ($visitCompleted * 5) + ($closing * 20) + ($slaCompliant * 2) - ((int) ($action?->overdue ?? 0) * 3), 1)),
                ];
            })->sortByDesc('performance_score')->values();

        $now = now();
        $customerRows = $customers->map(function (Costumer $customer) use ($now): array {
            $activityAt = $customer->last_activity_at ?? $customer->created_at;

            return [
                'id' => $customer->id, 'code' => $customer->kode_costumer, 'name' => $customer->nama,
                'marketing' => $customer->assignedMarketing?->name ?? 'Belum Ditugaskan',
                'housing' => $customer->perumahan?->nama_perusahaan ?? '-', 'source' => $customer->leadSource?->nama_sumber ?? '-',
                'status' => $customer->status_lead, 'customer_age' => $customer->tanggal_lahir?->age,
                'lead_age_days' => $customer->created_at?->diffInDays($now), 'inactive_days' => $activityAt?->diffInDays($now),
                'next_action_at' => $customer->next_action_at?->format('d/m/Y H:i'),
            ];
        })->sortByDesc('inactive_days')->values();

        $incompleteDocuments = CustomerDocumentChecklist::query()->whereIn('costumer_id', $customerIds)->where('validation_status', '!=', 'complete')->count();
        $overdueActions = MarketingActionPlan::query()->whereIn('costumer_id', $customerIds)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count();
        $staleLeads = $customerRows->where('inactive_days', '>=', 7)->count();

        return Inertia::render('Admin/Marketing/CrmOwnerReport/Index', [
            'title' => 'Laporan CRM Owner',
            'filters' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'marketing_id' => $marketingId ?: '', 'perumahan_id' => $perumahanId ?: '', 'status' => $status, 'source_id' => $sourceId ?: ''],
            'options' => [
                'marketings' => User::query()->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing', 'admin_sales']))->when($perumahanId, fn (Builder $query, int $id) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($id)))->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name]),
                'perumahans' => Perumahan::query()->finalized()->when($perumahanId, fn (Builder $query, int $id) => $query->whereKey($id))->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan]),
                'statuses' => $statusOptions,
                'sources' => MarketingLeadSource::query()->orderBy('nama_sumber')->get(['id', 'nama_sumber'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_sumber]),
            ],
            'summary' => ['total_customers' => $customers->count(), 'new_customers' => $periodCustomers->count(), 'unassigned' => $customers->whereNull('assigned_marketing_id')->count(), 'stale_leads' => $staleLeads, 'overdue_actions' => $overdueActions, 'incomplete_documents' => $incompleteDocuments],
            'pipeline' => $pipeline,
            'performance' => $performance,
            'customers' => $customerRows,
            'charts' => [
                'daily_activity' => MarketingLeadActivity::query()->whereBetween('activity_at', [$from, $to])->when($marketingId, fn (Builder $q) => $q->where('user_id', $marketingId))->selectRaw('DATE(activity_at) as label, COUNT(*) as total')->groupByRaw('DATE(activity_at)')->orderBy('label')->get(),
                'lead_sources' => $customers->groupBy(fn ($row) => $row->leadSource?->nama_sumber ?: 'Tanpa sumber')->map(fn ($rows, $label) => ['label' => $label, 'total' => $rows->count()])->sortByDesc('total')->values(),
                'cancellations' => $customers->filter(fn ($row) => in_array($row->status_lead, ['batal', 'tidak_berminat', 'tidak_aktif'], true))->groupBy(fn ($row) => $row->cancellation_reason ?: $row->lost_reason ?: 'Tanpa alasan')->map(fn ($rows, $label) => ['label' => $label, 'total' => $rows->count()])->sortByDesc('total')->values(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing.owner-report.view'), 403);
        $from = Carbon::parse($request->query('date_from') ?: now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($request->query('date_to') ?: now()->endOfMonth()->toDateString())->endOfDay();
        $marketingId = $request->integer('marketing_id');
        $perumahanId = $this->shouldScopeToActivePerumahan($request) ? $this->activePerumahanId($request) : $request->integer('perumahan_id');
        $status = trim((string) $request->query('status', ''));
        $rows = Costumer::query()->with(['assignedMarketing:id,name', 'perumahan:id,nama_perusahaan', 'leadSource:id,nama_sumber'])
            ->when($marketingId, fn (Builder $query) => $query->where('assigned_marketing_id', $marketingId))
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($status, fn (Builder $query) => $query->where('status_lead', $status))
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get();

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Kode', 'Customer', 'Telepon', 'Marketing', 'Perumahan', 'Sumber Lead', 'Status', 'Prioritas', 'Lead Diterima', 'Respons Pertama', 'Aktivitas Terakhir', 'Tindak Lanjut']);
            foreach ($rows as $row) {
                fputcsv($stream, [$row->kode_costumer, $row->nama, $row->telepon, $row->assignedMarketing?->name, $row->perumahan?->nama_perusahaan, $row->leadSource?->nama_sumber, $row->status_lead, $row->lead_priority, $row->lead_received_at?->format('Y-m-d H:i:s'), $row->first_contacted_at?->format('Y-m-d H:i:s'), $row->last_activity_at?->format('Y-m-d H:i:s'), $row->next_action_at?->format('Y-m-d H:i:s')]);
            }
            fclose($stream);
        }, 'laporan-crm-marketing-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
