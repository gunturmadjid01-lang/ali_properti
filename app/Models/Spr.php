<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spr extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_MENUNGGU_APPROVAL = 'menunggu_approval';

    public const STATUS_MENUNGGU_MANAGER = 'menunggu_manager';

    public const STATUS_MENUNGGU_OWNER = 'menunggu_owner';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'housing_reservation_id',
        'kode_spr',
        'revision_no',
        'revision_status',
        'superseded_by_spr_id',
        'costumer_id',
        'detail_rumah_id',
        'created_by',
        'updated_by',
        'tanggal_spr',
        'booking_expires_at',
        'metode_pembayaran',
        'bank_kredit_id',
        'bank_branch_id',
        'bank_credit_product_id',
        'cash_installment_scheme_id',
        'developer_kpr_product_id',
        'payment_configuration_snapshot',
        'kpr_tenor_bulan',
        'kpr_bunga_tahunan',
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
        'refund_master_bank_id',
        'refund_transaksi_keuangan_id',
        'refund_amount',
        'refund_at',
        'refund_status',
        'refund_requested_by',
        'refund_requested_at',
        'refund_manager_approved_by',
        'refund_manager_approved_at',
        'refund_owner_approved_by',
        'refund_owner_approved_at',
        'refund_rejected_by',
        'refund_rejected_at',
        'refund_approval_note',
        'catatan',
    ];

    public function housingReservation(): BelongsTo { return $this->belongsTo(HousingReservation::class); }

    protected $casts = [
        'tanggal_spr' => 'date',
        'booking_expires_at' => 'datetime',
        'kpr_tenor_bulan' => 'integer',
        'kpr_bunga_tahunan' => 'float',
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
        'refund_amount' => 'float',
        'refund_at' => 'date',
        'refund_requested_at' => 'datetime',
        'refund_manager_approved_at' => 'datetime',
        'refund_owner_approved_at' => 'datetime',
        'refund_rejected_at' => 'datetime',
        'payment_configuration_snapshot' => 'array',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function bankKredit(): BelongsTo
    {
        return $this->belongsTo(BankKredit::class, 'bank_kredit_id');
    }

    public function bankBranch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class);
    }

    public function bankCreditProduct(): BelongsTo
    {
        return $this->belongsTo(BankCreditProduct::class);
    }

    public function cashInstallmentScheme(): BelongsTo
    {
        return $this->belongsTo(CashInstallmentScheme::class);
    }

    public function developerKprProduct(): BelongsTo
    {
        return $this->belongsTo(DeveloperKprProduct::class);
    }

    public function salesTransaction(): HasOne
    {
        return $this->hasOne(SalesTransaction::class);
    }

    public function refundMasterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class, 'refund_master_bank_id');
    }

    public function refundTransaksiKeuangan(): BelongsTo
    {
        return $this->belongsTo(TransaksiKeuangan::class, 'refund_transaksi_keuangan_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SprApproval::class);
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'model')->latest('id');
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

    public function commissions(): HasMany
    {
        return $this->hasMany(MarketingCommission::class);
    }
}
