<?php

namespace App\Services\Marketing;

use App\Models\Costumer;
use App\Models\MarketingLeadAssignment;
use App\Models\SalesWorkItem;
use App\Models\Spr;

class MarketingLeadStatusService
{
    public const LEAD_BARU = 'lead_baru';

    public const BELUM_DIHUBUNGI = 'belum_dihubungi';

    public const DIHUBUNGI = 'dihubungi';

    public const TIDAK_MERESPONS = 'tidak_merespons';

    public const POTENSIAL = 'potensial';

    public const FOLLOW_UP = 'follow_up';

    public const JADWAL_KUNJUNGAN = 'jadwal_kunjungan';

    public const SUDAH_DIKUNJUNGI = 'sudah_dikunjungi';

    public const JADWAL_SURVEI = 'jadwal_survei';

    public const SURVEY_LOKASI = 'survey_lokasi';

    public const SUDAH_SURVEI = 'sudah_survei';

    public const NEGOSIASI = 'negosiasi';

    public const RESERVASI = 'reservasi';

    public const BOOKING_FEE = 'booking_fee';

    public const SPR = 'spr';

    public const SPR_MENUNGGU = 'spr_menunggu_persetujuan';

    public const SPR_DISETUJUI = 'spr_disetujui';

    public const KPR_BANK = 'proses_kpr_bank';

    public const KPR_DEVELOPER = 'proses_kpr_developer';

    public const CASH_BERTAHAP = 'proses_cash_bertahap';

    public const CASH = 'proses_cash';

    public const AKAD = 'akad';

    public const CLOSING = 'closing';

    public const SERAH_TERIMA = 'serah_terima';

    public const BATAL = 'batal';

    public const TIDAK_BERMINAT = 'tidak_berminat';

    public const TIDAK_AKTIF = 'tidak_aktif';

    public static function statusOptions(): array
    {
        return collect([
            self::LEAD_BARU => 'Lead Baru', self::BELUM_DIHUBUNGI => 'Belum Dihubungi', self::DIHUBUNGI => 'Sudah Dihubungi', self::TIDAK_MERESPONS => 'Tidak Merespons', self::POTENSIAL => 'Customer Potensial', self::FOLLOW_UP => 'Follow-up', self::JADWAL_KUNJUNGAN => 'Jadwal Kunjungan', self::SUDAH_DIKUNJUNGI => 'Sudah Dikunjungi', self::JADWAL_SURVEI => 'Jadwal Survei', self::SURVEY_LOKASI => 'Survei Lokasi (Status Lama)', self::SUDAH_SURVEI => 'Sudah Survei', self::NEGOSIASI => 'Negosiasi', self::RESERVASI => 'Reservasi', self::BOOKING_FEE => 'Booking Fee', self::SPR => 'Pengajuan SPR', self::SPR_MENUNGGU => 'SPR Menunggu Persetujuan', self::SPR_DISETUJUI => 'SPR Disetujui', self::KPR_BANK => 'Proses KPR Bank', self::KPR_DEVELOPER => 'Proses KPR Developer', self::CASH_BERTAHAP => 'Proses Cash Bertahap', self::CASH => 'Proses Cash', self::AKAD => 'Akad', self::CLOSING => 'Closing', self::SERAH_TERIMA => 'Serah Terima', self::BATAL => 'Batal', self::TIDAK_BERMINAT => 'Tidak Berminat', self::TIDAK_AKTIF => 'Tidak Aktif',
        ])->map(fn ($label, $value) => compact('value', 'label'))->values()->all();
    }

    public function markCustomer(?int $customerId, string $status, ?string $sourceType = null, ?int $sourceId = null, ?string $note = null, bool $forceLog = false): void
    {
        if (! $customerId) {
            return;
        }
        $customer = Costumer::query()->find($customerId);
        if (! $customer) {
            return;
        }
        $previousStatus = $customer->status_lead;
        $updates = ['last_activity_at' => now()];
        if ($previousStatus !== $status) {
            $updates['status_lead'] = $status;
        }
        if (! in_array($status, [self::LEAD_BARU, self::BELUM_DIHUBUNGI], true) && ! $customer->first_contacted_at) {
            $updates['first_contacted_at'] = now();
            if ($customer->lead_ownership_type === 'company') {
                $updates['assignment_status'] = 'responded';
                MarketingLeadAssignment::query()->where('costumer_id', $customer->id)->whereIn('status', ['offered', 'accepted'])->latest('assigned_at')->limit(1)->update(['status' => 'responded', 'responded_at' => now(), 'response_note' => 'Respons pertama tercatat dari aktivitas Marketing.']);
                SalesWorkItem::query()->where('costumer_id', $customer->id)->where('category', 'lead')->where('title', 'like', 'Pantau respons%')->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'completed', 'completed_at' => now(), 'resolution_note' => 'Respons pertama tercatat otomatis.']);
            }
        }
        Costumer::query()->whereKey($customerId)->update($updates);
        if ($forceLog || $previousStatus !== $status) {
            $source = $sourceType && $sourceId && class_exists($sourceType) ? $sourceType::query()->find($sourceId) : null;
            app(MarketingActivityService::class)->record($customer->id, 'status_change', 'Perubahan status customer', $source, $note, [], $previousStatus, $status);
        }
    }

    public function markSpr(?Spr $spr, string $status, ?string $note = null): void
    {
        if ($spr) {
            $this->markCustomer((int) $spr->costumer_id, $status, Spr::class, (int) $spr->id, $note);
        }
    }
}
