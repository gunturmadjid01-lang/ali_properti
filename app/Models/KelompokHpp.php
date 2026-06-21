<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KelompokHpp extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_LABELS = [
        'material' => 'Logistik',
        'tanah' => 'Tanah',
        'legalitas' => 'Perizinan & Persuratan',
        'bangunan' => 'Konstruksi',
        'tenaga_kerja' => 'Konstruksi',
        'infrastruktur' => 'Utilitas',
        'marketing' => 'Pemasaran',
        'operasional' => 'Operasional',
        'keuangan' => 'Keuangan',
        'cadangan' => 'Cadangan',
    ];

    public const LOGISTIC_CATEGORIES = [
        'material',
        'bangunan',
        'tenaga_kerja',
        'infrastruktur',
    ];

    public const FINANCE_CATEGORIES = [
        'tanah',
        'legalitas',
        'marketing',
        'operasional',
        'keuangan',
        'cadangan',
    ];

    protected $fillable = [
        'nama_hpp',
        'kategori',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    protected $appends = [
        'kategori_label',
    ];

    public function detailPerumahanHpps(): HasMany
    {
        return $this->hasMany(DetailPerumahanHpp::class);
    }

    public function detailRumahHppItems(): HasMany
    {
        return $this->hasMany(DetailRumahHppItem::class);
    }

    public function hppRealisasis(): HasMany
    {
        return $this->hasMany(HppRealisasi::class);
    }

    public function scopeForLogistic(Builder $query): Builder
    {
        return $query->whereIn('kategori', self::LOGISTIC_CATEGORIES);
    }

    public function scopeForFinance(Builder $query): Builder
    {
        return $query->whereIn('kategori', self::FINANCE_CATEGORIES);
    }

    public function getKategoriLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->kategori] ?? str($this->kategori)->headline()->toString();
    }

    public function optionLabel(): string
    {
        return "{$this->kategori_label} - {$this->nama_hpp}";
    }
}
