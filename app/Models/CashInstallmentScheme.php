<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashInstallmentScheme extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['minimum_booking_fee' => 'decimal:2', 'minimum_dp' => 'decimal:2', 'penalty_value' => 'decimal:4', 'requirements' => 'array', 'unit_types' => 'array', 'schedule_config' => 'array', 'penalty_config' => 'array', 'handover_config' => 'array', 'document_requirements' => 'array', 'advanced_config' => 'array', 'effective_from' => 'date', 'effective_until' => 'date','locked_at'=>'datetime'];

    public function steps()
    {
        return $this->hasMany(CashInstallmentSchemeStep::class)->orderBy('sequence');
    }

    public function housings()
    {
        return $this->belongsToMany(Perumahan::class, 'cash_installment_scheme_housing');
    }
}
