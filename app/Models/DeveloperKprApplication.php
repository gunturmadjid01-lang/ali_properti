<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeveloperKprApplication extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['product_snapshot' => 'array', 'financing_amount' => 'decimal:2', 'estimated_installment' => 'decimal:2', 'locked_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }
}
