<?php

namespace App\Services\Marketing;

use App\Models\Costumer;
use App\Models\MarketingLeadActivity;
use App\Models\Spr;

class MarketingLeadStatusService
{
    public const LEAD_BARU = 'lead_baru';
    public const DIHUBUNGI = 'dihubungi';
    public const SURVEY_LOKASI = 'survey_lokasi';
    public const NEGOSIASI = 'negosiasi';
    public const BOOKING_FEE = 'booking_fee';
    public const SPR = 'spr';
    public const CLOSING = 'closing';
    public const BATAL = 'batal';

    public static function statusOptions(): array
    {
        return [
            ['value' => self::LEAD_BARU, 'label' => 'Lead Baru'],
            ['value' => self::DIHUBUNGI, 'label' => 'Dihubungi'],
            ['value' => self::SURVEY_LOKASI, 'label' => 'Survey Lokasi'],
            ['value' => self::NEGOSIASI, 'label' => 'Negosiasi'],
            ['value' => self::BOOKING_FEE, 'label' => 'Booking Fee'],
            ['value' => self::SPR, 'label' => 'SPR'],
            ['value' => self::CLOSING, 'label' => 'Closing'],
            ['value' => self::BATAL, 'label' => 'Batal'],
        ];
    }

    public function markCustomer(
        ?int $customerId,
        string $status,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        bool $forceLog = false
    ): void
    {
        if (! $customerId) {
            return;
        }

        $customer = Costumer::query()->find($customerId);

        if (! $customer) {
            return;
        }

        $previousStatus = $customer->status_lead;

        if ($previousStatus !== $status) {
            Costumer::query()
                ->whereKey($customerId)
                ->update(['status_lead' => $status]);
        }

        if ($forceLog || $previousStatus !== $status) {
            MarketingLeadActivity::query()->create([
                'costumer_id' => $customer->id,
                'user_id' => auth()->id(),
                'status_from' => $previousStatus,
                'status_to' => $status,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'activity_at' => now(),
                'note' => $note,
            ]);
        }
    }

    public function markSpr(?Spr $spr, string $status, ?string $note = null): void
    {
        if (! $spr) {
            return;
        }

        $this->markCustomer(
            (int) $spr->costumer_id,
            $status,
            Spr::class,
            (int) $spr->id,
            $note
        );
    }
}
