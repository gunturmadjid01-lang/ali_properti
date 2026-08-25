<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nomor_jurnal',
        'tanggal',
        'type',
        'record_status',
        'locked_at',
        'locked_by',
        'posted_at',
        'posted_by',
        'source_type',
        'source_id',
        'cabang_perusahaan_id',
        'perumahan_id',
        'detail_rumah_id',
        'master_bank_id',
        'total_debit',
        'total_kredit',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_debit' => 'float',
        'total_kredit' => 'float',
        'locked_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function details(): HasMany
    {
        return $this->hasMany(JournalDetail::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_perusahaan_id');
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function masterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function latestApproval()
    {
        return $this->morphOne(ApprovalRequest::class, 'model')->latestOfMany();
    }
}
