<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingLeadActivity;
use App\Services\Marketing\MarketingLeadStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PipelineReportController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = $request->query('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->query('date_to') ?: now()->toDateString();
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();
        $options = MarketingLeadStatusService::statusOptions();
        $labels = collect($options)->pluck('label', 'value');

        $activities = MarketingLeadActivity::query()
            ->with(['costumer:id,kode_costumer,nama,telepon', 'user:id,name'])
            ->whereBetween('activity_at', [$from, $to])
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $query->where('created_by', $request->user()?->id)))
            ->orderBy('activity_at')
            ->get();

        $stageRows = collect($options)->map(function (array $option, int $index) use ($activities): array {
            $customerIds = $activities
                ->where('status_to', $option['value'])
                ->pluck('costumer_id')
                ->unique();
            $previousTotal = $index === 0
                ? $customerIds->count()
                : $activities->where('status_to', MarketingLeadStatusService::statusOptions()[$index - 1]['value'])->pluck('costumer_id')->unique()->count();

            return [
                'value' => $option['value'],
                'label' => $option['label'],
                'total' => $customerIds->count(),
                'conversion' => $previousTotal > 0
                    ? round(($customerIds->count() / $previousTotal) * 100, 1)
                    : 0,
            ];
        })->values();

        $marketingRows = $activities
            ->groupBy(fn (MarketingLeadActivity $activity) => $activity->user_id ?: 0)
            ->map(function ($items): array {
                $user = $items->first()?->user;

                return [
                    'user' => $user?->name ?? 'Sistem',
                    'activities' => $items->count(),
                    'customers' => $items->pluck('costumer_id')->unique()->count(),
                    'survey' => $items->where('status_to', MarketingLeadStatusService::SURVEY_LOKASI)->pluck('costumer_id')->unique()->count(),
                    'spr' => $items->where('status_to', MarketingLeadStatusService::SPR)->pluck('costumer_id')->unique()->count(),
                    'closing' => $items->where('status_to', MarketingLeadStatusService::CLOSING)->pluck('costumer_id')->unique()->count(),
                ];
            })
            ->sortByDesc('closing')
            ->values();

        $dailyRows = $activities
            ->groupBy(fn (MarketingLeadActivity $activity) => optional($activity->activity_at)->format('Y-m-d'))
            ->map(fn ($items, string $date) => [
                'date' => Carbon::parse($date)->format('d/m/Y'),
                'total' => $items->count(),
                'customers' => $items->pluck('costumer_id')->unique()->count(),
                'closing' => $items->where('status_to', MarketingLeadStatusService::CLOSING)->pluck('costumer_id')->unique()->count(),
            ])
            ->sortKeysDesc()
            ->values();

        $timeline = $activities
            ->sortByDesc('activity_at')
            ->take(100)
            ->values()
            ->map(fn (MarketingLeadActivity $activity) => [
                'id' => $activity->id,
                'date' => optional($activity->activity_at)->format('d/m/Y H:i'),
                'customer' => $activity->costumer?->nama ?? '-',
                'code' => $activity->costumer?->kode_costumer ?? '-',
                'from' => $labels[$activity->status_from] ?? ($activity->status_from ?: '-'),
                'to' => $labels[$activity->status_to] ?? $activity->status_to,
                'user' => $activity->user?->name ?? 'Sistem',
                'note' => $activity->note ?: '-',
            ]);

        $uniqueCustomers = $activities->pluck('costumer_id')->unique()->count();
        $closing = $activities->where('status_to', MarketingLeadStatusService::CLOSING)->pluck('costumer_id')->unique()->count();

        return Inertia::render('Admin/Marketing/PipelineReport/Index', [
            'title' => 'Laporan Pipeline Marketing',
            'description' => 'Analisis pergerakan customer, konversi setiap tahap, dan performa marketing berdasarkan periode.',
            'baseUrl' => route('admin.marketing.laporan-pipeline.index', absolute: false),
            'filters' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'summary' => [
                'activities' => $activities->count(),
                'customers' => $uniqueCustomers,
                'closing' => $closing,
                'closing_rate' => $uniqueCustomers > 0 ? round(($closing / $uniqueCustomers) * 100, 1) : 0,
            ],
            'stageRows' => $stageRows,
            'marketingRows' => $marketingRows,
            'dailyRows' => $dailyRows,
            'timeline' => $timeline,
        ]);
    }

    private function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }
}
