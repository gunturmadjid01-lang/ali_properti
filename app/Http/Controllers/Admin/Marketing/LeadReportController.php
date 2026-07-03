<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksMarketingAccess;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Models\Costumer;
use App\Models\MarketingLeadActivity;
use App\Services\Marketing\MarketingLeadStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadReportController extends Controller
{
    use ChecksMarketingAccess, ScopesActivePerumahan;

    public function index(Request $request): Response
    {
        $this->abortUnlessMarketingAccess($request, ['manajer_pimpro', 'owner'], 'marketing.lead-report.view');
        $dateFrom = $request->query('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->query('date_to') ?: now()->toDateString();

        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();
        $statusOptions = MarketingLeadStatusService::statusOptions();

        $activities = MarketingLeadActivity::query()
            ->with(['costumer:id,kode_costumer,nama,no_identitas,telepon,marketing_lead_source_id', 'costumer.leadSource:id,nama_sumber', 'user:id,name'])
            ->whereBetween('activity_at', [$from, $to])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->orderBy('activity_at')
            ->get();

        $periodStats = $this->buildPeriodStats($activities, $statusOptions);
        $dailyRows = $this->buildDailyRows($activities, $statusOptions);

        $currentStatus = Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->selectRaw('status_lead, count(*) as total')
            ->groupBy('status_lead')
            ->pluck('total', 'status_lead')
            ->all();

        $sourceRows = Costumer::query()
            ->with('leadSource:id,nama_sumber')
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'marketing_lead_source_id'])
            ->groupBy(fn (Costumer $customer) => $customer->leadSource?->nama_sumber ?? 'Tanpa Sumber')
            ->map(fn ($items, string $label) => [
                'label' => $label,
                'total' => $items->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $timeline = MarketingLeadActivity::query()
            ->with(['costumer:id,kode_costumer,nama,no_identitas,telepon', 'user:id,name'])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $this->scopeToActivePerumahan($query, $request)))
            ->latest('activity_at')
            ->limit(80)
            ->get()
            ->map(fn (MarketingLeadActivity $activity) => [
                'id' => $activity->id,
                'tanggal' => optional($activity->activity_at)->format('d/m/Y H:i'),
                'customer' => $activity->costumer?->nama ?? '-',
                'kode_customer' => $activity->costumer?->kode_costumer ?? '-',
                'status_from' => $this->labelStatus($activity->status_from, $statusOptions),
                'status_to' => $this->labelStatus($activity->status_to, $statusOptions),
                'user' => $activity->user?->name ?? 'Sistem',
                'note' => $activity->note ?: '-',
            ]);

        return Inertia::render('Admin/Marketing/LeadReport/Index', [
            'title' => 'Laporan Lead',
            'description' => 'Pantau jumlah lead baru, survey, booking fee, SPR, closing, dan batal berdasarkan histori perubahan status customer.',
            'baseUrl' => route('admin.marketing.laporan-lead.index', absolute: false),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'statusOptions' => $statusOptions,
            'summary' => [
                'total_customers' => Costumer::query()
                    ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
                    ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
                    ->count(),
                'activities' => $activities->count(),
                'closing_period' => $periodStats[MarketingLeadStatusService::CLOSING]['total'] ?? 0,
                'booking_period' => $periodStats[MarketingLeadStatusService::BOOKING_FEE]['total'] ?? 0,
            ],
            'currentStatus' => collect($statusOptions)->map(fn (array $option) => [
                'value' => $option['value'],
                'label' => $option['label'],
                'total' => (int) ($currentStatus[$option['value']] ?? 0),
            ])->values(),
            'periodStats' => array_values($periodStats),
            'dailyRows' => array_values($dailyRows),
            'sourceRows' => $sourceRows,
            'timeline' => $timeline,
        ]);
    }

    private function buildPeriodStats($activities, array $statusOptions): array
    {
        $rows = [];

        foreach ($statusOptions as $option) {
            $status = $option['value'];
            $customerIds = $activities
                ->where('status_to', $status)
                ->pluck('costumer_id')
                ->unique();

            $rows[$status] = [
                'value' => $status,
                'label' => $option['label'],
                'total' => $customerIds->count(),
            ];
        }

        return $rows;
    }

    private function buildDailyRows($activities, array $statusOptions): array
    {
        $labels = collect($statusOptions)->pluck('label', 'value')->all();

        return $activities
            ->groupBy(fn (MarketingLeadActivity $activity) => optional($activity->activity_at)->format('Y-m-d'))
            ->map(function ($items, string $date) use ($statusOptions, $labels): array {
                $row = [
                    'tanggal' => Carbon::parse($date)->format('d/m/Y'),
                    'total' => $items->pluck('costumer_id')->unique()->count(),
                    'statuses' => [],
                ];

                foreach ($statusOptions as $option) {
                    $count = $items
                        ->where('status_to', $option['value'])
                        ->pluck('costumer_id')
                        ->unique()
                        ->count();

                    if ($count > 0) {
                        $row['statuses'][] = [
                            'label' => $labels[$option['value']] ?? $option['value'],
                            'total' => $count,
                        ];
                    }
                }

                return $row;
            })
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    private function labelStatus(?string $status, array $options): string
    {
        if (! $status) {
            return '-';
        }

        foreach ($options as $option) {
            if ($option['value'] === $status) {
                return $option['label'];
            }
        }

        return $status;
    }

    private function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }
}
