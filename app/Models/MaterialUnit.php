<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialUnit extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'description', 'status'];

    public function materials(): HasMany
    {
        return $this->hasMany(BarangMaterial::class, 'base_unit_id');
    }
}
