<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCharge extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2', 'charge_date' => 'date', 'due_date' => 'date',
        'locked_at' => 'datetime', 'approved_at' => 'datetime', 'reversed_at' => 'datetime',
    ];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(MasterBank::class, 'master_bank_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function reversalJournal()
    {
        return $this->belongsTo(Journal::class, 'reversal_journal_id');
    }

    public function invoice()
    {
        return $this->morphOne(PaymentSchedule::class, 'source');
    }
}
