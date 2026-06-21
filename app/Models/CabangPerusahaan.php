<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CabangPerusahaan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_cabang',
        'nama_cabang',
        'address',
        'phone',
        'latitude',
        'longtitude',
        'logo',
        'image',
        'deskripsi',
        'emaiil',
        'manager_name',
        'status',
        'type',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function perumahans(): HasMany
    {
        return $this->hasMany(Perumahan::class, 'cabang_id');
    }

    public function transaksiKeuangans(): HasMany
    {
        return $this->hasMany(TransaksiKeuangan::class, 'cabang_id');
    }
}
