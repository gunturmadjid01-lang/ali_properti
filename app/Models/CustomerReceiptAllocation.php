<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceiptAllocation extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2'];

    public function receipt()
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id');
    }

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }
}
