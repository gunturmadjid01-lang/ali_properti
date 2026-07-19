<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankHousingPartnershipVersion extends Model
{
    protected $fillable = ['bank_housing_partnership_id', 'version_number', 'agreement_snapshot', 'effective_from', 'effective_until', 'created_by'];

    protected $casts = ['agreement_snapshot' => 'array', 'effective_from' => 'date', 'effective_until' => 'date'];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(BankHousingPartnership::class, 'bank_housing_partnership_id');
    }
}
