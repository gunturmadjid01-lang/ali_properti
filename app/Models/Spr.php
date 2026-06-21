<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spr extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_MENUNGGU_MANAGER = 'menunggu_manager';
    public const STATUS_MENUNGGU_OWNER = 'menunggu_owner';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'kode_spr',
        'costumer_id',
        'detail_rumah_id',
        'created_by',
        'tanggal_spr',
        'booking_expires_at',
        'metode_pembayaran',
        'skema_bertahap',
        'harga_jual',
        'booking_fee',
        'booking_fee_includes_dp',
        'tanggal_pembayaran_booking_fee',
        'uang_muka',
        'uang_muka_jumlah_pembayaran',
        'tanggal_jatuh_tempo_dp',
        'tanggal_jatuh_tempo_angsuran',
        'nilai_pengajuan_kpr',
        'penambahan_tanah',
        'harga_penambahan_tanah',
        'penambahan_lain_lain',
        'harga_penambahan_lain_lain',
        'total_penambahan_tanah',
        'total_penambahan_lain_lain',
        'total_penambahan',
        'nilai_pengajuan_akhir',
        'jumlah_termin',
        'nominal_termin',
        'tanggal_jatuh_tempo_termin',
        'status',
        'alasan_batal',
        'catatan',
    ];

    protected $casts = [
        'tanggal_spr' => 'date',
        'booking_expires_at' => 'datetime',
        'harga_jual' => 'float',
        'booking_fee' => 'float',
        'booking_fee_includes_dp' => 'boolean',
        'tanggal_pembayaran_booking_fee' => 'date',
        'uang_muka' => 'float',
        'uang_muka_jumlah_pembayaran' => 'integer',
        'tanggal_jatuh_tempo_dp' => 'date',
        'tanggal_jatuh_tempo_angsuran' => 'date',
        'nilai_pengajuan_kpr' => 'float',
        'harga_penambahan_tanah' => 'float',
        'harga_penambahan_lain_lain' => 'float',
        'total_penambahan_tanah' => 'float',
        'total_penambahan_lain_lain' => 'float',
        'total_penambahan' => 'float',
        'nilai_pengajuan_akhir' => 'float',
        'jumlah_termin' => 'integer',
        'nominal_termin' => 'float',
        'tanggal_jatuh_tempo_termin' => 'date',
    ];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SprApproval::class);
    }

    public function kprSubmission(): HasOne
    {
        return $this->hasOne(KprSubmission::class);
    }

    public function cashSale(): HasOne
    {
        return $this->hasOne(CashSale::class);
    }

    public function berkasCostumers(): HasMany
    {
        return $this->hasMany(SprBerkasCostumer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SprPayment::class);
    }

    public function billingSchedules(): HasMany
    {
        return $this->hasMany(SprBillingSchedule::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(MarketingCommission::class);
    }
}
