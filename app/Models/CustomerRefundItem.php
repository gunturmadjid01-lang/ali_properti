<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRefundItem extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2'];
    public function refund() { return $this->belongsTo(CustomerRefund::class, 'customer_refund_id'); }
    public function schedule() { return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id'); }
}
