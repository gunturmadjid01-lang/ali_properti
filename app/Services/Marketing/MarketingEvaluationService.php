<?php

namespace App\Services\Marketing;

use App\Models\Costumer;
use App\Models\CostumerFollowUp;
use App\Models\CustomerDocumentChecklist;
use App\Models\HousingReservation;
use App\Models\MarketingEvaluation;
use App\Models\MarketingLeadActivity;
use App\Models\MarketingLead;
use App\Models\MarketingScoreSetting;
use App\Models\MarketingTarget;
use App\Models\MarketingVisit;
use App\Models\Spr;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketingEvaluationService
{
    public function calculate(int $marketingId, CarbonInterface $from, CarbonInterface $to, ?int $perumahanId = null): array
    {
        $leads = MarketingLead::query()->where('marketing_id', $marketingId)->whereBetween('created_at', [$from, $to])->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->get();
        $customerIds = MarketingLead::query()->where('marketing_id', $marketingId)->whereNotNull('converted_costumer_id')->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->pluck('converted_costumer_id');
        $followUps = CostumerFollowUp::query()->where('user_id', $marketingId)->whereBetween(DB::raw('COALESCE(followed_up_at, costumer_follow_ups.created_at)'), [$from, $to])->when($perumahanId, fn (Builder $q) => $q->whereHas('lead', fn (Builder $c) => $c->where('perumahan_id', $perumahanId)))->get();
        $visits = MarketingVisit::query()->where('marketing_id', $marketingId)->whereBetween('planned_at', [$from, $to])->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->get();
        $reservations = HousingReservation::query()->whereIn('costumer_id', $customerIds)->whereBetween('reserved_at', [$from, $to])->count();
        $spr = Spr::query()->where('created_by', $marketingId)->whereBetween('tanggal_spr', [$from, $to])->count();
        $closing = Spr::query()->where('created_by', $marketingId)->whereBetween('tanggal_spr', [$from, $to])->where('status', Spr::STATUS_DISETUJUI)->count();
        $target = MarketingTarget::query()->where('user_id', $marketingId)->where('tahun', $from->year)->where('bulan', $from->month)->when($perumahanId, fn (Builder $q) => $q->where('perumahan_id', $perumahanId))->first();
        $documents = CustomerDocumentChecklist::query()->whereIn('costumer_id', $customerIds)->whereBetween('updated_at', [$from, $to])->get();
        $progress = $leads->whereIn('stage', ['qualified', 'converted'])->count();

        $responded = $leads->whereNotNull('first_contacted_at');
        $responseOnTime = $responded->filter(fn (MarketingLead $lead) => $lead->first_contacted_at->lte($lead->first_response_due_at ?? $lead->created_at->copy()->addHours(2)))->count();
        $completeFollowUps = $followUps->filter(fn (CostumerFollowUp $row) => filled($row->catatan) && filled($row->result_code ?? $row->status) && (filled($row->next_action) || $row->status === 'dibatalkan'))->count();
        $completedVisits = $visits->where('status', 'completed');
        $verifiedVisits = $completedVisits->where('verification_status', 'verified')->count();
        $metrics = [
            'lead_response_speed' => [$this->ratio($responseOnTime, max(1, $leads->count())), ['leads' => $leads->count(), 'responded_on_time' => $responseOnTime]],
            'follow_up_timeliness' => [$this->ratio($followUps->where('status', 'selesai')->count(), max(1, $followUps->count())), ['total' => $followUps->count(), 'completed' => $followUps->where('status', 'selesai')->count()]],
            'follow_up_quality' => [$this->ratio($completeFollowUps, max(1, $followUps->count())), ['total' => $followUps->count(), 'complete' => $completeFollowUps]],
            'visit_execution' => [$this->ratio($completedVisits->count(), max(1, $visits->whereNotIn('status', ['cancelled'])->count())), ['planned' => $visits->count(), 'completed' => $completedVisits->count()]],
            'visit_report_quality' => [$this->ratio($verifiedVisits, max(1, $completedVisits->count())), ['completed' => $completedVisits->count(), 'verified' => $verifiedVisits]],
            'customer_progress' => [$this->ratio($progress, max(1, $leads->count())), ['lead_count' => $leads->count(), 'progress_events' => $progress]],
            'reservation_spr' => [$this->targetRatio($reservations + $spr, (int) (($target?->target_reservation ?? 0) + ($target?->target_spr ?? 0))), ['reservations' => $reservations, 'spr' => $spr, 'target' => (int) (($target?->target_reservation ?? 0) + ($target?->target_spr ?? 0))]],
            'closing' => [$this->targetRatio($closing, (int) ($target?->target_closing ?? 0)), ['closing' => $closing, 'target' => (int) ($target?->target_closing ?? 0)]],
            'administration' => [$this->ratio($documents->where('validation_status', 'complete')->count(), max(1, $documents->count())), ['checked' => $documents->count(), 'complete' => $documents->where('validation_status', 'complete')->count()]],
        ];

        $settings = MarketingScoreSetting::query()->where('is_active', true)->where('record_status', 'locked')->orderBy('id')->get();
        $details = $settings->map(function (MarketingScoreSetting $setting) use ($metrics): array {
            [$achievement, $evidence] = $metrics[$setting->metric_key] ?? [0, []];
            return ['metric_key' => $setting->metric_key, 'label' => $setting->label, 'weight' => $setting->weight, 'achievement' => $achievement, 'score' => round($achievement * $setting->weight / 100, 2), 'evidence' => $evidence];
        });
        $weight = max(1, $settings->sum('weight'));
        $score = round($details->sum('score') * 100 / $weight, 2);

        return ['total_score' => $score, 'rating' => match (true) { $score >= 90 => 'sangat_baik', $score >= 75 => 'baik', $score >= 60 => 'cukup', default => 'perlu_pembinaan' }, 'details' => $details->all()];
    }

    public function recalculate(MarketingEvaluation $evaluation): MarketingEvaluation
    {
        $result = $this->calculate($evaluation->marketing_id, $evaluation->period_start->startOfDay(), $evaluation->period_end->endOfDay(), $evaluation->perumahan_id);
        DB::transaction(function () use ($evaluation, $result): void {
            $evaluation->forceFill(['total_score' => $result['total_score'], 'rating' => $result['rating']])->save();
            $evaluation->details()->delete();
            $evaluation->details()->createMany($result['details']);
        });
        return $evaluation->fresh(['details', 'marketing', 'perumahan']);
    }

    private function ratio(int|float $actual, int|float $base): float { return round(min(100, $actual / max(1, $base) * 100), 2); }
    private function targetRatio(int|float $actual, int|float $target): float { return $target > 0 ? $this->ratio($actual, $target) : ($actual > 0 ? 100 : 0); }
}
