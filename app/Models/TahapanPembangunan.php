<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahapanPembangunan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nama_tahapan', 'bobot_persen', 'urutan', 'status'];

    protected $casts = [
        'bobot_persen' => 'float',
        'urutan' => 'integer',
    ];

    public function progressPembangunans(): HasMany
    {
        return $this->hasMany(ProgressPembangunan::class);
    }
}
