<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankCreditProductVersion extends Model
{
    protected $fillable = ['bank_credit_product_id', 'version_number', 'terms_snapshot', 'effective_from', 'effective_until', 'created_by'];

    protected $casts = ['terms_snapshot' => 'array', 'effective_from' => 'date', 'effective_until' => 'date'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(BankCreditProduct::class, 'bank_credit_product_id');
    }
}
