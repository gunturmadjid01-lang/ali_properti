<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashInstallmentContract extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['scheme_snapshot' => 'array', 'contract_value' => 'decimal:2', 'start_date' => 'date', 'locked_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }
}
