<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashFunding extends Model
{
    use SoftDeletes;

    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const DISBURSED = 'disbursed';

    protected $fillable = ['petty_cash_account_id', 'number', 'type', 'request_date', 'amount', 'status', 'record_status', 'request_notes', 'request_proof_path', 'requested_by', 'submitted_at', 'locked_at', 'locked_by', 'approved_by', 'approved_at', 'approval_proof_path', 'approval_notes', 'rejection_notes'];

    protected $casts = ['request_date' => 'date', 'amount' => 'decimal:2', 'submitted_at' => 'datetime', 'locked_at' => 'datetime', 'approved_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(PettyCashAccount::class, 'petty_cash_account_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function ledger(): MorphOne
    {
        return $this->morphOne(PettyCashLedger::class, 'source');
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'model')->latestOfMany();
    }
}
