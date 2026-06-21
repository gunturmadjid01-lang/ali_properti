<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerumahanHpp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'perumahan_id',
        'user_id',
        'tanggal_dibuat',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PerumahanHpp $perumahanHpp) {
            $perumahanHpp->detailPerumahanHpps()->get()->each->delete();
        });
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detailPerumahanHpps(): HasMany
    {
        return $this->hasMany(DetailPerumahanHpp::class);
    }
}
