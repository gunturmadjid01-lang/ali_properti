<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousingReservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'booking_fee' => 'decimal:2', 'paid_amount' => 'decimal:2',
        'reserved_at' => 'datetime',
        'paid_at' => 'datetime', 'payment_submitted_at' => 'date', 'locked_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime',
        'booking_fee_snapshot' => 'array',
    ];

    public function customer() { return $this->belongsTo(Costumer::class, 'costumer_id'); }
    public function unit() { return $this->belongsTo(DetailRumah::class, 'detail_rumah_id'); }
    public function spr() { return $this->belongsTo(Spr::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function canceller() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function approvalRequests() { return $this->morphMany(ApprovalRequest::class, 'model'); }
    public function latestApproval() { return $this->morphOne(ApprovalRequest::class, 'model')->ofMany('id', 'max')->where('module_key', 'housing-reservation'); }
    public function paymentSchedule() { return $this->hasOne(PaymentSchedule::class); }
    public function receipts() { return $this->hasMany(CustomerReceipt::class); }
    public function fundBank() { return $this->belongsTo(MasterBank::class, 'fund_master_bank_id'); }
    public function pettyCashAccount() { return $this->belongsTo(PettyCashAccount::class); }
}
