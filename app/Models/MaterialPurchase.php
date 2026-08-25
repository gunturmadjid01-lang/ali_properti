<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialPurchase extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    public const STATUS_MENUNGGU_MANAGER = 'menunggu_approval_manager';
    public const STATUS_MENUNGGU_APPROVAL = 'menunggu_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_MENUNGGU_DANA = 'menunggu_pencairan_dana';
    public const STATUS_DANA_CAIR = 'dana_cair';
    public const STATUS_DIBELI = 'dibeli';
    public const STATUS_MENUNGGU_PEMERIKSAAN = 'menunggu_pengecekan';
    public const STATUS_DITERIMA = 'diterima_logistik';
    public const STATUS_DITERIMA_SEBAGIAN = 'diterima_sebagian';
    public const STATUS_DITOLAK_GUDANG = 'ditolak_gudang';
    public const STATUS_PENGECEKAN_SELESAI = 'pengecekan_selesai';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'kode_pembelian',
        'tanggal',
        'tanggal_barang_masuk',
        'nomor_faktur',
        'tanggal_faktur',
        'nomor_surat_jalan',
        'nama_ekspedisi',
        'nomor_kendaraan',
        'material_request_id',
        'material_purchase_request_id',
        'gudang_id',
        'perumahan_id',
        'detail_rumah_id',
        'tahapan_pembangunan_id',
        'kelompok_hpp_id',
        'supplier_id',
        'supplier',
        'metode_pembayaran',
        'planned_master_bank_id',
        'payment_master_bank_id',
        'subtotal_nominal',
        'diskon_transaksi',
        'biaya_ekspedisi',
        'upah_buruh_logistik',
        'biaya_lain_perolehan',
        'metode_alokasi_biaya',
        'total_nominal',
        'total_landed_cost',
        'status',
        'keterangan',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'fund_released_by',
        'fund_released_at',
        'received_by',
        'received_at',
        'receive_note',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_barang_masuk' => 'date',
        'tanggal_faktur' => 'date',
        'subtotal_nominal' => 'float',
        'diskon_transaksi' => 'float',
        'biaya_ekspedisi' => 'float',
        'upah_buruh_logistik' => 'float',
        'biaya_lain_perolehan' => 'float',
        'total_nominal' => 'float',
        'total_landed_cost' => 'float',
        'approved_at' => 'datetime',
        'fund_released_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(MaterialPurchaseDetail::class);
    }

    public function supplierInvoice(): HasOne
    {
        return $this->hasOne(MaterialSupplierInvoice::class);
    }

    public function supplierClaims(): HasMany
    {
        return $this->hasMany(MaterialSupplierClaim::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function materialPurchaseRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchaseRequest::class);
    }

    public function supplierData(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function plannedMasterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class, 'planned_master_bank_id');
    }

    public function paymentMasterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class, 'payment_master_bank_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fundReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fund_released_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
