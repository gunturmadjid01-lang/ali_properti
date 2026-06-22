<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransaksiKeuangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cabang_id',
        'perumahan_id',
        'master_bank_id',
        'tipe_post_id',
        'journal_id',
        'source_type',
        'source_id',
        'nomor_referensi',
        'status',
        'tanggal',
        'nominal',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'float',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_id');
    }

    public function masterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class, 'master_bank_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipePost(): BelongsTo
    {
        return $this->belongsTo(TipePost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
