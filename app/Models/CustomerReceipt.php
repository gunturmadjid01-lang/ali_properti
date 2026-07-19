<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReceipt extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2', 'locked_at' => 'datetime', 'approved_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }
    public function housingReservation() { return $this->belongsTo(HousingReservation::class); }

    public function bankAccount()
    {
        return $this->belongsTo(MasterBank::class, 'master_bank_id');
    }

    public function allocations()
    {
        return $this->hasMany(CustomerReceiptAllocation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}
