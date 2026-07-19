<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialBrand extends Model
{
    protected $fillable = ['code', 'name', 'description', 'status'];

    public function materials(): HasMany
    {
        return $this->hasMany(BarangMaterial::class);
    }
}
