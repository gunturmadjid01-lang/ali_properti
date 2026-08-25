<?php

namespace App\Services\Marketing;

use App\Models\Costumer;
use App\Models\CustomerUnitInterest;
use App\Models\MarketingLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingLeadConversionService
{
    public function convert(MarketingLead $lead, int $actorId): Costumer
    {
        if ($lead->converted_costumer_id) {
            return Costumer::query()->findOrFail($lead->converted_costumer_id);
        }
        if ($lead->stage !== 'qualified') {
            throw ValidationException::withMessages(['stage' => 'Lead harus berstatus Qualified sebelum dikonversi.']);
        }
        if ($lead->verification_status !== 'verified') {
            throw ValidationException::withMessages(['stage' => 'Lead Qualified harus diverifikasi Admin Sales sebelum menjadi Customer.']);
        }
        if (! $lead->phone || ! $lead->perumahan_id || ! $lead->preferred_payment_method) {
            throw ValidationException::withMessages(['stage' => 'Nomor telepon, perumahan yang diminati, dan rencana pembayaran wajib lengkap sebelum konversi.']);
        }
        $duplicate = Costumer::query()->where('telepon', $lead->phone)->when($lead->email, fn ($query) => $query->orWhere('email', $lead->email))->first();
        if ($duplicate) {
            throw ValidationException::withMessages(['stage' => 'Customer serupa sudah ada: '.$duplicate->kode_costumer.' - '.$duplicate->nama.'.']);
        }

        return DB::transaction(function () use ($lead, $actorId): Costumer {
            $nextId = (int) (Costumer::withTrashed()->max('id') ?? 0) + 1;
            $customer = Costumer::query()->create([
                'kode_costumer' => 'CST-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
                'source_marketing_lead_id' => $lead->id, 'customer_stage' => 'pre_reservation',
                'nama' => $lead->name, 'telepon' => $lead->phone, 'email' => $lead->email, 'no_identitas' => $lead->identity_no,
                'perumahan_id' => $lead->perumahan_id, 'marketing_campaign_id' => $lead->marketing_campaign_id,
                'assigned_marketing_id' => $lead->marketing_id, 'admin_sales_id' => $lead->admin_sales_id,
                'marketing_lead_source_id' => $lead->lead_source_id, 'lead_ownership_type' => $lead->ownership_type,
                'lead_source_channel' => $lead->source_channel, 'interest_level' => $lead->interest_level,
                'preferred_payment_method' => $lead->preferred_payment_method, 'status_lead' => 'qualified',
                'lead_verification_status' => 'verified', 'assignment_status' => $lead->marketing_id ? 'responded' : 'unassigned',
                'lead_received_at' => $lead->created_at, 'first_contacted_at' => $lead->first_contacted_at,
                'last_activity_at' => $lead->last_activity_at, 'next_action_at' => $lead->next_action_at,
                'keterangan' => $lead->notes, 'created_by' => $actorId, 'updated_by' => $actorId,
            ]);
            if ($lead->detail_rumah_id || $lead->unit_type_interest) {
                CustomerUnitInterest::query()->create([
                    'costumer_id' => $customer->id, 'detail_rumah_id' => $lead->detail_rumah_id,
                    'perumahan_id' => $lead->perumahan_id, 'interest_level' => $lead->interest_level,
                    'payment_plan' => $lead->preferred_payment_method, 'budget_min' => $lead->budget_min,
                    'budget_max' => $lead->budget_max, 'notes' => $lead->unit_type_interest ? 'Tipe diminati: '.$lead->unit_type_interest : null,
                    'created_by' => $actorId, 'updated_by' => $actorId,
                ]);
            }
            $lead->update(['stage' => 'converted', 'converted_costumer_id' => $customer->id, 'converted_at' => now(), 'converted_by' => $actorId, 'updated_by' => $actorId]);

            return $customer;
        });
    }
}
