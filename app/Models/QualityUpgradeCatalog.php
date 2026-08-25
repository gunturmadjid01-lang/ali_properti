<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityUpgradeCatalog extends Model
{
    protected $guarded = [];
    protected $casts = [
        'standard_price' => 'float',
        'estimated_material_cost' => 'float',
        'estimated_labor_cost' => 'float',
        'estimated_other_cost' => 'float',
        'is_active' => 'boolean',
    ];
}
