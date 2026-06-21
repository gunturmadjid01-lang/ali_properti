<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenLegalitas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dokumen_legalitas';

    protected $fillable = [
        'perumahan_id',
        'nama_dokument',
        'nomor_dokument',
        'tanggal_terbit',
        'tanggal_berakhir',
        'file',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'tanggal_terbit' => 'date',
        'tanggal_berakhir' => 'date',
        'locked_at' => 'datetime',
    ];

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }
}
