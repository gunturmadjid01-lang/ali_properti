<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankKprDisbursement extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['disbursement_date' => 'date', 'amount' => 'decimal:2', 'locked_at' => 'datetime'];

    public function submission()
    {
        return $this->belongsTo(KprSubmission::class, 'kpr_submission_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(MasterBank::class, 'master_bank_id');
    }

    public function customerReceipt()
    {
        return $this->belongsTo(CustomerReceipt::class);
    }
}
