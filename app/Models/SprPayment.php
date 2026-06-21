<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SprPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spr_id',
        'master_bank_id',
        'transaksi_keuangan_id',
        'created_by',
        'jenis_pembayaran',
        'tanggal_pembayaran',
        'nominal',
        'bukti_pembayaran',
        'keterangan',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'tanggal_pembayaran' => 'date',
        'nominal' => 'float',
        'locked_at' => 'datetime',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function masterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class, 'master_bank_id');
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
