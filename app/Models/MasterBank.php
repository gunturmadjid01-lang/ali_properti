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
}
