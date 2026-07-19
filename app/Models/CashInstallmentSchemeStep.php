<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashInstallmentSchemeStep extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['value' => 'decimal:4'];

    public function scheme()
    {
        return $this->belongsTo(CashInstallmentScheme::class, 'cash_installment_scheme_id');
    }
}
