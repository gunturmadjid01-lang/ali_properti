<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MaterialStockOpname extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_opname',
        'gudang_id',
        'tanggal',
        'keterangan',
        'record_status',
        'locked_at',
        'locked_by',
        'stock_posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'locked_at' => 'datetime',
        'stock_posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $opname): void {
            if (filled($opname->kode_opname)) {
                return;
            }

            $opname->kode_opname = static::nextKodeOpname();
        });
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(MaterialStockOpnameDetail::class);
    }

    public static function nextKodeOpname(): string
    {
        $prefix = 'OPN-'.now()->format('Ym').'-';

        $next = (static::withTrashed()
            ->where('kode_opname', 'like', $prefix.'%')
            ->count()) + 1;

        return $prefix.Str::padLeft((string) $next, 4, '0');
    }
}
