<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarangMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori_material',
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

    public function stokMaterials(): HasMany
    {
        return $this->hasMany(StokMaterial::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(MaterialPriceHistory::class);
    }
}
