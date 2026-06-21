<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpkKontraktorPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spk_kontraktor_id',
        'termin_ke',
        'tanggal_jatuh_tempo',
        'tanggal_pembayaran',
        'nominal',
        'keterangan',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'released_by',
        'released_at',
        'paid_at',
    ];

    protected $casts = [
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_pembayaran' => 'date',
        'nominal' => 'float',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function spkKontraktor(): BelongsTo
    {
        return $this->belongsTo(SpkKontraktor::class);
    }
}
