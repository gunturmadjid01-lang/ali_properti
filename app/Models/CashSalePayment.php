<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashSalePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cash_sale_id',
        'transaksi_keuangan_id',
        'created_by',
        'tanggal_pembayaran',
        'nominal',
        'metode_pembayaran',
        'keterangan',
        'bukti_pembayaran',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'tanggal_pembayaran' => 'date',
        'nominal' => 'float',
        'locked_at' => 'datetime',
    ];

    public function cashSale(): BelongsTo
    {
        return $this->belongsTo(CashSale::class);
    }

    public function transaksiKeuangan(): BelongsTo
    {
        return $this->belongsTo(TransaksiKeuangan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
