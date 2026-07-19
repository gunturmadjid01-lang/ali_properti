<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesStageEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['occurred_at' => 'datetime'];

    public function attempt()
    {
        return $this->belongsTo(SalesMethodAttempt::class, 'sales_method_attempt_id');
    }
}
