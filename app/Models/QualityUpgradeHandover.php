<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityUpgradeHandover extends Model
{
    protected $guarded = [];
    protected $casts = ['handover_date' => 'date', 'final_progress_percent' => 'float', 'checklist' => 'array', 'locked_at' => 'datetime', 'approved_at' => 'datetime'];
    public function contract() { return $this->belongsTo(QualityUpgradeContract::class, 'quality_upgrade_contract_id'); }
}
