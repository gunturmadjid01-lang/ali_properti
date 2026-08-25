<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityUpgradeProgress extends Model
{
    protected $guarded = [];
    protected $casts = [
        'report_date' => 'date',
        'progress_percent' => 'float',
        'material_cost' => 'float',
        'labor_cost' => 'float',
        'other_cost' => 'float',
    ];

    public function item() { return $this->belongsTo(QualityUpgradeContractItem::class, 'quality_upgrade_contract_item_id'); }
    public function inspector() { return $this->belongsTo(User::class, 'inspected_by'); }
}
