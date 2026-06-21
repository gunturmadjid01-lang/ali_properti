<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gudang extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_gudang',
        'nama_gudang',
        'cabang_id',
        'perumahan_id',
        'penanggung_jawab',
        'phone',
        'alamat',
        'catatan',
        'status',
        'created_by',
        'updated_by',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function stokMaterials(): HasMany
    {
        return $this->hasMany(StokMaterial::class);
    }
}
