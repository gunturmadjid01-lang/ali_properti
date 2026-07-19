<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MaterialGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'base_quantity', 'base_unit', 'notes', 'status', 'created_by', 'updated_by'];

    protected $casts = ['base_quantity' => 'float'];

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            $group->code ??= 'KMT-'.Str::padLeft((string) (self::withTrashed()->count() + 1), 5, '0');
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialGroupItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
