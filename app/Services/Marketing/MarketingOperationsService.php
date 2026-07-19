<?php

namespace App\Services\Marketing;

use App\Models\CostumerFollowUp;
use App\Models\KprStageHistory;
use App\Models\KprSubmission;
use App\Models\MarketingReminder;
use App\Models\MarketingSurveySchedule;
use App\Models\Spr;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingOperationsService
{
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
            ->with(['salesTransaction.customerReceipts', 'detailRumah'])
            ->where('status', Spr::STATUS_DISETUJUI)
            ->whereNotNull('booking_expires_at')
            ->where('booking_expires_at', '<', now())
            ->get()
            ->filter(fn (Spr $spr) => (float) ($spr->salesTransaction?->customerReceipts->where('receipt_purpose', 'booking_fee')->where('status', 'posted')->sum('amount') ?? 0) < (float) $spr->booking_fee);

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
