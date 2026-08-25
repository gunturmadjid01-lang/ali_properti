<?php

namespace App\Services\Marketing;

use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\HousingReservation;
use App\Models\MarketingEvaluation;
use App\Models\MarketingLead;
use App\Models\MarketingLeadActivity;
use App\Models\MarketingTarget;
use App\Models\MarketingVisit;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MarketingReportService
{
    public const TYPES = [
        'activities' => 'Aktivitas Marketing', 'follow-ups' => 'Follow-up', 'visits' => 'Kunjungan & Lokasi',
        'inactive-customers' => 'Customer Tidak Aktif', 'pipeline' => 'Pipeline Lead', 'conversion' => 'Konversi',
        'targets' => 'Pencapaian Target', 'cancellations' => 'Pembatalan', 'performance' => 'Kinerja Marketing',
    ];

    public function report(string $type, Request $request, bool $paginate = true): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$from, $to] = $this->period($request);
        [$columns, $query, $map] = match ($type) {
            'activities' => $this->activities($request, $from, $to),
            'follow-ups' => $this->followUps($request, $from, $to),
            'visits' => $this->visits($request, $from, $to),
            'inactive-customers' => $this->inactive($request),
            'pipeline' => $this->pipeline($request, $from, $to),
            'conversion' => $this->conversion($request, $from, $to),
            'targets' => $this->targets($request, $from, $to),
            'cancellations' => $this->cancellations($request, $from, $to),
            'performance' => $this->performance($request, $from, $to),
        };
        $this->scopeMarketing($query, $request, $type);
        if ($paginate) {
            $result = $query->paginate(25)->withQueryString();
            if ($type === 'targets') {
                $metrics = $this->targetMetrics($result->getCollection());
                $result = $result->through(fn (MarketingTarget $target) => $this->targetRow($target, $metrics->get($target->id, [])));
            } else {
                $result = $result->through($map);
            }
        } else {
            $rows = $query->limit(5000)->get();
            if ($type === 'targets') {
                $metrics = $this->targetMetrics($rows);
                $result = $rows->map(fn (MarketingTarget $target) => $this->targetRow($target, $metrics->get($target->id, [])));
            } else {
                $result = $rows->map($map);
            }
        }

        return ['title' => self::TYPES[$type], 'columns' => $columns, 'rows' => $result];
    }

    private function activities(Request $r, Carbon $from, Carbon $to): array
    {
        $q = MarketingLeadActivity::query()->with(['user:id,name', 'costumer:id,nama,kode_costumer,perumahan_id', 'costumer.perumahan:id,nama_perusahaan'])
            ->whereBetween('activity_at', [$from, $to])->when($r->filled('customer_id'), fn (Builder $q) => $q->where('costumer_id', $r->integer('customer_id')))
            ->when($r->filled('activity_type'), fn (Builder $q) => $q->where('activity_type', $r->string('activity_type')))->latest('activity_at');

        return [['Waktu', 'Marketing', 'Customer', 'Perumahan', 'Aktivitas', 'Status', 'Hasil/Tindak Lanjut'], $q, fn ($x) => [$x->activity_at?->format('d/m/Y H:i'), $x->user?->name, $x->costumer?->nama, $x->costumer?->perumahan?->nama_perusahaan, $x->title ?: $x->activity_type, trim(($x->status_from ?: '-').' → '.($x->status_to ?: '-')), $x->note]];
    }

    private function followUps(Request $r, Carbon $from, Carbon $to): array
    {
        $q = CostumerFollowUp::query()->with(['user:id,name', 'lead:id,name,lead_no,perumahan_id,stage', 'lead.perumahan:id,nama_perusahaan'])
            ->whereBetween('followed_up_at', [$from, $to])->when($r->filled('lead_id'), fn (Builder $q) => $q->where('marketing_lead_id', $r->integer('lead_id')))
            ->when($r->filled('result'), fn (Builder $q) => $q->where('result_code', $r->string('result')))->latest('followed_up_at');

        return [['Waktu', 'Marketing', 'Lead', 'Perumahan', 'Media', 'Hasil', 'Catatan', 'Tindak Lanjut', 'Jadwal Berikutnya', 'Tahap Lead'], $q, fn ($x) => [$x->followed_up_at?->format('d/m/Y H:i'), $x->user?->name, $x->lead?->name, $x->lead?->perumahan?->nama_perusahaan, $x->metode_follow_up, $x->result_code, $x->catatan, $x->next_action, $x->rencana_follow_up_at?->format('d/m/Y'), $x->lead?->stage]];
    }

    private function visits(Request $r, Carbon $from, Carbon $to): array
    {
        $q = MarketingVisit::query()->with(['marketing:id,name', 'costumer:id,nama,kode_costumer,telepon', 'perumahan:id,nama_perusahaan'])->whereBetween('planned_at', [$from, $to])
            ->when($r->filled('customer_id'), fn (Builder $q) => $q->where('costumer_id', $r->integer('customer_id')))->when($r->filled('visit_type'), fn (Builder $q) => $q->where('visit_type', $r->string('visit_type')))
            ->when($r->filled('visit_status'), fn (Builder $q) => $q->where('status', $r->string('visit_status')))->when($r->filled('verification_status'), fn (Builder $q) => $q->where('verification_status', $r->string('verification_status')))->latest('planned_at');

        return [['Tanggal', 'Marketing', 'Customer/Prospek', 'Kontak', 'Instansi/Event', 'Perumahan', 'Jenis', 'Check-in', 'Check-out', 'Durasi', 'Lokasi', 'Map', 'Hasil', 'Tindak Lanjut', 'Status', 'Verifikasi'], $q, fn ($x) => [$x->planned_at?->format('d/m/Y H:i'), $x->marketing?->name, $x->costumer?->nama ?: $x->contact_name, $x->costumer?->telepon ?: $x->contact_phone, $x->organization_name ?: $x->lead_source_note, $x->perumahan?->nama_perusahaan, $x->visit_type, $x->started_at?->format('H:i'), $x->finished_at?->format('H:i'), $x->started_at && $x->finished_at ? $x->started_at->diffInMinutes($x->finished_at).' menit' : '-', ($x->location ?: '-').($x->check_in_latitude ? " ({$x->check_in_latitude}, {$x->check_in_longitude})" : ''), $this->mapUrl($x->check_in_latitude, $x->check_in_longitude) ?: $this->mapUrl($x->check_out_latitude, $x->check_out_longitude), $x->result, $x->next_action, $x->status, $x->verification_status]];
    }

    private function inactive(Request $r): array
    {
        $days = max(1, min(365, $r->integer('inactive_days', 7)));
        $q = MarketingLead::query()->with(['marketing:id,name', 'perumahan:id,nama_perusahaan', 'source:id,nama_sumber'])->whereNotIn('stage', ['converted', 'lost'])->where(fn (Builder $q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<=', now()->subDays($days)))
            ->when($r->filled('status'), fn (Builder $q) => $q->where('stage', $r->string('status')))->orderBy('last_activity_at');

        return [['Lead', 'Marketing', 'Perumahan', 'Sumber', 'Tahap', 'Aktivitas Terakhir', 'Hari Tidak Aktif', 'Next Action'], $q, fn ($x) => [$x->name, $x->marketing?->name ?: 'Belum ditugaskan', $x->perumahan?->nama_perusahaan, $x->source?->nama_sumber, $x->stage, $x->last_activity_at?->format('d/m/Y H:i') ?: 'Belum pernah', ($x->last_activity_at ?? $x->created_at)?->diffInDays(now()), $x->next_action_at?->format('d/m/Y H:i')]];
    }

    private function pipeline(Request $r, Carbon $from, Carbon $to): array
    {
        $q = MarketingLead::query()->with(['marketing:id,name', 'perumahan:id,nama_perusahaan'])->whereBetween('created_at', [$from, $to])->when($r->filled('status'), fn (Builder $q) => $q->where('stage', $r->string('status')))->latest();

        return [['Lead', 'Marketing', 'Perumahan', 'Minat', 'Tahap Pipeline', 'Lead Masuk', 'Respons Pertama', 'Aktivitas Terakhir', 'Next Action'], $q, fn ($x) => [$x->name, $x->marketing?->name, $x->perumahan?->nama_perusahaan, $x->interest_level, $x->stage, $x->created_at?->format('d/m/Y H:i'), $x->first_contacted_at?->format('d/m/Y H:i'), $x->last_activity_at?->format('d/m/Y H:i'), $x->next_action_at?->format('d/m/Y H:i')]];
    }

    private function conversion(Request $r, Carbon $from, Carbon $to): array
    {
        $housingId = $r->integer('perumahan_id');
        $base = fn (Builder $q) => $q->whereBetween('created_at', [$from, $to])->when($housingId, fn (Builder $q) => $q->where('perumahan_id', $housingId));
        $q = User::query()->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['marketing', 'area_marketing']))->withCount([
            'marketingLeads as leads_count' => $base,
            'marketingLeads as contacted_count' => fn (Builder $q) => $base($q)->whereNotNull('first_contacted_at'),
            'marketingLeads as qualified_count' => fn (Builder $q) => $base($q)->whereIn('stage', ['qualified', 'converted']),
            'marketingLeads as converted_count' => fn (Builder $q) => $base($q)->where('stage', 'converted')->whereNotNull('converted_costumer_id'),
            'marketingLeads as lost_count' => fn (Builder $q) => $base($q)->where('stage', 'lost'),
        ])->orderBy('name');
        $ratio = fn ($part, $whole) => $whole ? round($part / $whole * 100, 1).'%' : '0%';

        return [['Marketing', 'Lead', 'Dihubungi', 'Qualified', 'Menjadi Customer', 'Lost', 'Lead ke Hubungi', 'Lead ke Customer'], $q, fn ($x) => [$x->name, $x->leads_count, $x->contacted_count, $x->qualified_count, $x->converted_count, $x->lost_count, $ratio($x->contacted_count, $x->leads_count), $ratio($x->converted_count, $x->leads_count)]];
    }

    private function targets(Request $r, Carbon $from, Carbon $to): array
    {
        $q = MarketingTarget::query()->with(['user:id,name', 'perumahan:id,nama_perusahaan'])->where(fn (Builder $q) => $q->where('tahun', '>', $from->year)->orWhere(fn (Builder $q) => $q->where('tahun', $from->year)->where('bulan', '>=', $from->month)))->where(fn (Builder $q) => $q->where('tahun', '<', $to->year)->orWhere(fn (Builder $q) => $q->where('tahun', $to->year)->where('bulan', '<=', $to->month)))->latest('tahun')->latest('bulan');

        return [['Marketing', 'Perumahan', 'Periode', 'Lead T/R', 'Follow-up T/R', 'Kunjungan T/R', 'Reservasi T/R', 'SPR T/R', 'Closing T/R', 'Nilai T/R', 'Capaian'], $q, fn ($x) => $this->targetRow($x)];
    }

    private function cancellations(Request $r, Carbon $from, Carbon $to): array
    {
        $q = MarketingLead::query()->with(['marketing:id,name', 'perumahan:id,nama_perusahaan'])->where('stage', 'lost')->whereBetween('updated_at', [$from, $to])->latest('updated_at');

        return [['Lead', 'Marketing', 'Perumahan', 'Tahap Akhir', 'Alasan', 'Tanggal', 'Metode Pembayaran'], $q, fn ($x) => [$x->name, $x->marketing?->name, $x->perumahan?->nama_perusahaan, $x->stage, $x->lost_reason ?: $x->qualification_note, $x->updated_at?->format('d/m/Y H:i'), $x->preferred_payment_method]];
    }

    private function performance(Request $r, Carbon $from, Carbon $to): array
    {
        $q = MarketingEvaluation::query()->with(['marketing:id,name', 'perumahan:id,nama_perusahaan'])->where('period_end', '>=', $from)->where('period_start', '<=', $to)->latest('period_end');

        return [['Nomor', 'Marketing', 'Perumahan', 'Periode', 'Nilai', 'Rating', 'Status Approval', 'Catatan Manager', 'Rencana Coaching'], $q, fn ($x) => [$x->evaluation_no, $x->marketing?->name, $x->perumahan?->nama_perusahaan ?: 'Semua', $x->period_start?->format('d/m/Y').' - '.$x->period_end?->format('d/m/Y'), $x->total_score, $x->rating, $x->record_status, $x->manager_note, $x->coaching_plan]];
    }

    private function scopeMarketing(Builder $query, Request $request, string $type): void
    {
        $column = match ($type) {
            'activities', 'follow-ups', 'targets' => 'user_id', 'visits', 'performance', 'inactive-customers', 'pipeline', 'cancellations' => 'marketing_id', 'conversion' => 'id', default => 'marketing_id'
        };
        if ($request->filled('marketing_id')) {
            $query->where($column, $request->integer('marketing_id'));
        }
        if (! $request->user()?->hasRole('super_admin') && ! $request->user()?->can('marketing.activity.view-all')) {
            $query->where($column, $request->user()->id);
        }
        if ($request->filled('perumahan_id') && ! in_array($type, ['activities', 'follow-ups', 'conversion'], true)) {
            $query->where('perumahan_id', $request->integer('perumahan_id'));
        }
        if ($request->filled('perumahan_id') && in_array($type, ['activities', 'follow-ups'], true)) {
            $query->whereHas($type === 'follow-ups' ? 'lead' : 'costumer', fn (Builder $q) => $q->where('perumahan_id', $request->integer('perumahan_id')));
        }
    }

    private function scopeCustomerFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('lead_source_id'), fn (Builder $q) => $q->where('marketing_lead_source_id', $request->integer('lead_source_id')))
            ->when($request->filled('campaign_id'), fn (Builder $q) => $q->where('marketing_campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('payment_plan'), fn (Builder $q) => $q->where('preferred_payment_method', $request->string('payment_plan')))
            ->when($request->filled('interest_level'), fn (Builder $q) => $q->where('interest_level', $request->string('interest_level')))
            ->when($request->filled('unit_id'), fn (Builder $q) => $q->whereHas('unitInterests', fn (Builder $interest) => $interest->where('detail_rumah_id', $request->integer('unit_id'))))
            ->when($request->boolean('has_unit_interest'), fn (Builder $q) => $q->whereHas('unitInterests'));
    }

    private function period(Request $request): array
    {
        return [Carbon::parse($request->input('date_from') ?: now()->startOfMonth())->startOfDay(), Carbon::parse($request->input('date_to') ?: now()->endOfMonth())->endOfDay()];
    }

    private function mapUrl(mixed $latitude, mixed $longitude): ?string
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        return "https://www.google.com/maps?q={$latitude},{$longitude}";
    }

    private function targetMetrics(Collection $targets): Collection
    {
        if ($targets->isEmpty()) {
            return collect();
        }

        $users = $targets->pluck('user_id')->filter()->unique()->values();
        $from = $targets->map(fn (MarketingTarget $target) => Carbon::create($target->tahun, $target->bulan)->startOfMonth())->min();
        $to = $targets->map(fn (MarketingTarget $target) => Carbon::create($target->tahun, $target->bulan)->endOfMonth())->max();

        $leads = MarketingLead::query()
            ->whereIn('marketing_id', $users)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('marketing_id as user_id, perumahan_id, YEAR(created_at) as year_no, MONTH(created_at) as month_no, COUNT(*) as total')
            ->groupBy('marketing_id', 'perumahan_id', 'year_no', 'month_no')
            ->get();
        $followUps = CostumerFollowUp::query()
            ->join('marketing_leads', 'marketing_leads.id', '=', 'costumer_follow_ups.marketing_lead_id')
            ->whereIn('costumer_follow_ups.user_id', $users)
            ->whereBetween('costumer_follow_ups.followed_up_at', [$from, $to])
            ->selectRaw('costumer_follow_ups.user_id, marketing_leads.perumahan_id, YEAR(costumer_follow_ups.followed_up_at) as year_no, MONTH(costumer_follow_ups.followed_up_at) as month_no, COUNT(*) as total')
            ->groupBy('costumer_follow_ups.user_id', 'marketing_leads.perumahan_id', 'year_no', 'month_no')
            ->get();
        $visits = MarketingVisit::query()
            ->whereIn('marketing_id', $users)
            ->where('status', 'completed')
            ->whereBetween('finished_at', [$from, $to])
            ->selectRaw('marketing_id as user_id, perumahan_id, YEAR(finished_at) as year_no, MONTH(finished_at) as month_no, COUNT(*) as total')
            ->groupBy('marketing_id', 'perumahan_id', 'year_no', 'month_no')
            ->get();
        $reservations = HousingReservation::query()
            ->join('costumers', 'costumers.id', '=', 'housing_reservations.costumer_id')
            ->whereIn('costumers.assigned_marketing_id', $users)
            ->whereBetween('housing_reservations.reserved_at', [$from, $to])
            ->selectRaw('costumers.assigned_marketing_id as user_id, costumers.perumahan_id, YEAR(housing_reservations.reserved_at) as year_no, MONTH(housing_reservations.reserved_at) as month_no, COUNT(*) as total')
            ->groupBy('costumers.assigned_marketing_id', 'costumers.perumahan_id', 'year_no', 'month_no')
            ->get();
        $sprs = Spr::query()
            ->join('costumers', 'costumers.id', '=', 'sprs.costumer_id')
            ->whereIn('costumers.assigned_marketing_id', $users)
            ->whereBetween('sprs.tanggal_spr', [$from, $to])
            ->selectRaw('costumers.assigned_marketing_id as user_id, costumers.perumahan_id, YEAR(sprs.tanggal_spr) as year_no, MONTH(sprs.tanggal_spr) as month_no, COUNT(*) as total')
            ->groupBy('costumers.assigned_marketing_id', 'costumers.perumahan_id', 'year_no', 'month_no')
            ->get();
        $sales = SalesTransaction::query()
            ->whereIn('marketing_user_id', $users)
            ->whereBetween('closed_at', [$from, $to])
            ->selectRaw('marketing_user_id as user_id, perumahan_id, YEAR(closed_at) as year_no, MONTH(closed_at) as month_no, COUNT(*) as total, COALESCE(SUM(sale_price_snapshot), 0) as value_total')
            ->groupBy('marketing_user_id', 'perumahan_id', 'year_no', 'month_no')
            ->get();

        $indexes = [
            'lead' => $this->metricIndex($leads),
            'follow' => $this->metricIndex($followUps),
            'visit' => $this->metricIndex($visits),
            'reservation' => $this->metricIndex($reservations),
            'spr' => $this->metricIndex($sprs),
            'sales' => $this->metricIndex($sales),
        ];

        return $targets->mapWithKeys(function (MarketingTarget $target) use ($indexes): array {
            $key = $this->metricKey($target->user_id, $target->tahun, $target->bulan, $target->perumahan_id);
            $sales = $indexes['sales']->get($key, ['total' => 0, 'value' => 0]);

            return [$target->id => [
                'lead' => $indexes['lead']->get($key, ['total' => 0])['total'],
                'follow' => $indexes['follow']->get($key, ['total' => 0])['total'],
                'visit' => $indexes['visit']->get($key, ['total' => 0])['total'],
                'reservation' => $indexes['reservation']->get($key, ['total' => 0])['total'],
                'spr' => $indexes['spr']->get($key, ['total' => 0])['total'],
                'closing' => $sales['total'],
                'value' => $sales['value'],
            ]];
        });
    }

    private function metricIndex(Collection $rows): Collection
    {
        $index = collect();

        foreach ($rows as $row) {
            $housingIds = $row->perumahan_id ? [$row->perumahan_id, null] : [null];
            foreach ($housingIds as $housingId) {
                $key = $this->metricKey($row->user_id, $row->year_no, $row->month_no, $housingId);
                $current = $index->get($key, ['total' => 0, 'value' => 0]);
                $index->put($key, [
                    'total' => $current['total'] + (int) $row->total,
                    'value' => $current['value'] + (float) ($row->value_total ?? 0),
                ]);
            }
        }

        return $index;
    }

    private function metricKey(mixed $userId, mixed $year, mixed $month, mixed $housingId): string
    {
        return implode('|', [(int) $userId, (int) $year, (int) $month, $housingId ? (int) $housingId : 'all']);
    }

    private function targetRow(MarketingTarget $target, array $actual = []): array
    {
        $lead = (int) ($actual['lead'] ?? 0);
        $follow = (int) ($actual['follow'] ?? 0);
        $visit = (int) ($actual['visit'] ?? 0);
        $reservation = (int) ($actual['reservation'] ?? 0);
        $spr = (int) ($actual['spr'] ?? 0);
        $closing = (int) ($actual['closing'] ?? 0);
        $value = (float) ($actual['value'] ?? 0);
        $scores = collect([[$lead, $target->target_lead], [$follow, $target->target_follow_up], [$visit, $target->target_visit], [$reservation, $target->target_reservation], [$spr, $target->target_spr], [$closing, $target->target_closing], [$value, $target->target_nilai_penjualan]])->filter(fn ($x) => $x[1] > 0)->map(fn ($x) => min(100, $x[0] / $x[1] * 100));

        return [$target->user?->name, $target->perumahan?->nama_perusahaan ?: 'Semua', sprintf('%02d/%d', $target->bulan, $target->tahun), "$target->target_lead / $lead", "$target->target_follow_up / $follow", "$target->target_visit / $visit", "$target->target_reservation / $reservation", "$target->target_spr / $spr", "$target->target_closing / $closing", 'Rp '.number_format($target->target_nilai_penjualan, 0, ',', '.').' / Rp '.number_format($value, 0, ',', '.'), round($scores->avg() ?? 0, 1).'%'];
    }

    private function unitInterestSummary(Costumer $customer): string
    {
        $items = $customer->unitInterests->map(function ($interest): string {
            $unit = $interest->unit
                ? trim(($interest->unit->kode_nlok ? 'Blok '.$interest->unit->kode_nlok.' ' : '').($interest->unit->nomor_rumah ? 'No. '.$interest->unit->nomor_rumah : '').($interest->unit->tipe_rumah ? ' - '.$interest->unit->tipe_rumah : ''))
                : null;

            return trim(($interest->perumahan?->nama_perusahaan ? $interest->perumahan->nama_perusahaan.' - ' : '').($unit ?: 'Unit belum spesifik'));
        })->filter()->unique()->take(3)->values();

        return $items->isEmpty() ? '-' : $items->join('; ');
    }
}
