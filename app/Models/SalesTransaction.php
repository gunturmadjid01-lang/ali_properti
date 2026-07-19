<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesTransaction extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['sale_price_snapshot' => 'decimal:2', 'party_snapshot' => 'array', 'payment_snapshot' => 'array', 'approved_at' => 'datetime', 'closed_at' => 'datetime'];

    public function spr()
    {
        return $this->belongsTo(Spr::class);
    }

    public function customer()
    {
        return $this->belongsTo(Costumer::class, 'costumer_id');
    }

    public function housingProject()
    {
        return $this->belongsTo(Perumahan::class, 'perumahan_id');
    }

    public function housingUnit()
    {
        return $this->belongsTo(DetailRumah::class, 'detail_rumah_id');
    }

    public function marketing()
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    public function paymentSchedules()
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function cashInstallmentContract()
    {
        return $this->hasOne(CashInstallmentContract::class);
    }

    public function developerKprApplication()
    {
        return $this->hasOne(DeveloperKprApplication::class);
    }

    public function customerReceipts()
    {
        return $this->hasMany(CustomerReceipt::class);
    }

    public function workflowHistories()
    {
        return $this->hasMany(SalesWorkflowHistory::class);
    }

    public function processSteps()
    {
        return $this->hasMany(SalesProcessStep::class)->orderBy('sequence');
    }

    public function paymentSubmissions()
    {
        return $this->hasMany(SalesMethodAttempt::class);
    }
}
