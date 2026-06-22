<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'perumahan_id', 'kode_campaign', 'nama_campaign', 'kanal', 'tanggal_mulai', 'tanggal_selesai',
        'anggaran', 'realisasi_biaya', 'target_lead', 'status', 'keterangan',
        'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'anggaran' => 'float',
        'realisasi_biaya' => 'float',
        'locked_at' => 'datetime',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Costumer::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }
}
