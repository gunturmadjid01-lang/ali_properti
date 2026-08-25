<?php

namespace App\Services;

use App\Models\CrmNurtureEnrollment;
use App\Models\CrmNurtureSequence;
use App\Models\MarketingLead;
use App\Models\MarketingReminder;
use Illuminate\Support\Carbon;

class CrmNurtureService
{
    public function sync(): int
    {
        $sequence = $this->defaultSequence();
        $count = 0;
        MarketingLead::query()->whereNull('deleted_at')->whereNotIn('status', ['lost', 'converted'])->chunkById(100, function ($leads) use ($sequence, &$count): void {
            foreach ($leads as $lead) {
                if ($lead->do_not_contact || !$lead->marketing_id) continue;
                CrmNurtureEnrollment::firstOrCreate(
                    ['sequence_id' => $sequence->id, 'marketing_lead_id' => $lead->id],
                    ['status' => 'aktif', 'enrolled_at' => now(), 'next_run_at' => now()]
                );
                $count++;
            }
        });
        return $count;
    }

    public function processDue(): int
    {
        $processed = 0;
        CrmNurtureEnrollment::with(['sequence.steps', 'lead'])->where('status', 'aktif')->where(function ($query): void {
            $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
        })->chunkById(100, function ($enrollments) use (&$processed): void {
            foreach ($enrollments as $enrollment) {
                $lead = $enrollment->lead;
                $step = $enrollment->sequence->steps->firstWhere('step_order', $enrollment->current_step);
                if (!$lead || $lead->do_not_contact || in_array($lead->status, ['lost', 'converted'], true) || !$step) {
                    $enrollment->update(['status' => 'berhenti', 'completed_at' => now()]);
                    continue;
                }
                MarketingReminder::firstOrCreate(
                    ['source_type' => 'crm_nurture_step', 'source_id' => $enrollment->id * 10000 + $step->step_order],
                    ['marketing_lead_id' => $lead->id, 'user_id' => $lead->marketing_id, 'jenis' => 'nurture', 'judul' => $step->title, 'remind_at' => now(), 'status' => 'menunggu', 'catatan' => $step->note]
                );
                $next = $enrollment->sequence->steps->firstWhere('step_order', $enrollment->current_step + 1);
                $enrollment->update($next ? ['current_step' => $next->step_order, 'next_run_at' => Carbon::now()->addMinutes($next->delay_minutes)] : ['status' => 'selesai', 'completed_at' => now(), 'next_run_at' => null]);
                $processed++;
            }
        });
        return $processed;
    }

    private function defaultSequence(): CrmNurtureSequence
    {
        $sequence = CrmNurtureSequence::firstOrCreate(['code' => 'lead-baru'], ['name' => 'Tindak lanjut lead baru', 'description' => 'Pengingat bertahap untuk lead yang belum dihubungi.']);
        if ($sequence->steps()->count() === 0) {
            $sequence->steps()->createMany([
                ['step_order' => 1, 'delay_minutes' => 0, 'title' => 'Hubungi lead baru', 'note' => 'Periksa kebutuhan, sumber lead, dan waktu terbaik untuk dihubungi.'],
                ['step_order' => 2, 'delay_minutes' => 1440, 'title' => 'Tindak lanjut hari kedua', 'note' => 'Kirim informasi proyek yang paling sesuai dan catat tanggapan lead.'],
                ['step_order' => 3, 'delay_minutes' => 2880, 'title' => 'Tindak lanjut terakhir', 'note' => 'Coba kontak terakhir, lalu jadwalkan daur ulang bila belum ada respons.'],
            ]);
        }
        return $sequence->load('steps');
    }
}
