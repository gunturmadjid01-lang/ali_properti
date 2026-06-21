<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashSale extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_MENUNGGU_PEMBAYARAN = 'menunggu_pembayaran';
    public const STATUS_DP_DIBAYAR = 'dp_dibayar';
    public const STATUS_CICILAN_TERMIN = 'cicilan_termin';
    public const STATUS_LUNAS = 'lunas';
    public const STATUS_SERAH_TERIMA = 'serah_terima';

    protected $fillable = [
        'kode_cash',
        'spr_id',
        'costumer_id',
        'detail_rumah_id',
        'handled_by',
        'tanggal_transaksi',
        'harga_rumah',
        'total_tagihan',
        'total_dibayar',
        'sisa_tagihan',
        'status_pembayaran',
        'catatan',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'date',
        'harga_rumah' => 'float',
        'total_tagihan' => 'float',
        'total_dibayar' => 'float',
        'sisa_tagihan' => 'float',
        'locked_at' => 'datetime',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class, 'costumer_id');
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class, 'detail_rumah_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CashSalePayment::class);
    }
}
