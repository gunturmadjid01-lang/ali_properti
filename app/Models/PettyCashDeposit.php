<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PettyCashDeposit extends Model
{
    protected $fillable = ['petty_cash_account_id', 'master_bank_id', 'number', 'deposit_date', 'amount', 'status', 'record_status', 'proof_path', 'notes', 'deposited_at', 'deposited_by', 'locked_at', 'locked_by', 'created_by', 'updated_by'];

    protected $casts = ['deposit_date' => 'date', 'amount' => 'decimal:2', 'deposited_at' => 'datetime', 'locked_at' => 'datetime'];

    public function account(): BelongsTo { return $this->belongsTo(PettyCashAccount::class, 'petty_cash_account_id'); }
    public function masterBank(): BelongsTo { return $this->belongsTo(MasterBank::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function depositor(): BelongsTo { return $this->belongsTo(User::class, 'deposited_by'); }
    public function approvalRequest(): MorphOne { return $this->morphOne(ApprovalRequest::class, 'model')->ofMany('id', 'max'); }
    public function ledger(): MorphOne { return $this->morphOne(PettyCashLedger::class, 'source'); }
}
