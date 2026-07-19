<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesMethodAttempt extends Model
{
    protected $guarded = [];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function events()
    {
        return $this->hasMany(SalesStageEvent::class);
    }
}
