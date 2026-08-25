<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\CustomerDocumentChecklist;
use App\Models\HousingReservation;
use App\Models\KprSubmission;
use App\Models\MarketingLead;
use App\Models\MarketingVisit;
use App\Models\PaymentSchedule;
use App\Models\SalesWorkItem;
use App\Models\Spr;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdminSalesWorkQueueService
{
    public function sync(): array
    {
        $seen = [];
        $counts = [];
        $defaultAdmin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin_sales'))->value('id');
        $add = function (string $key, string $category, string $title, Model $subject, ?int $customerId, ?int $adminId, string $priority, $dueAt, ?string $description = null) use (&$seen, &$counts, $defaultAdmin): void {
            $seen[] = $key;
            $counts[$category] = ($counts[$category] ?? 0) + 1;
            $item = SalesWorkItem::query()->updateOrCreate(['automation_key' => $key], ['work_no' => 'AUTO-'.strtoupper(substr(sha1($key), 0, 14)), 'category' => $category, 'title' => $title, 'description' => $description, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'costumer_id' => $customerId, 'marketing_lead_id' => $subject instanceof MarketingLead ? $subject->id : null, 'assigned_to' => $adminId ?: $defaultAdmin, 'priority' => $priority, 'status' => 'open', 'due_at' => $dueAt, 'updated_by' => null]);
            if ($item->wasRecentlyCreated && $item->assigned_to) {
                AppNotification::query()->create(['user_id' => $item->assigned_to, 'role' => 'admin_sales', 'title' => $title, 'message' => $description ?: 'Tugas otomatis baru memerlukan tindak lanjut.', 'url' => '/admin/admin-sales/tugas/'.$item->id]);
            }
        };

        MarketingLead::query()->where('do_not_contact', false)->whereNotNull('next_action_at')->where('next_action_at', '<', now())->whereNotIn('stage', ['converted', 'lost'])->get()->each(fn ($x) => $add('followup-overdue-lead-'.$x->id, 'lead', 'Follow-up terlambat: '.$x->name, $x, null, $x->admin_sales_id, 'high', $x->next_action_at, 'Marketing belum mencatat tindak lanjut sesuai rencana.'));
        MarketingLead::query()->where('do_not_contact', false)->whereNull('first_contacted_at')->where('first_response_due_at', '<', now())->whereNotIn('stage', ['converted', 'lost'])->get()->each(fn ($x) => $add('lead-response-'.$x->id, 'lead', 'SLA respons terlewati: '.$x->name, $x, null, $x->admin_sales_id, 'urgent', $x->first_response_due_at));
        MarketingLead::query()->where('qualification_status', 'submitted')->where('verification_status', 'pending')->where('submitted_for_verification_at', '<', now()->subHours(4))->get()->each(fn ($x) => $add('lead-verification-'.$x->id, 'lead', 'Verifikasi Lead terlambat: '.$x->name, $x, null, $x->admin_sales_id, 'urgent', $x->submitted_for_verification_at?->copy()->addHours(4), 'Lead Qualified belum diputuskan Admin Sales lebih dari 4 jam.'));
        MarketingLead::query()->whereIn('stage', ['lost', 'postponed'])->whereNotNull('recycle_at')->where('recycle_at', '<=', now())->get()->each(fn ($x) => $add('lead-recycle-'.$x->id, 'lead', 'Aktifkan kembali Lead: '.$x->name, $x, null, $x->admin_sales_id, 'medium', $x->recycle_at, 'Lead sudah mencapai tanggal recycle dan perlu ditinjau untuk penugasan ulang.'));
        MarketingVisit::query()->where('status', 'completed')->where('admin_review_status', 'pending')->with('costumer')->get()->each(fn ($x) => $add('visit-review-'.$x->id, 'visit', 'Periksa laporan kunjungan '.$x->visit_no, $x, $x->costumer_id, $x->costumer?->admin_sales_id, 'medium', now()->addDay(), $x->result));
        CustomerDocumentChecklist::query()->where('validation_status', '!=', 'complete')->with('costumer')->get()->each(fn ($x) => $add('document-incomplete-'.$x->id, 'document', 'Lengkapi dokumen '.$x->checklist_no, $x, $x->costumer_id, $x->costumer?->admin_sales_id, 'medium', now()->addDays(2)));
        HousingReservation::query()->whereIn('status', ['draft', 'pending', 'submitted', 'payment_submitted'])->with('customer')->get()->each(fn ($x) => $add('reservation-review-'.$x->id, 'reservation', 'Proses reservasi '.$x->reservation_no, $x, $x->costumer_id, $x->customer?->admin_sales_id, 'high', ($x->reserved_at ?? now())->copy()->addDay()));
        Spr::query()->whereNotIn('status', [Spr::STATUS_DISETUJUI, Spr::STATUS_DITOLAK])->with('costumer')->get()->each(fn ($x) => $add('spr-review-'.$x->id, 'spr', 'Proses SPR '.$x->kode_spr, $x, $x->costumer_id, $x->costumer?->admin_sales_id, 'high', now()->addDay()));
        KprSubmission::query()->whereNotIn('status', ['approved', 'rejected', 'cancelled', 'disbursed'])->where('updated_at', '<', now()->subDays(3))->with('spr.costumer')->get()->each(fn ($x) => $add('kpr-stale-'.$x->id, 'kpr', 'Perbarui KPR '.$x->kode_kpr, $x, $x->spr?->costumer_id, $x->spr?->costumer?->admin_sales_id, 'high', now(), 'Status KPR belum diperbarui lebih dari 3 hari.'));
        PaymentSchedule::query()->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', '<=', now()->addDays(3))->with(['salesTransaction.customer', 'housingReservation.customer'])->get()->each(function ($x) use ($add): void {
            $customer = $x->salesTransaction?->customer ?? $x->housingReservation?->customer;
            $add('payment-due-'.$x->id, 'payment', 'Pantau tagihan '.$x->invoice_no, $x, $customer?->id, $customer?->admin_sales_id, $x->due_date?->isPast() ? 'urgent' : 'high', $x->due_date, 'Sisa pembayaran: '.number_format((float) $x->amount - (float) $x->paid_amount, 0, ',', '.'));
        });

        SalesWorkItem::query()->whereNotNull('automation_key')->whereNotIn('automation_key', $seen ?: ['__none__'])->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'completed', 'completed_at' => now(), 'resolution_note' => 'Kondisi sumber sudah tidak memerlukan tindak lanjut.']);

        return $counts;
    }
}
