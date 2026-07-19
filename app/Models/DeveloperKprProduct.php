<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeveloperKprProduct extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['allowed_tenors' => 'array', 'requirements' => 'array', 'unit_types' => 'array', 'margin_tiers' => 'array', 'fees' => 'array', 'schedule_config' => 'array', 'penalty_config' => 'array', 'eligibility_config' => 'array', 'document_requirements' => 'array', 'handover_config' => 'array', 'advanced_config' => 'array', 'minimum_dp' => 'decimal:2', 'maximum_financing' => 'decimal:2', 'annual_margin' => 'decimal:4', 'administration_fee' => 'decimal:2', 'contract_fee' => 'decimal:2', 'effective_from' => 'date', 'effective_until' => 'date','locked_at'=>'datetime'];

    public function housings()
    {
        return $this->belongsToMany(Perumahan::class, 'developer_kpr_product_housing');
    }
}
