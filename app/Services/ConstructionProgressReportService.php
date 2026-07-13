<?php

namespace App\Services;

use App\Models\ContractorOpname;
use App\Models\DetailRumah;
use App\Models\ProgressPembangunan;
use App\Models\SpkKontraktor;
use App\Models\SpkKontraktorPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConstructionProgressReportService
{
    public function build(Collection $units, Carbon $start, Carbon $end): array
    {
        if ($units->isEmpty()) {
            return $this->emptyResult($start, $end);
        }

        $unitReports = $units->map(fn (DetailRumah $unit) => $this->unitReport($unit, $start, $end));
        $rowKeys = $unitReports
            ->flatMap(fn (array $report) => array_keys($report['items']))
            ->unique()
            ->values();

        $rows = $rowKeys->map(function (string $key, int $index) use ($unitReports) {
            $source = $unitReports->first(fn (array $report) => isset($report['items'][$key]))['items'][$key];

            return [
                'no' => $index + 1,
                'key' => $key,
                'stage' => $source['stage'],
                'work' => $source['work'],
                'amount' => $source['amount'],
                'weight' => $source['weight'],
                'units' => $unitReports->map(function (array $report) use ($key) {
                    $item = $report['items'][$key] ?? null;

                    return [
                        'cumulative' => (float) ($item['cumulative'] ?? 0),
                        'weighted' => (float) ($item['weighted'] ?? 0),
                        'period' => (float) ($item['period'] ?? 0),
                        'period_weighted' => (float) ($item['period_weighted'] ?? 0),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $start->isSameDay($end)
                    ? $start->translatedFormat('d F Y')
                    : $start->translatedFormat('d F Y').' - '.$end->translatedFormat('d F Y'),
            ],
            'project' => $units->first()?->perumahan?->nama_perusahaan ?? '-',
            'building_type' => $units->first()?->tipe_rumah ?? '-',
            'rows' => $rows,
            'units' => $unitReports->map(fn (array $report) => $report['summary'])->values()->all(),
        ];
    }

    private function unitReport(DetailRumah $unit, Carbon $start, Carbon $end): array
    {
        $spks = SpkKontraktor::query()
            ->with(['items' => fn ($query) => $query->orderBy('urutan')->orderBy('id')])
            ->where('detail_rumah_id', $unit->id)
            ->where('status', '!=', 'batal')
            ->get();
        $spkIds = $spks->pluck('id');

        $progress = ProgressPembangunan::query()
            ->with('siteSchedule:id,spk_kontraktor_id')
            ->where('detail_rumah_id', $unit->id)
            ->where('approval_status', 'approved')
            ->whereDate('tanggal', '<=', $end)
            ->whereHas('siteSchedule', fn ($query) => $query->whereIn('spk_kontraktor_id', $spkIds))
            ->get();

        $progressByItem = $progress->groupBy(fn (ProgressPembangunan $row) => $this->progressKey($row));
        $totalContract = max(1, (float) $spks->flatMap->items->sum('total'));
        $items = [];

        foreach ($spks as $spk) {
            foreach ($spk->items as $item) {
                $displayKey = $this->displayKey($item->nama_tahap_pekerjaan, $item->nama_pekerjaan);
                $progressKey = $spk->id.'|'.$displayKey;
                $itemProgress = $progressByItem->get($progressKey, collect());
                $cumulative = min(100, (float) $itemProgress->sum('persentase'));
                $previous = min(100, (float) $itemProgress->filter(fn ($row) => $row->tanggal->lt($start))->sum('persentase'));
                $period = max(0, $cumulative - $previous);
                $amount = (float) $item->total;
                $weight = ($amount / $totalContract) * 100;

                if (! isset($items[$displayKey])) {
                    $items[$displayKey] = [
                        'stage' => $item->nama_tahap_pekerjaan ?: 'Tanpa Tahap',
                        'work' => $item->nama_pekerjaan ?: '-',
                        'amount' => 0,
                        'weight' => 0,
                        '_cumulative_amount' => 0,
                        '_period_amount' => 0,
                    ];
                }

                $items[$displayKey]['amount'] += $amount;
                $items[$displayKey]['weight'] += $weight;
                $items[$displayKey]['_cumulative_amount'] += $amount * ($cumulative / 100);
                $items[$displayKey]['_period_amount'] += $amount * ($period / 100);
            }
        }

        foreach ($items as &$item) {
            $amount = max(1, (float) $item['amount']);
            $item['cumulative'] = min(100, ($item['_cumulative_amount'] / $amount) * 100);
            $item['period'] = min(100, ($item['_period_amount'] / $amount) * 100);
            $item['weighted'] = ($item['cumulative'] / 100) * $item['weight'];
            $item['period_weighted'] = ($item['period'] / 100) * $item['weight'];
            unset($item['_cumulative_amount'], $item['_period_amount']);
        }
        unset($item);

        $linkedSpkIds = $progress->pluck('siteSchedule.spk_kontraktor_id')->filter()->unique()->values();
        $progressIds = $progress->pluck('id');
        $payments = SpkKontraktorPayment::query()
            ->whereIn('spk_kontraktor_id', $linkedSpkIds)
            ->where('status', 'dana_cair')
            ->get();
        $paymentDate = fn (SpkKontraktorPayment $payment) => $payment->tanggal_pembayaran
            ?? $payment->paid_at?->toDateString();
        $paymentBefore = $payments->filter(function ($payment) use ($paymentDate, $start) {
            $date = $paymentDate($payment);
            return $date && Carbon::parse($date)->lt($start);
        })->sum('nominal');
        $paymentPeriod = $payments->filter(function ($payment) use ($paymentDate, $start, $end) {
            $date = $paymentDate($payment);
            return $date && Carbon::parse($date)->betweenIncluded($start, $end);
        })->sum('nominal');

        $opnames = ContractorOpname::query()
            ->whereIn('spk_kontraktor_id', $linkedSpkIds)
            ->whereIn('progress_pembangunan_id', $progressIds)
            ->where('approval_status', 'approved')
            ->whereDate('tanggal', '<=', $end)
            ->get();
        $opnameBefore = (float) $opnames->filter(fn ($row) => $row->tanggal->lt($start))->sum('nilai_disetujui');
        $opnamePeriod = (float) $opnames->filter(fn ($row) => $row->tanggal->betweenIncluded($start, $end))->sum('nilai_disetujui');

        return [
            'items' => $items,
            'summary' => [
                'id' => (string) $unit->id,
                'label' => trim('Blok '.($unit->kode_nlok ?? '').' No. '.($unit->nomor_rumah ?? '')),
                'block' => $unit->kode_nlok,
                'number' => $unit->nomor_rumah,
                'contract_total' => (float) $spks->sum('nilai_kontrak'),
                'cumulative_weight' => (float) collect($items)->sum('weighted'),
                'previous_weight' => (float) collect($items)->sum('weighted') - (float) collect($items)->sum('period_weighted'),
                'period_weight' => (float) collect($items)->sum('period_weighted'),
                'opname_previous' => $opnameBefore,
                'opname_period' => $opnamePeriod,
                'opname_total' => $opnameBefore + $opnamePeriod,
                'payment_previous' => (float) $paymentBefore,
                'payment_period' => (float) $paymentPeriod,
                'payment_total' => (float) $paymentBefore + (float) $paymentPeriod,
            ],
        ];
    }

    private function progressKey(ProgressPembangunan $progress): string
    {
        return ($progress->siteSchedule?->spk_kontraktor_id ?? 0).'|'.$this->displayKey(
            $progress->schedule_stage_name,
            $progress->schedule_item_name ?: $progress->nama_progress,
        );
    }

    private function displayKey(?string $stage, ?string $work): string
    {
        return Str::of(($stage ?: 'Tanpa Tahap').'|'.($work ?: '-'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();
    }

    private function emptyResult(Carbon $start, Carbon $end): array
    {
        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => '-'],
            'project' => '-',
            'building_type' => '-',
            'rows' => [],
            'units' => [],
        ];
    }
}
