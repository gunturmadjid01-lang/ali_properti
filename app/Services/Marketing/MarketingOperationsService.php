<?php

namespace App\Services\Marketing;

use App\Models\CostumerFollowUp;
use App\Models\DetailRumah;
use App\Models\KprStageHistory;
use App\Models\KprSubmission;
use App\Models\MarketingReminder;
use App\Models\MarketingSurveySchedule;
use App\Models\Spr;
use App\Models\SprBillingSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingOperationsService
{
    public function syncBillingSchedules(Spr $spr): void
    {
        $spr->loadMissing('payments');

        DB::transaction(function () use ($spr): void {
            $this->upsertSchedule($spr, 'booking_fee', null, $spr->tanggal_pembayaran_booking_fee ?? $spr->tanggal_spr, (float) $spr->booking_fee);

            $dpParts = max(1, (int) ($spr->uang_muka_jumlah_pembayaran ?: 1));
            $dpNominal = $dpParts > 0 ? (float) $spr->uang_muka / $dpParts : 0;
            $dpDate = Carbon::parse($spr->tanggal_jatuh_tempo_dp ?? $spr->tanggal_spr ?? now());
            for ($i = 1; $i <= $dpParts; $i++) {
                $this->upsertSchedule($spr, 'uang_muka', $i, $dpDate->copy()->addMonths($i - 1), $dpNominal);
            }

            if ($spr->metode_pembayaran === 'bertahap') {
                $parts = max(1, (int) ($spr->jumlah_termin ?: 1));
                $nominal = (float) ($spr->nominal_termin ?: (($spr->nilai_pengajuan_akhir ?: $spr->harga_jual) / $parts));
                $date = Carbon::parse($spr->tanggal_jatuh_tempo_termin ?? $spr->tanggal_jatuh_tempo_angsuran ?? $spr->tanggal_spr ?? now());
                for ($i = 1; $i <= $parts; $i++) {
                    $this->upsertSchedule($spr, 'termin', $i, $date->copy()->addMonths($i - 1), $nominal);
                }
            }

            $this->allocatePayments($spr);
        });
    }

    public function syncAutomaticReminders(?int $userId = null): void
    {
        CostumerFollowUp::query()
            ->whereNotNull('rencana_follow_up_at')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->get()
            ->each(function (CostumerFollowUp $row): void {
                $existing = MarketingReminder::query()
                    ->where('source_type', CostumerFollowUp::class)
                    ->where('source_id', $row->id)
                    ->first();

                MarketingReminder::query()->updateOrCreate(
                    ['source_type' => CostumerFollowUp::class, 'source_id' => $row->id],
                    [
                        'costumer_id' => $row->costumer_id,
                        'user_id' => $row->user_id,
                        'jenis' => 'follow_up',
                        'judul' => 'Follow up customer berikutnya',
                        'remind_at' => Carbon::parse($row->rencana_follow_up_at)->startOfDay()->addHours(9),
                        'status' => $existing?->status ?? 'menunggu',
                        'catatan' => $row->catatan,
                    ],
                );
            });

        MarketingSurveySchedule::query()
            ->whereNotNull('tanggal_survey')
            ->when($userId, fn ($query) => $query->where('marketing_id', $userId))
            ->get()
            ->each(function (MarketingSurveySchedule $row): void {
                $existing = MarketingReminder::query()
                    ->where('source_type', MarketingSurveySchedule::class)
                    ->where('source_id', $row->id)
                    ->first();
                $status = in_array($row->status, ['selesai', 'batal'], true)
                    ? 'selesai'
                    : ($existing?->status ?? 'menunggu');

                MarketingReminder::query()->updateOrCreate(
                    ['source_type' => MarketingSurveySchedule::class, 'source_id' => $row->id],
                    [
                        'costumer_id' => $row->costumer_id,
                        'user_id' => $row->marketing_id,
                        'jenis' => 'survey',
                        'judul' => 'Jadwal survey customer',
                        'remind_at' => $row->tanggal_survey,
                        'status' => $status,
                        'catatan' => $row->catatan,
                    ],
                );
            });
    }

    public function expireBookings(): int
    {
        $expired = Spr::query()
            ->with(['payments', 'detailRumah'])
            ->where('status', Spr::STATUS_DISETUJUI)
            ->whereNotNull('booking_expires_at')
            ->where('booking_expires_at', '<', now())
            ->get()
            ->filter(fn (Spr $spr) => (float) $spr->payments->where('jenis_pembayaran', 'booking_fee')->sum('nominal') < (float) $spr->booking_fee);

        foreach ($expired as $spr) {
            DB::transaction(function () use ($spr): void {
                $spr->update([
                    'status' => Spr::STATUS_DITOLAK,
                    'alasan_batal' => 'Masa berlaku booking berakhir',
                ]);
                $spr->detailRumah?->update([
                    'status_penjualan' => 'tersedia',
                    'booking_spr_id' => null,
                    'booking_at' => null,
                ]);
            });
        }

        return $expired->count();
    }

    public function recordKprStage(KprSubmission $submission, string $status, ?string $note = null, ?int $userId = null): void
    {
        KprStageHistory::create([
            'kpr_submission_id' => $submission->id,
            'tahapan' => $this->kprStage($status),
            'status' => $status,
            'tanggal_status' => now(),
            'catatan' => $note,
            'user_id' => $userId,
        ]);
    }

    protected function upsertSchedule(Spr $spr, string $type, ?int $part, mixed $date, float $amount): void
    {
        if ($amount <= 0 || blank($date)) {
            return;
        }

        SprBillingSchedule::query()->updateOrCreate(
            ['spr_id' => $spr->id, 'jenis_tagihan' => $type, 'termin_ke' => $part],
            [
                'tanggal_jatuh_tempo' => Carbon::parse($date)->toDateString(),
                'nominal_tagihan' => round($amount, 2),
                'keterangan' => $part ? ucfirst(str_replace('_', ' ', $type))." ke-{$part}" : ucfirst(str_replace('_', ' ', $type)),
            ],
        );
    }

    protected function allocatePayments(Spr $spr): void
    {
        foreach (['booking_fee', 'uang_muka', 'termin'] as $type) {
            $paymentType = $type === 'termin' ? 'lainnya' : $type;
            $remaining = (float) $spr->payments
                ->where('jenis_pembayaran', $paymentType)
                ->where('status', 'dikonfirmasi')
                ->sum('nominal');

            foreach ($spr->billingSchedules()->where('jenis_tagihan', $type)->orderBy('termin_ke')->orderBy('id')->get() as $schedule) {
                $paid = min($remaining, (float) $schedule->nominal_tagihan);
                $remaining -= $paid;
                $schedule->update([
                    'nominal_dibayar' => $paid,
                    'status' => $paid >= (float) $schedule->nominal_tagihan
                        ? 'lunas'
                        : ($paid > 0 ? 'sebagian' : ($schedule->tanggal_jatuh_tempo->isPast() ? 'jatuh_tempo' : 'belum_bayar')),
                ]);
            }
        }
    }

    protected function kprStage(string $status): string
    {
        return match ($status) {
            'slik_menunggu', 'slik_lolos', 'slik_tidak_lolos' => 'slik',
            'dp_belum_bayar', 'dp_sudah_bayar' => 'dp_booking',
            'pengumpulan_dokumen', 'berkas_belum_lengkap', 'berkas_lengkap' => 'berkas',
            'ots_belum_dijadwalkan', 'ots_dijadwalkan', 'ots_selesai' => 'ots',
            'submit_bank', 'analisa_menunggu', 'analisa_diproses', 'disetujui', 'ditolak' => 'analisa_bank',
            'akad_belum_dijadwalkan', 'akad_dijadwalkan', 'akad_selesai' => 'akad',
            'serah_terima_belum', 'serah_terima_selesai' => 'serah_terima',
            default => 'proses_kpr',
        };
    }
}
