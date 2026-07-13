<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BarangMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori_material',
        'jenis_material',
        'merk_material',
        'satuan',
        'harga_hpp',
        'stok_minimum',
        'catatan',
        'status',
    ];

    protected $casts = [
        'harga_hpp' => 'float',
        'stok_minimum' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $material): void {
            if (filled($material->kode_barang)) {
                return;
            }

            $material->kode_barang = static::nextKodeBarang();
        });
    }

    public function stokMaterials(): HasMany
    {
        return $this->hasMany(StokMaterial::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(MaterialPriceHistory::class);
    }

    public static function nextKodeBarang(): string
    {
        return 'MAT-'.Str::padLeft((string) (static::withTrashed()->count() + 1), 5, '0');
    }
}
