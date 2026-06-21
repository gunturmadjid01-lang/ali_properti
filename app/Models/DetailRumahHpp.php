<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailRumahHpp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'detail_rumah_id',
        'user_id',
        'tanggal_dibuat',
    ];

    protected $casts = [
        'tanggal_dibuat' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (DetailRumahHpp $detailRumahHpp) {
            $detailRumahHpp->items()->get()->each->delete();
        });
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DetailRumahHppItem::class);
    }
}
