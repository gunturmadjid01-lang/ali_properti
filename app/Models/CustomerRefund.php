<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerRefund extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['eligible_amount' => 'decimal:2', 'penalty_amount' => 'decimal:2', 'refund_amount' => 'decimal:2', 'refund_date' => 'date', 'locked_at' => 'datetime', 'approved_at' => 'datetime'];

    public function resolution() { return $this->belongsTo(SalesResolutionRequest::class, 'sales_resolution_request_id'); }
    public function salesTransaction() { return $this->belongsTo(SalesTransaction::class); }
    public function bankAccount() { return $this->belongsTo(MasterBank::class, 'master_bank_id'); }
    public function items() { return $this->hasMany(CustomerRefundItem::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approvalRequests() { return $this->morphMany(ApprovalRequest::class, 'model')->latest('id'); }
}
