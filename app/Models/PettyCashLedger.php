<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PettyCashLedger extends Model
{
    protected $fillable = ['petty_cash_account_id', 'transaction_date', 'direction', 'amount', 'balance_after', 'source_type', 'source_id', 'description', 'created_by'];

    protected $casts = ['transaction_date' => 'date', 'amount' => 'decimal:2', 'balance_after' => 'decimal:2'];

    public function account(): BelongsTo { return $this->belongsTo(PettyCashAccount::class, 'petty_cash_account_id'); }
    public function source(): MorphTo { return $this->morphTo(); }
}
