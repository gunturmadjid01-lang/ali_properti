<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpkKontraktorAddition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spk_kontraktor_id',
        'kategori_penambahan',
        'judul_penambahan',
        'deskripsi',
        'volume',
        'satuan',
        'harga_satuan',
        'total',
        'keterangan',
    ];

    protected $casts = [
        'volume' => 'float',
        'harga_satuan' => 'float',
        'total' => 'float',
    ];

    public function spkKontraktor(): BelongsTo
    {
        return $this->belongsTo(SpkKontraktor::class);
    }
}
