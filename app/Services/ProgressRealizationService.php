<?php

namespace App\Services;

use App\Models\DetailRumah;
use App\Models\ProgressPembangunan;
use App\Models\SiteSchedule;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgressRealizationService
{
    public function recordFromSource(Model $source, array $payload): ?ProgressPembangunan
    {
        $percent = (float) ($payload['persentase'] ?? 0);
        if ($percent <= 0) {
            return null;
        }

        return DB::transaction(function () use ($source, $payload, $percent): ProgressPembangunan {
            $schedule = null;
            if (! empty($payload['site_schedule_id'])) {
                $schedule = SiteSchedule::query()->findOrFail($payload['site_schedule_id']);
            }

            $detailRumahId = $payload['detail_rumah_id'] ?? $schedule?->detail_rumah_id;
            $tahapanId = $payload['tahapan_pembangunan_id'] ?? $schedule?->tahapan_pembangunan_id;

            if (! $tahapanId) {
                throw ValidationException::withMessages([
                    'tahapan_pembangunan_id' => 'Tahapan wajib dipilih agar progress bisa dinaikkan.',
                ]);
            }

            $tahapan = TahapanPembangunan::query()->findOrFail($tahapanId);
            $existing = ProgressPembangunan::query()
                ->where('source_type', $source::class)
                ->where('source_id', $source->getKey())
                ->first();

            $this->ensureTahapanCapacity($detailRumahId, $tahapan->id, $percent, $existing?->id);

            $progress = ProgressPembangunan::query()->updateOrCreate(
                [
                    'source_type' => $source::class,
                    'source_id' => $source->getKey(),
                ],
                [
                    'detail_rumah_id' => $detailRumahId,
                    'tahapan_pembangunan_id' => $tahapan->id,
                    'site_schedule_id' => $schedule?->id,
                    'nama_progress' => $payload['nama_progress'] ?? $tahapan->nama_tahapan,
                    'tanggal' => $payload['tanggal'] ?? now()->toDateString(),
                    'tahapan' => $tahapan->bobot_persen,
                    'persentase' => $percent,
                    'persentase_total' => ($percent / 100) * (float) $tahapan->bobot_persen,
                    'keterangan' => $payload['keterangan'] ?? '-',
                    'foto' => $payload['foto'] ?? null,
                    'approval_status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'approved_note' => $payload['approved_note'] ?? 'Otomatis dari '.$this->sourceName($source),
                    'source_label' => $payload['source_label'] ?? $this->sourceName($source),
                    'users_id' => auth()->id() ?: ($source->created_by ?? 1),
                    'created_by' => $existing?->created_by ?? auth()->id(),
                    'updated_by' => auth()->id(),
                ],
            );

            $this->recalculateRumahProgress($progress->detailRumah);
            $this->syncSiteSchedules($progress);

            return $progress;
        });
    }

    public function recalculateRumahProgress(?DetailRumah $rumah): void
    {
        if (! $rumah) {
            return;
        }

        $progressTotal = ProgressPembangunan::query()
            ->where('detail_rumah_id', $rumah->id)
            ->where('approval_status', 'approved')
            ->sum('persentase_total');

        $rumah->update([
            'progress_terakhir' => min(100, $progressTotal),
            'status_pembangunan' => $progressTotal >= 100 ? 'selesai' : 'sedang_dibangun',
            'updated_by' => auth()->id(),
        ]);
    }

    public function syncSiteSchedules(ProgressPembangunan $progress): void
    {
        $approvedQuery = ProgressPembangunan::query()
            ->where('detail_rumah_id', $progress->detail_rumah_id)
            ->where('tahapan_pembangunan_id', $progress->tahapan_pembangunan_id)
            ->where('approval_status', 'approved');

        $scheduleQuery = SiteSchedule::query();

        if ($progress->site_schedule_id) {
            $approvedQuery->where('site_schedule_id', $progress->site_schedule_id);
            $scheduleQuery->whereKey($progress->site_schedule_id);
        } else {
            $approvedQuery->whereNull('site_schedule_id')->where('nama_progress', $progress->nama_progress);
            $scheduleQuery
                ->where('detail_rumah_id', $progress->detail_rumah_id)
                ->where('tahapan_pembangunan_id', $progress->tahapan_pembangunan_id)
                ->where('nama_pekerjaan', $progress->nama_progress);
        }

        $approvedPercent = (float) $approvedQuery->sum('persentase_total');

        $scheduleQuery->get()->each(function (SiteSchedule $schedule) use ($approvedPercent): void {
            $realisasi = min(100, $approvedPercent);
            $target = (float) ($schedule->target_progress ?? 100);

            $schedule->update([
                'realisasi_progress' => $realisasi,
                'status' => $this->scheduleStatus($schedule, $realisasi, $target),
                'updated_by' => auth()->id(),
            ]);
        });
    }

    public function ensureTahapanCapacity(int|string|null $detailRumahId, int|string $tahapanPembangunanId, float $incomingPercent, ?int $ignoreId = null): void
    {
        $tahapan = TahapanPembangunan::query()->findOrFail($tahapanPembangunanId);

        $currentApproved = ProgressPembangunan::query()
            ->when($detailRumahId === null, fn ($query) => $query->whereNull('detail_rumah_id'), fn ($query) => $query->where('detail_rumah_id', $detailRumahId))
            ->where('tahapan_pembangunan_id', $tahapanPembangunanId)
            ->where('approval_status', 'approved')
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('persentase');

        $nextTotal = (float) $currentApproved + $incomingPercent;
        if ($nextTotal > 100) {
            throw ValidationException::withMessages([
                'persentase' => 'Total progress tahapan '.$tahapan->nama_tahapan.' tidak boleh melebihi 100%. Sisa tersedia '.number_format(max(0, 100 - (float) $currentApproved), 2, ',', '.').'%.',
            ]);
        }
    }

    protected function scheduleStatus(SiteSchedule $schedule, float $realisasi, float $target): string
    {
        if ($realisasi >= $target) {
            return 'selesai';
        }

        if (($schedule->status ?? null) === 'tertahan') {
            return 'tertahan';
        }

        if ($schedule->tanggal_target?->isPast()) {
            return 'terlambat';
        }

        return $realisasi > 0 ? 'berjalan' : 'direncanakan';
    }

    protected function sourceName(Model $source): string
    {
        return class_basename($source).' #'.$source->getKey();
    }
}
