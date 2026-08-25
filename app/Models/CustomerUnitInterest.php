<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUnitInterest extends Model
{
    protected $fillable = [
        'costumer_id',
        'detail_rumah_id',
        'perumahan_id',
        'interest_level',
        'payment_plan',
        'budget_min',
        'budget_max',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'budget_min' => 'float',
        'budget_max' => 'float',
    ];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class, 'detail_rumah_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }
}
