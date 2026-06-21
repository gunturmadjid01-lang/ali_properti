<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\BankKredit;
use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\DetailRumah;
use App\Models\MarketingLeadActivity;
use App\Models\MarketingReminder;
use App\Models\MarketingSurveySchedule;
use App\Models\Perumahan;
use App\Models\Spr;
use App\Models\User;
use App\Services\Marketing\MarketingLeadStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingToolsController extends Controller
{
    protected array $sections = [
        'unit-stock' => 'Unit Available / Stock Unit',
        'pricelist' => 'Pricelist Aktif',
        'simulasi-pembayaran' => 'Simulasi Pembayaran',
        'riwayat-komunikasi' => 'Riwayat Komunikasi Customer',
        'hot-lead' => 'Hot Lead / Prioritas Lead',
        'distribusi-lead' => 'Distribusi Lead',
        'monitoring-aktivitas' => 'Monitoring Aktivitas Marketing',
        'approval-diskon' => 'Approval Diskon / Promo',
        'aging-lead' => 'Aging Lead',
        'leaderboard-sales' => 'Leaderboard Sales',
    ];

    public function show(Request $request, string $section): Response
    {
        abort_unless(array_key_exists($section, $this->sections), 404);

        return Inertia::render('Admin/Marketing/Tools/Index', [
            'title' => $this->sections[$section],
            'section' => $section,
            'baseUrl' => route('admin.marketing.tools.show', $section, absolute: false),
            'filters' => $request->only(['search', 'status', 'perumahan_id', 'date_from', 'date_to']),
            'data' => $this->data($request, $section),
        ]);
    }

    public function assignLead(Request $request): RedirectResponse
    {
        abort_unless($this->canManageTeam($request), 403, 'Hanya supervisor marketing yang dapat distribusi lead.');

        $validated = $request->validate([
            'costumer_id' => ['required', 'exists:costumers,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $marketing = User::query()
            ->whereKey($validated['user_id'])
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
            ->firstOrFail();

        Costumer::query()
            ->whereKey($validated['costumer_id'])
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
            'approval-diskon' => $this->discountData($request),
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
            'banks' => BankKredit::query()
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
        abort_unless($this->canManageTeam($request), 403);

        return [
            'rows' => Costumer::query()
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
        abort_unless($this->canManageTeam($request), 403);
        $dateFrom = $request->query('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->query('date_to') ?: now()->toDateString();

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => User::query()
                ->withCount([
                    'costumers as lead_count',
                    'costumerFollowUps as follow_up_count' => fn (Builder $query) => $query->whereBetween('tanggal_follow_up', [$dateFrom, $dateTo]),
                    'surveySchedules as survey_count' => fn (Builder $query) => $query->whereBetween('tanggal_survey', [$dateFrom, $dateTo]),
                    'sprs as spr_count' => fn (Builder $query) => $query->whereBetween('tanggal_spr', [$dateFrom, $dateTo]),
                    'marketingReminders as overdue_reminder_count' => fn (Builder $query) => $query
                        ->where('status', 'menunggu')
                        ->whereBetween('remind_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
                        ->where('remind_at', '<', now()),
                ])
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lead' => $user->lead_count,
                    'follow_up' => $user->follow_up_count,
                    'survey' => $user->survey_count,
                    'spr' => $user->spr_count,
                    'overdue' => $user->overdue_reminder_count,
                ])
                ->values(),
        ];
    }

    protected function discountData(Request $request): array
    {
        abort_unless($this->canManageTeam($request), 403);

        return [
            'rows' => Spr::query()
                ->with(['costumer:id,nama', 'creator:id,name', 'detailRumah:id,harga_jual,kode_nlok,nomor_rumah'])
                ->whereColumn('harga_jual', '<', 'nilai_pengajuan_akhir')
                ->orWhereNotNull('penambahan_lain_lain')
                ->latest('id')
                ->limit(120)
                ->get()
                ->map(fn (Spr $spr) => [
                    'id' => $spr->id,
                    'kode_spr' => $spr->kode_spr,
                    'customer' => $spr->costumer?->nama ?? '-',
                    'marketing' => $spr->creator?->name ?? '-',
                    'harga_jual' => (float) $spr->harga_jual,
                    'nilai_akhir' => (float) $spr->nilai_pengajuan_akhir,
                    'catatan' => $spr->catatan ?: $spr->penambahan_lain_lain ?: '-',
                    'status' => $spr->status,
                ])
                ->values(),
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
                        ? \Carbon\Carbon::parse($customer->lead_activities_max_activity_at)
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
        abort_unless($this->canManageTeam($request), 403);

        return [
            'rows' => User::query()
                ->withCount(['costumers as lead_count', 'sprs as spr_count'])
                ->withSum(['sprs as nilai_penjualan' => fn (Builder $query) => $query->where('status', Spr::STATUS_DISETUJUI)], 'harga_jual')
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
                ->orderByDesc('nilai_penjualan')
                ->get(['id', 'name'])
                ->map(fn (User $user, int $index) => [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'lead' => $user->lead_count,
                    'spr' => $user->spr_count,
                    'nilai' => (float) ($user->nilai_penjualan ?? 0),
                ])
                ->values(),
        ];
    }

    protected function unitQuery(Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));

        return DetailRumah::query()
            ->with('perumahan:id,nama_perusahaan')
            ->when($request->query('perumahan_id'), fn (Builder $query, string $id) => $query->where('perumahan_id', $id))
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
            'spesifikasi' => $unit->spesifikasi,
            'catatan' => $unit->catatan,
        ];
    }

    protected function perumahanOptions(): array
    {
        return Perumahan::query()
            ->orderBy('nama_perusahaan')
            ->get(['id', 'nama_perusahaan'])
            ->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])
            ->prepend(['value' => '', 'label' => 'Semua Perumahan'])
            ->values()
            ->all();
    }

    protected function marketingOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['marketing', 'area_marketing']))
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
        return (bool) $request->user()?->hasAnyRole(['supervisor_marketing', 'owner', 'super_admin']);
    }
}
