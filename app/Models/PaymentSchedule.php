<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSchedule extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['issued_at' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'calculation_snapshot' => 'array', 'locked_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }
    public function housingReservation() { return $this->belongsTo(HousingReservation::class); }
    public function qualityUpgradeContract() { return $this->belongsTo(QualityUpgradeContract::class); }

    public function source()
    {
        return $this->morphTo();
    }

    public function allocations()
    {
        return $this->hasMany(CustomerReceiptAllocation::class);
    }
}
