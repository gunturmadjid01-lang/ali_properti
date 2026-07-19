<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Concerns\ChecksMarketingAccess;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\BankKredit;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\DetailRumah;
use App\Models\MarketingReminder;
use App\Models\Perumahan;
use App\Models\SalesProcessStep;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\User;
use App\Services\Marketing\MarketingLeadStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingToolsController extends Controller
{
    use ChecksMarketingAccess, ScopesActivePerumahan;

    protected array $sections = [
        'unit-stock' => 'Unit Available / Stock Unit',
        'pricelist' => 'Pricelist Aktif',
        'simulasi-pembayaran' => 'Simulasi Pembayaran',
        'riwayat-komunikasi' => 'Riwayat Komunikasi Customer',
        'hot-lead' => 'Hot Lead / Prioritas Lead',
        'distribusi-lead' => 'Distribusi Lead',
        'monitoring-aktivitas' => 'Monitoring Aktivitas Marketing',
        'aging-lead' => 'Aging Lead',
        'leaderboard-sales' => 'Statistik & Ranking Marketing',
    ];

    public function show(Request $request, string $section): Response
    {
        abort_unless(array_key_exists($section, $this->sections), 404);
        $this->abortUnlessMarketingAccess($request, $this->defaultRolesForSection($section), $this->permissionForSection($section));

        return Inertia::render('Admin/Marketing/Tools/Index', [
            'title' => $this->sections[$section],
            'section' => $section,
            'baseUrl' => route('admin.marketing.tools.show', $section, absolute: false),
            'filters' => $request->only(['search', 'status', 'perumahan_id', 'date_from', 'date_to', 'period', 'reference_date', 'marketing_id']),
            'data' => $this->data($request, $section),
            'permissions' => $this->permissionsForSection($request, $section),
        ]);
    }

    public function assignLead(Request $request): RedirectResponse
    {
        $this->abortUnlessMarketingAccess($request, ['supervisor_marketing'], 'marketing.lead-distribution.manage', 403, 'Hanya supervisor marketing yang dapat distribusi lead.');

        $validated = $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);
        $activePerumahanId = $this->shouldScopeToActivePerumahan($request)
            ? $this->ensureActivePerumahan($request)
            : null;

        $marketing = User::query()
            ->whereKey($validated['user_id'])
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($id)))
            ->firstOrFail();

        Costumer::query()
            ->whereKey($validated['costumer_id'])
            ->when($activePerumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id))
            ->firstOrFail()
            ->update([
                'created_by' => $marketing->id,
                'updated_by' => $request->user()?->id,
            ]);

        return back()->with('success', 'Lead berhasil didistribusikan ke '.$marketing->name.'.');
    }

    protected function data(Request $request, string $section): array
    {
        return match ($section) {
            'unit-stock' => $this->unitStockData($request),
            'pricelist' => $this->pricelistData($request),
            'simulasi-pembayaran' => $this->simulationData($request),
            'riwayat-komunikasi' => $this->communicationData($request),
            'hot-lead' => $this->hotLeadData($request),
            'distribusi-lead' => $this->distributionData($request),
            'monitoring-aktivitas' => $this->activityData($request),
            'aging-lead' => $this->agingLeadData($request),
            'leaderboard-sales' => $this->leaderboardData($request),
        };
    }

    protected function unitStockData(Request $request): array
    {
        $query = $this->unitQuery($request)
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status_penjualan', $status));

        $rows = (clone $query)->latest('id')->limit(300)->get()->map(fn (DetailRumah $unit) => $this->unitRow($unit))->values();
        $summary = (clone $this->unitQuery($request))
            ->selectRaw('status_penjualan, count(*) as total')
            ->groupBy('status_penjualan')
            ->pluck('total', 'status_penjualan')
            ->all();

        return [
            'rows' => $rows,
            'summary' => [
                'tersedia' => (int) ($summary['tersedia'] ?? 0),
                'booking' => (int) ($summary['booking'] ?? 0),
                'dp' => (int) ($summary['dp'] ?? 0) + (int) ($summary['dp_lunas'] ?? 0),
                'proses' => (int) ($summary['proses_penjualan'] ?? 0),
                'terjual' => (int) ($summary['terjual'] ?? 0),
                'hold' => (int) ($summary['hold'] ?? 0),
            ],
            'statusOptions' => $this->statusPenjualanOptions(),
            'perumahanOptions' => $this->perumahanOptions(),
        ];
    }

    protected function pricelistData(Request $request): array
    {
        $rows = $this->unitQuery($request)
            ->where('status', 'aktif')
            ->orderBy('perumahan_id')
            ->orderBy('kode_nlok')
            ->orderBy('nomor_rumah')
            ->limit(300)
            ->get()
            ->map(fn (DetailRumah $unit) => [
                ...$this->unitRow($unit),
                'booking_fee_saran' => round(((float) $unit->harga_jual) * 0.01),
                'dp_10' => round(((float) $unit->harga_jual) * 0.1),
                'dp_20' => round(((float) $unit->harga_jual) * 0.2),
            ])
            ->values();

        return [
            'rows' => $rows,
            'perumahanOptions' => $this->perumahanOptions(),
        ];
    }

    protected function simulationData(Request $request): array
    {
        return [
            'units' => $this->unitQuery($request)
                ->whereIn('status_penjualan', ['tersedia', 'hold'])
                ->orderBy('perumahan_id')
                ->orderBy('kode_nlok')
                ->limit(300)
                ->get()
                ->map(fn (DetailRumah $unit) => [
                    'value' => (string) $unit->id,
                    'label' => trim(($unit->kode_nlok ?? '').' '.($unit->nomor_rumah ?? '')).' - '.$unit->perumahan?->nama_perusahaan.' - Rp '.number_format((float) $unit->harga_jual, 0, ',', '.'),
                    'harga_jual' => (float) $unit->harga_jual,
                ])
                ->values(),
            'banks' => BankKredit::query()->finalized()
                ->where('status', 'aktif')
                ->orderBy('nama_bank')
                ->get(['id', 'nama_bank', 'bunga_tahunan', 'tenor_min_bulan', 'tenor_max_bulan', 'minimal_dp_persen', 'biaya_provisi_persen', 'biaya_admin'])
                ->map(fn (BankKredit $bank) => [
                    'value' => (string) $bank->id,
                    'label' => $bank->nama_bank.' - '.$bank->bunga_tahunan.'% / tahun',
                    'bunga_tahunan' => (float) $bank->bunga_tahunan,
                    'tenor_min_bulan' => (int) $bank->tenor_min_bulan,
                    'tenor_max_bulan' => (int) $bank->tenor_max_bulan,
                    'minimal_dp_persen' => (float) $bank->minimal_dp_persen,
                    'biaya_provisi_persen' => (float) $bank->biaya_provisi_persen,
                    'biaya_admin' => (float) $bank->biaya_admin,
                ])
                ->values(),
        ];
    }

    protected function communicationData(Request $request): array
    {
        return [
            'rows' => $this->customerQuery($request)
                ->with(['followUps.user:id,name', 'surveySchedules.marketing:id,name', 'reminders.user:id,name'])
                ->latest('id')
                ->limit(120)
                ->get()
                ->map(fn (Costumer $customer) => [
                    'id' => $customer->id,
                    'customer' => $customer->nama,
                    'telepon' => $customer->telepon,
                    'status' => $this->statusLabel($customer->status_lead),
                    'follow_ups' => $customer->followUps->sortByDesc('tanggal_follow_up')->take(5)->map(fn (CostumerFollowUp $row) => [
                        'tanggal' => optional($row->tanggal_follow_up)->format('d/m/Y'),
                        'metode' => $row->metode_follow_up,
                        'catatan' => $row->catatan,
                        'user' => $row->user?->name ?? '-',
                    ])->values(),
                    'reminders' => $customer->reminders->sortBy('remind_at')->take(3)->map(fn (MarketingReminder $row) => [
                        'tanggal' => optional($row->remind_at)->format('d/m/Y H:i'),
                        'judul' => $row->judul,
                        'status' => $row->status,
                    ])->values(),
                ])
                ->values(),
        ];
    }

    protected function hotLeadData(Request $request): array
    {
        return [
            'rows' => $this->customerQuery($request)
                ->with(['followUps' => fn ($query) => $query->latest('tanggal_follow_up'), 'leadSource:id,nama_sumber'])
                ->where(function (Builder $query): void {
                    $query->whereIn('status_lead', ['negosiasi', 'survey_lokasi', 'spr'])
                        ->orWhereHas('followUps', fn (Builder $query) => $query->whereIn('progress_kemampuan', ['high', 'very_high']));
                })
                ->latest('id')
                ->limit(150)
                ->get()
                ->map(fn (Costumer $customer) => [
                    'id' => $customer->id,
                    'customer' => $customer->nama,
                    'telepon' => $customer->telepon,
                    'sumber' => $customer->leadSource?->nama_sumber ?? '-',
                    'status' => $this->statusLabel($customer->status_lead),
                    'progress' => $customer->followUps->first()?->progress_kemampuan ?? '-',
                    'catatan' => $customer->followUps->first()?->catatan ?? '-',
                    'last_follow_up' => optional($customer->followUps->first()?->tanggal_follow_up)->format('d/m/Y'),
                ])
                ->values(),
        ];
    }

    protected function distributionData(Request $request): array
    {
        $this->abortUnlessMarketingAccess($request, ['supervisor_marketing'], 'marketing.lead-distribution.view');

        return [
            'rows' => $this->customerQuery($request)
                ->with(['leadSource:id,nama_sumber', 'creator:id,name'])
                ->orderByRaw('CASE WHEN created_by IS NULL THEN 0 ELSE 1 END')
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (Costumer $customer) => [
                    'id' => $customer->id,
                    'kode' => $customer->kode_costumer,
                    'customer' => $customer->nama,
                    'telepon' => $customer->telepon,
                    'sumber' => $customer->leadSource?->nama_sumber ?? '-',
                    'status' => $this->statusLabel($customer->status_lead),
                    'marketing' => $customer->creator?->name ?? 'Belum dibagi',
                    'created_at' => optional($customer->created_at)->format('d/m/Y H:i'),
                ])
                ->values(),
            'marketingOptions' => $this->marketingOptions(),
        ];
    }

    protected function activityData(Request $request): array
    {
        $this->abortUnlessMarketingAccess($request, ['owner', 'manager', 'supervisor_marketing'], 'marketing.activity.view');
        $dateFrom = $request->query('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->query('date_to') ?: now()->toDateString();
        $periodStart = Carbon::parse($dateFrom)->startOfDay();
        $periodEnd = Carbon::parse($dateTo)->endOfDay();
        $activePerumahanId = $this->shouldScopeToActivePerumahan($request)
            ? $this->activePerumahanId($request)
            : null;
        $canViewAll = $request->user()?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing', 'super_admin'])
            || $request->user()?->can('marketing.activity.view-all');
        $selectedMarketingId = $canViewAll ? $request->integer('marketing_id') : (int) $request->user()->id;

        $rows = User::query()
            ->withCount([
                'costumers as lead_count' => fn (Builder $query) => $query
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)),
                'costumerFollowUps as follow_up_count' => fn (Builder $query) => $query
                    ->whereBetween('tanggal_follow_up', [$periodStart, $periodEnd])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('costumer', fn (Builder $query) => $query->where('perumahan_id', $id))),
                'surveySchedules as survey_count' => fn (Builder $query) => $query
                    ->whereBetween('tanggal_survey', [$periodStart, $periodEnd])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)),
                'sprs as spr_count' => fn (Builder $query) => $query
                    ->whereBetween('tanggal_spr', [$periodStart, $periodEnd])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $id))),
                'marketingReminders as overdue_reminder_count' => fn (Builder $query) => $query
                    ->where('status', 'menunggu')
                    ->whereBetween('remind_at', [$periodStart, $periodEnd])
                    ->where('remind_at', '<', now())
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('costumer', fn (Builder $query) => $query->where('perumahan_id', $id))),
            ])
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->when($selectedMarketingId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($id)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $user): array {
                $lead = (int) $user->lead_count;
                $followUp = (int) $user->follow_up_count;
                $survey = (int) $user->survey_count;
                $spr = (int) $user->spr_count;
                $overdue = (int) $user->overdue_reminder_count;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lead' => $lead,
                    'follow_up' => $followUp,
                    'survey' => $survey,
                    'spr' => $spr,
                    'overdue' => $overdue,
                    'follow_up_rate' => $lead > 0 ? round(min(100, ($followUp / $lead) * 100), 1) : 0,
                    'spr_conversion' => $lead > 0 ? round(($spr / $lead) * 100, 1) : 0,
                    'activity_score' => max(0, ($followUp * 2) + ($survey * 5) + ($spr * 12) - ($overdue * 3)),
                ];
            })->sortByDesc('activity_score')->values();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => $rows,
            'can_view_all' => $canViewAll,
            'selected_marketing_id' => $selectedMarketingId ? (string) $selectedMarketingId : '',
            'marketing_options' => collect($this->marketingOptions())->prepend(['value' => '', 'label' => 'Seluruh Marketing'])->values(),
            'summary' => [
                'marketing' => $rows->count(),
                'lead' => $rows->sum('lead'),
                'follow_up' => $rows->sum('follow_up'),
                'survey' => $rows->sum('survey'),
                'spr' => $rows->sum('spr'),
                'overdue' => $rows->sum('overdue'),
                'top_activity' => $rows->first()['name'] ?? '-',
            ],
        ];
    }

    protected function agingLeadData(Request $request): array
    {
        return [
            'rows' => $this->customerQuery($request)
                ->withMax('leadActivities', 'activity_at')
                ->with('creator:id,name')
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(function (Costumer $customer): array {
                    $last = $customer->lead_activities_max_activity_at
                        ? Carbon::parse($customer->lead_activities_max_activity_at)
                        : $customer->created_at;

                    return [
                        'id' => $customer->id,
                        'customer' => $customer->nama,
                        'telepon' => $customer->telepon,
                        'marketing' => $customer->creator?->name ?? '-',
                        'status' => $this->statusLabel($customer->status_lead),
                        'last_activity' => optional($last)->format('d/m/Y H:i'),
                        'age_days' => $last ? $last->diffInDays(now()) : 0,
                    ];
                })
                ->sortByDesc('age_days')
                ->values(),
        ];
    }

    protected function leaderboardData(Request $request): array
    {
        $this->abortUnlessMarketingAccess($request, ['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing', 'marketing', 'area_marketing'], 'marketing.leaderboard.view');

        $period = in_array($request->query('period'), ['week', 'month', 'year'], true)
            ? $request->query('period')
            : 'week';
        $referenceDate = Carbon::parse($request->query('reference_date') ?: now()->toDateString());
        [$from, $to] = match ($period) {
            'month' => [$referenceDate->copy()->startOfMonth(), $referenceDate->copy()->endOfMonth()],
            'year' => [$referenceDate->copy()->startOfYear(), $referenceDate->copy()->endOfYear()],
            default => [$referenceDate->copy()->startOfWeek(Carbon::MONDAY), $referenceDate->copy()->endOfWeek(Carbon::SUNDAY)],
        };
        $activePerumahanId = $this->shouldScopeToActivePerumahan($request)
            ? $this->activePerumahanId($request)
            : null;
        $canViewAll = $request->user()?->hasAnyRole(['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing']) || $request->user()?->can('marketing.leaderboard.view-all');
        $selectedMarketingId = $canViewAll ? $request->integer('marketing_id') : (int) $request->user()->id;

        $rows = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->when($selectedMarketingId, fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($id)))
            ->withCount([
                'costumers as lead_count' => fn (Builder $query) => $query
                    ->whereBetween('created_at', [$from, $to])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)),
                'surveySchedules as survey_count' => fn (Builder $query) => $query
                    ->whereBetween('tanggal_survey', [$from, $to])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->where('perumahan_id', $id)),
                'sprs as spr_count' => fn (Builder $query) => $query
                    ->whereBetween('tanggal_spr', [$from, $to])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $id))),
                'sprs as closing_count' => fn (Builder $query) => $query
                    ->where('status', Spr::STATUS_DISETUJUI)
                    ->whereBetween('tanggal_spr', [$from, $to])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $id))),
            ])
            ->withSum([
                'sprs as nilai_penjualan' => fn (Builder $query) => $query
                    ->where('status', Spr::STATUS_DISETUJUI)
                    ->whereBetween('tanggal_spr', [$from, $to])
                    ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $id))),
            ], 'nilai_pengajuan_akhir')
            ->get(['id', 'name'])
            ->sortByDesc(fn (User $user) => (float) ($user->nilai_penjualan ?? 0))
            ->values()
            ->map(function (User $user) use ($from, $to, $activePerumahanId) {
                $transactions = SalesTransaction::query()->where('marketing_user_id', $user->id)->whereBetween('approved_at', [$from, $to])->when($activePerumahanId, fn ($q, $id) => $q->where('perumahan_id', $id));
                $transactionIds = (clone $transactions)->pluck('id');
                $active = (clone $transactions)->whereNotIn('status', ['completed', 'closed_lost'])->count();
                $failed = (clone $transactions)->where('status', 'closed_lost')->count();
                $completedStages = SalesProcessStep::whereIn('sales_transaction_id', $transactionIds)->where('status', 'completed')->count();
                $stageSummary = SalesProcessStep::whereIn('sales_transaction_id', $transactionIds)->whereIn('status', ['available', 'in_progress', 'pending_approval'])->selectRaw('code, count(*) total')->groupBy('code')->pluck('total', 'code')->all();
                $score = ((int) $user->closing_count * 50) + ($completedStages * 2) + ($active * 3) - ($failed * 15);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lead' => (int) $user->lead_count,
                    'survey' => (int) $user->survey_count,
                    'spr' => (int) $user->spr_count,
                    'closing' => (int) $user->closing_count,
                    'conversion' => $user->lead_count > 0 ? round(($user->closing_count / $user->lead_count) * 100, 1) : 0,
                    'nilai' => (float) ($user->nilai_penjualan ?? 0),
                    'active_process' => $active, 'failed' => $failed, 'completed_stages' => $completedStages, 'stage_summary' => $stageSummary, 'score' => max(0, $score),
                ];
            })->sortByDesc('score')->values()->map(fn (array $row, int $index) => [...$row, 'rank' => $index + 1]);

        return [
            'period' => $period,
            'reference_date' => $referenceDate->toDateString(),
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'rows' => $rows,
            'can_view_all' => $canViewAll,
            'marketing_options' => collect($this->marketingOptions())->prepend(['value' => '', 'label' => 'Seluruh Marketing'])->values(),
            'selected_marketing_id' => $selectedMarketingId ? (string) $selectedMarketingId : '',
            'stage_summary' => collect($rows)->flatMap(fn ($row) => collect($row['stage_summary'])->map(fn ($total, $stage) => ['stage' => $stage, 'total' => $total]))->groupBy('stage')->map(fn ($items) => $items->sum('total'))->sortDesc()->all(),
            'summary' => [
                'marketing' => $rows->count(),
                'lead' => $rows->sum('lead'),
                'survey' => $rows->sum('survey'),
                'spr' => $rows->sum('spr'),
                'closing' => $rows->sum('closing'),
                'active_process' => $rows->sum('active_process'),
                'failed' => $rows->sum('failed'),
                'nilai' => $rows->sum('nilai'),
                'top_marketing' => $rows->first()['name'] ?? '-',
            ],
        ];
    }

    protected function unitQuery(Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));

        return DetailRumah::query()->finalized()
            ->with(['perumahan:id,nama_perusahaan', 'bookingSpr.costumer:id,nama,pekerjaan'])
            ->when($request->query('perumahan_id'), fn (Builder $query, string $id) => $query->where('perumahan_id', $id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('kode_nlok', 'like', "%{$search}%")
                    ->orWhere('nomor_rumah', 'like', "%{$search}%")
                    ->orWhere('tipe_rumah', 'like', "%{$search}%")
                    ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
            }));
    }

    protected function customerQuery(Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));

        return Costumer::query()
            ->when($this->shouldScopeToCurrentMarketing($request), fn (Builder $query) => $query->where('created_by', $request->user()?->id))
            ->when($this->shouldScopeToActivePerumahan($request), fn (Builder $query) => $this->scopeToActivePerumahan($query, $request))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode_costumer', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%");
            }));
    }

    protected function unitRow(DetailRumah $unit): array
    {
        return [
            'id' => $unit->id,
            'perumahan' => $unit->perumahan?->nama_perusahaan ?? '-',
            'blok' => $unit->kode_nlok,
            'nomor' => $unit->nomor_rumah,
            'unit' => trim(($unit->kode_nlok ?? '').' '.($unit->nomor_rumah ?? '')),
            'tipe' => $unit->tipe_rumah ?: '-',
            'model' => $unit->model_unit ?: '-',
            'luas_bangunan' => $unit->luas_bangunan ?: '-',
            'luas_tanah' => $unit->luas_tanah ?: '-',
            'harga_jual' => (float) $unit->harga_jual,
            'status_penjualan' => $unit->status_penjualan ?: '-',
            'status_pembangunan' => $unit->status_pembangunan ?: '-',
            'progress' => (float) ($unit->progress_terakhir ?? 0),
            'pembeli' => $unit->bookingSpr?->costumer?->nama ?? '-',
            'pekerjaan_pembeli' => $unit->bookingSpr?->costumer?->pekerjaan ?? '-',
            'spesifikasi' => $unit->spesifikasi,
            'catatan' => $unit->catatan,
        ];
    }

    protected function perumahanOptions(): array
    {
        return Perumahan::query()->finalized()
            ->when($this->shouldScopeToActivePerumahan(request()), fn (Builder $query) => $query->whereIn('id', $this->assignedPerumahanIds(request())))
            ->orderBy('nama_perusahaan')
            ->get(['id', 'nama_perusahaan'])
            ->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])
            ->prepend(['value' => '', 'label' => 'Semua Perumahan'])
            ->values()
            ->all();
    }

    protected function marketingOptions(): array
    {
        $activePerumahanId = $this->shouldScopeToActivePerumahan(request())
            ? $this->activePerumahanId(request())
            : null;

        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->when($activePerumahanId, fn (Builder $query, int $id) => $query->whereHas('perumahans', fn (Builder $query) => $query->whereKey($id)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $row) => ['value' => (string) $row->id, 'label' => $row->name])
            ->values()
            ->all();
    }

    protected function statusPenjualanOptions(): array
    {
        return collect(['tersedia', 'booking', 'dp', 'dp_lunas', 'proses_penjualan', 'terjual', 'hold', 'batal'])
            ->map(fn (string $status) => ['value' => $status, 'label' => ucwords(str_replace('_', ' ', $status))])
            ->prepend(['value' => '', 'label' => 'Semua Status'])
            ->values()
            ->all();
    }

    protected function statusLabel(?string $status): string
    {
        foreach (MarketingLeadStatusService::statusOptions() as $option) {
            if ($option['value'] === $status) {
                return $option['label'];
            }
        }

        return $status ?? '-';
    }

    protected function shouldScopeToCurrentMarketing(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing'])
            && ! $user->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }

    protected function canManageTeam(Request $request): bool
    {
        return (bool) $request->user()?->hasAnyRole([
            'supervisor_marketing',
            'manajer_pimpro',
            'owner',
            'super_admin',
        ]);
    }

    protected function permissionsForSection(Request $request, string $section): array
    {
        $user = $request->user();

        return match ($section) {
            'distribusi-lead' => [
                'canView' => $this->hasMarketingAccess($request, ['supervisor_marketing'], 'marketing.lead-distribution.manage'),
                'canCreate' => (bool) $user?->can('marketing.lead-distribution.create') || $user?->can('marketing.lead-distribution.manage'),
                'canUpdate' => (bool) $user?->can('marketing.lead-distribution.update') || $user?->can('marketing.lead-distribution.manage'),
                'canDelete' => (bool) $user?->can('marketing.lead-distribution.delete') || $user?->can('marketing.lead-distribution.manage'),
                'canUnlock' => (bool) $user?->can('marketing.lead-distribution.unlock') || $user?->can('marketing.lead-distribution.manage'),
            ],
            'monitoring-aktivitas' => [
                'canView' => $this->hasMarketingAccess($request, ['owner', 'manajer_pimpro', 'supervisor_marketing'], 'marketing.activity.view'),
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'canUnlock' => false,
            ],
            'leaderboard-sales' => [
                'canView' => $this->hasMarketingAccess($request, ['owner', 'manajer_pimpro', 'supervisor_marketing'], 'marketing.leaderboard.view'),
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'canUnlock' => false,
            ],
            default => [
                'canView' => true,
                'canCreate' => false,
                'canUpdate' => false,
                'canDelete' => false,
                'canUnlock' => false,
            ],
        };
    }

    protected function defaultRolesForSection(string $section): array
    {
        return match ($section) {
            'distribusi-lead' => ['supervisor_marketing'],
            'monitoring-aktivitas' => ['owner', 'manajer_pimpro', 'supervisor_marketing'],
            'leaderboard-sales' => ['owner', 'manager', 'manajer_pimpro', 'supervisor_marketing', 'marketing', 'area_marketing'],
            default => ['marketing', 'area_marketing', 'supervisor_marketing', 'manager', 'owner', 'manajer_pimpro', 'pengawas'],
        };
    }

    protected function permissionForSection(string $section): ?string
    {
        return match ($section) {
            'distribusi-lead' => 'marketing.lead-distribution.manage',
            'monitoring-aktivitas' => 'marketing.activity.view',
            'leaderboard-sales' => 'marketing.leaderboard.view',
            default => null,
        };
    }
}
