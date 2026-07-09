<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tukang extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    public const POSITIONS = [
        'mandor' => 'Mandor',
        'kepala_tukang' => 'Kepala Tukang',
        'tukang' => 'Tukang',
        'kenek' => 'Kenek / Pembantu Tukang',
    ];

    protected $fillable = [
        'nama',
        'alamat',
        'posisi',
        'gaji',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gaji' => 'decimal:2',
        ];
    }

    public function daftarGaji(): HasMany
    {
        return $this->hasMany(TukangGaji::class)->latest('tanggal_berlaku')->latest('id');
    }

    public function gajiAktif(): HasOne
    {
        return $this->hasOne(TukangGaji::class)
            ->where('status', 'aktif')
            ->latestOfMany('tanggal_berlaku');
    }
}
