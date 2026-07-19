<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterBank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cabang_id',
        'perumahan_id',
        'kode_bank',
        'nama_bank',
        'nomor_rekening',
        'nama_rekening',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_id');
    }
}
