<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpkKontraktorItem extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    protected $fillable = [
        'spk_kontraktor_id',
        'tahapan_pembangunan_id',
        'nama_tahap_pekerjaan',
        'nama_pekerjaan',
        'volume',
        'satuan',
        'harga_satuan',
        'total',
        'urutan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'volume' => 'float',
        'harga_satuan' => 'float',
        'total' => 'float',
        'urutan' => 'integer',
    ];

    public function spkKontraktor(): BelongsTo
    {
        return $this->belongsTo(SpkKontraktor::class);
    }

    public function tahapanPembangunan(): BelongsTo
    {
        return $this->belongsTo(TahapanPembangunan::class);
    }
}
