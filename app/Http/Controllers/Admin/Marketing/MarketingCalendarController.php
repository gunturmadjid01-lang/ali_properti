<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CostumerFollowUp;
use App\Models\MarketingActionPlan;
use App\Models\MarketingReminder;
use App\Models\MarketingSurveySchedule;
use App\Models\MarketingVisit;
use App\Models\Perumahan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingCalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-calendar.view'), 403);
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'marketing_id' => ['nullable', 'integer', 'exists:users,id'], 'perumahan_id' => ['nullable', 'integer', 'exists:perumahans,id'], 'types' => ['nullable', 'array'], 'types.*' => ['in:visit,survey,follow_up,reminder,action_plan']]);
        $month = Carbon::createFromFormat('Y-m', (string) $request->query('month', now()->format('Y-m')))->startOfMonth();
        $from = $month->copy()->startOfWeek()->startOfDay();
        $to = $month->copy()->endOfMonth()->endOfWeek()->endOfDay();
        $canViewAll = $request->user()?->hasAnyRole(['super_admin', 'admin_sales', 'manager', 'owner', 'supervisor_marketing']) || $request->user()?->can('marketing.activity.view-all');
        $marketingId = $canViewAll ? $request->integer('marketing_id') : (int) $request->user()?->id;
        $perumahanId = $request->integer('perumahan_id');
        $types = array_filter((array) $request->query('types', []));
        $events = collect();

        if (! $types || in_array('visit', $types, true)) {
            $events->push(...MarketingVisit::query()->with(['costumer:id,nama', 'marketing:id,name', 'perumahan:id,nama_perusahaan'])->whereBetween('planned_at', [$from, $to])->when($marketingId, fn (Builder $q) => $q->where('marketing_id', $marketingId))->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->get()->map(fn ($x) => $this->event('visit', $x->id, $x->planned_at, 'Kunjungan: '.($x->costumer?->nama ?: $x->contact_name), $x->marketing?->name, $x->perumahan?->nama_perusahaan, $x->status, $x->objective, route('admin.marketing.crm.show', ['resource' => 'visits', 'id' => $x->id], false))));
        }
        if (! $types || in_array('survey', $types, true)) {
            $events->push(...MarketingSurveySchedule::query()->with(['costumer:id,nama', 'marketing:id,name', 'perumahan:id,nama_perusahaan'])->whereBetween('tanggal_survey', [$from, $to])->when($marketingId, fn (Builder $q) => $q->where('marketing_id', $marketingId))->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->get()->map(fn ($x) => $this->event('survey', $x->id, $x->tanggal_survey, 'Survey: '.$x->costumer?->nama, $x->marketing?->name, $x->perumahan?->nama_perusahaan, $x->status, $x->catatan, route('admin.marketing.jadwal-survey.edit', $x->id, false))));
        }
        if (! $types || in_array('follow_up', $types, true)) {
            $events->push(...CostumerFollowUp::query()->with(['lead:id,name,perumahan_id', 'lead.perumahan:id,nama_perusahaan', 'user:id,name'])->whereNotNull('rencana_follow_up_at')->whereBetween('rencana_follow_up_at', [$from, $to])->when($marketingId, fn (Builder $q) => $q->where('user_id', $marketingId))->when($perumahanId, fn (Builder $q) => $q->whereHas('lead', fn (Builder $q) => $q->where('perumahan_id', $perumahanId)))->get()->map(fn ($x) => $this->event('follow_up', $x->id, $x->rencana_follow_up_at, 'Follow-up: '.$x->lead?->name, $x->user?->name, $x->lead?->perumahan?->nama_perusahaan, $x->status, $x->next_action ?: $x->catatan, route('admin.marketing.jejak-follow-up.show', $x->id, false))));
        }
        if (! $types || in_array('reminder', $types, true)) {
            $events->push(...MarketingReminder::query()->with(['costumer:id,nama', 'user:id,name'])->whereBetween('remind_at', [$from, $to])->when($marketingId, fn (Builder $q) => $q->where('user_id', $marketingId))->get()->map(fn ($x) => $this->event('reminder', $x->id, $x->remind_at, $x->judul, $x->user?->name, null, $x->status, $x->catatan, route('admin.marketing.operasional.show', ['section' => 'reminder'], false))));
        }
        if (! $types || in_array('action_plan', $types, true)) {
            $events->push(...MarketingActionPlan::query()->with(['costumer:id,nama', 'marketing:id,name', 'perumahan:id,nama_perusahaan'])->whereBetween('start_at', [$from, $to])->when($marketingId, fn (Builder $q) => $q->where('marketing_id', $marketingId))->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->get()->map(fn ($x) => $this->event('action_plan', $x->id, $x->start_at, $x->title, $x->marketing?->name, $x->perumahan?->nama_perusahaan, $x->status, $x->objective, route('admin.marketing.crm.show', ['resource' => 'action-plans', 'id' => $x->id], false), $x->due_at)));
        }

        return Inertia::render('Admin/Marketing/Calendar/Index', [
            'title' => 'Kalender Kegiatan Marketing', 'month' => $month->format('Y-m'), 'calendarStart' => $from->toDateString(), 'calendarEnd' => $to->toDateString(),
            'events' => $events->sortBy('start')->values(), 'filters' => ['marketing_id' => $marketingId ?: '', 'perumahan_id' => $perumahanId ?: '', 'types' => $types], 'canViewAll' => $canViewAll,
            'options' => ['marketings' => $canViewAll ? User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['marketing', 'area_marketing']))->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->name]) : [], 'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_perusahaan])],
        ]);
    }

    private function event(string $type, int $id, $start, string $title, ?string $marketing, ?string $housing, ?string $status, ?string $note, string $url, $end = null): array
    {
        return ['key' => $type.'-'.$id, 'type' => $type, 'start' => $start?->toISOString(), 'end' => $end?->toISOString(), 'date' => $start?->toDateString(), 'time' => $start?->format('H:i'), 'title' => $title, 'marketing' => $marketing, 'housing' => $housing, 'status' => $status, 'note' => $note, 'url' => $url];
    }
}
