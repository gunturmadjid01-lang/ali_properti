<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesProcessChecklistItem extends Model
{
    protected $guarded = [];

    protected $casts = ['is_required' => 'boolean', 'is_completed' => 'boolean', 'completed_at' => 'datetime'];

    public function step()
    {
        return $this->belongsTo(SalesProcessStep::class, 'sales_process_step_id');
    }
}
