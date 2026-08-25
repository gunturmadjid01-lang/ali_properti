<?php

namespace App\Services\Marketing;

use App\Models\AppNotification;
use App\Models\CostumerFollowUp;
use App\Models\CustomerDocumentChecklist;
use App\Models\HousingReservation;
use App\Models\KprStageHistory;
use App\Models\KprSubmission;
use App\Models\MarketingActionPlan;
use App\Models\MarketingLead;
use App\Models\MarketingReminder;
use App\Models\MarketingSurveySchedule;
use App\Models\MarketingVisit;
use App\Models\Spr;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketingOperationsService
{
    public function syncAutomaticReminders(?int $userId = null): void
    {
        $syncKey = 'marketing-reminder-sync:'.($userId ?: 'all');
        if (! Cache::add($syncKey, true, now()->addMinutes(5))) {
            return;
        }

        MarketingLead::query()
            ->where('stage', 'new')
            ->where('do_not_contact', false)
            ->whereNotNull('marketing_id')
            ->when($userId, fn ($query) => $query->where('marketing_id', $userId))
            ->limit(200)
            ->get()
            ->each(function (MarketingLead $lead): void {
                MarketingReminder::query()->updateOrCreate(
                    ['source_type' => MarketingLead::class, 'source_id' => $lead->id],
                    [
                        'marketing_lead_id' => $lead->id,
                        'user_id' => $lead->marketing_id,
                        'jenis' => 'lead_baru',
                        'judul' => 'Hubungi lead baru',
                        'remind_at' => $lead->first_response_due_at ?? $lead->created_at?->copy()->addHours(2) ?? now(),
                        'status' => $lead->first_contacted_at ? 'selesai' : 'menunggu',
                        'completed_at' => $lead->first_contacted_at,
                        'catatan' => 'SLA kontak awal maksimal 2 jam setelah lead diterima.',
                    ],
                );
            });

        MarketingVisit::query()->whereNotIn('status', ['completed', 'cancelled'])
            ->when($userId, fn ($query) => $query->where('marketing_id', $userId))
            ->limit(200)
            ->get()->each(fn (MarketingVisit $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => MarketingVisit::class, 'source_id' => $row->id],
                ['costumer_id' => $row->costumer_id, 'user_id' => $row->marketing_id, 'jenis' => 'kunjungan',
                    'judul' => 'Jadwal kunjungan customer', 'remind_at' => $row->planned_at,
                    'status' => 'menunggu', 'catatan' => $row->objective]
            ));

        MarketingActionPlan::query()->whereNotIn('status', ['completed', 'cancelled'])
            ->when($userId, fn ($query) => $query->where('marketing_id', $userId))
            ->limit(200)
            ->get()->each(fn (MarketingActionPlan $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => MarketingActionPlan::class, 'source_id' => $row->id],
                ['costumer_id' => $row->costumer_id, 'user_id' => $row->marketing_id, 'jenis' => 'action_plan',
                    'judul' => $row->title, 'remind_at' => $row->due_at,
                    'status' => 'menunggu', 'catatan' => $row->objective]
            ));

        MarketingLead::query()->where('do_not_contact', false)->whereNotNull('marketing_id')->whereNotIn('stage', ['converted', 'lost'])
            ->where(fn ($query) => $query->whereNull('last_activity_at')->orWhere('last_activity_at', '<=', now()->subDays(7)))
            ->when($userId, fn ($query) => $query->where('marketing_id', $userId))->limit(200)->get()
            ->each(fn (MarketingLead $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => 'stale-lead', 'source_id' => $row->id],
                ['marketing_lead_id' => $row->id, 'user_id' => $row->marketing_id, 'jenis' => 'lead_tidak_aktif', 'judul' => 'Lead tidak memiliki aktivitas 7 hari', 'remind_at' => now(), 'status' => 'menunggu', 'catatan' => 'Segera lakukan follow-up atau perbarui tahap Lead.']
            ));

        MarketingVisit::query()->where('status', 'completed')->where('verification_status', 'pending_review')
            ->when($userId, fn ($query) => $query->where('marketing_id', $userId))->limit(200)->get()
            ->each(fn (MarketingVisit $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => MarketingVisit::class.':report', 'source_id' => $row->id],
                ['costumer_id' => $row->costumer_id, 'user_id' => $row->marketing_id, 'jenis' => 'laporan_kunjungan', 'judul' => 'Laporan kunjungan menunggu finalisasi/verifikasi', 'remind_at' => $row->finished_at ?? now(), 'status' => 'menunggu', 'catatan' => $row->result]
            ));

        CustomerDocumentChecklist::query()->with('costumer:id,assigned_marketing_id')->where('validation_status', '!=', 'complete')
            ->when($userId, fn ($query) => $query->whereHas('costumer', fn ($customer) => $customer->where('assigned_marketing_id', $userId)))
            ->limit(200)->get()
            ->each(fn (CustomerDocumentChecklist $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => CustomerDocumentChecklist::class, 'source_id' => $row->id],
                ['costumer_id' => $row->costumer_id, 'user_id' => $row->costumer?->assigned_marketing_id, 'jenis' => 'dokumen_belum_lengkap', 'judul' => 'Dokumen customer belum lengkap', 'remind_at' => now(), 'status' => 'menunggu', 'catatan' => 'Kelengkapan '.$row->completion_percentage.'% pada tahap '.$row->process_stage]
            ));

        HousingReservation::query()->with('customer:id,assigned_marketing_id')->whereIn('status', ['pending_payment', 'payment_submitted'])->whereBetween('payment_due_at', [now(), now()->addDays(2)])
            ->when($userId, fn ($query) => $query->whereHas('customer', fn ($customer) => $customer->where('assigned_marketing_id', $userId)))
            ->limit(200)->get()
            ->each(fn (HousingReservation $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => HousingReservation::class, 'source_id' => $row->id],
                ['costumer_id' => $row->costumer_id, 'user_id' => $row->customer?->assigned_marketing_id, 'jenis' => 'reservasi_jatuh_tempo', 'judul' => 'Reservasi mendekati jatuh tempo', 'remind_at' => $row->payment_due_at, 'status' => 'menunggu', 'catatan' => $row->reservation_no]
            ));

        Spr::query()->with('costumer:id,assigned_marketing_id')->whereIn('status', [Spr::STATUS_MENUNGGU_APPROVAL, Spr::STATUS_MENUNGGU_MANAGER, Spr::STATUS_MENUNGGU_OWNER])->where('updated_at', '<=', now()->subDay())
            ->when($userId, fn ($query) => $query->whereHas('costumer', fn ($customer) => $customer->where('assigned_marketing_id', $userId)))
            ->limit(200)->get()
            ->each(fn (Spr $row) => MarketingReminder::query()->updateOrCreate(
                ['source_type' => Spr::class.':pending', 'source_id' => $row->id],
                ['costumer_id' => $row->costumer_id, 'user_id' => $row->costumer?->assigned_marketing_id, 'jenis' => 'spr_belum_diproses', 'judul' => 'SPR belum diproses lebih dari 1 hari', 'remind_at' => $row->updated_at->addDay(), 'status' => 'menunggu', 'catatan' => $row->kode_spr]
            ));

        MarketingReminder::query()->where('status', 'menunggu')->where('remind_at', '<=', now())
            ->with(['costumer:id,nama', 'lead:id,name'])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->limit(200)
            ->get()->each(function (MarketingReminder $reminder): void {
                $url = '/admin/marketing?reminder_id='.$reminder->id;
                AppNotification::query()->firstOrCreate(
                    ['user_id' => $reminder->user_id, 'title' => 'Tugas marketing jatuh tempo', 'url' => $url, 'read_at' => null],
                    ['message' => $reminder->judul.' - '.($reminder->lead?->name ?? $reminder->costumer?->nama ?? 'tanpa referensi')]
                );
            });

        CostumerFollowUp::query()
            ->whereNotNull('rencana_follow_up_at')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->limit(200)
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
            ->limit(200)
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
