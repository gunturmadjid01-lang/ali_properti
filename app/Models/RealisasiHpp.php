<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RealisasiHpp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'detail_perumahan_hpp_id',
        'tanggal',
        'nominal',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'float',
    ];

    public function detailPerumahanHpp(): BelongsTo
    {
        return $this->belongsTo(DetailPerumahanHpp::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
