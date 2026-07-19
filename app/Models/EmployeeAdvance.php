<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeAdvance extends Model
{
    protected $fillable = [
        'perumahan_id', 'master_bank_id', 'advance_number', 'user_id',
        'advance_date', 'deduction_period', 'amount', 'purpose', 'status',
        'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['advance_date' => 'date', 'amount' => 'decimal:2', 'locked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(EmployeeAdvancePayrollAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function masterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class);
    }
}
