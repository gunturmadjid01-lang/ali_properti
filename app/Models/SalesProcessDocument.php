<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesProcessDocument extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['document_date' => 'date', 'expires_at' => 'date', 'validated_at' => 'datetime'];

    public function step()
    {
        return $this->belongsTo(SalesProcessStep::class, 'sales_process_step_id');
    }
}
