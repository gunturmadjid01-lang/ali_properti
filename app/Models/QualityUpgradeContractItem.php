<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityUpgradeContractItem extends Model
{
    protected $guarded = [];
    protected $casts = ['volume' => 'float', 'unit_price' => 'float', 'discount' => 'float', 'total' => 'float', 'progress_percent' => 'float', 'material_cost' => 'float', 'labor_cost' => 'float', 'other_cost' => 'float', 'estimated_material_cost' => 'float', 'estimated_labor_cost' => 'float', 'estimated_other_cost' => 'float'];

    public function contract(): BelongsTo { return $this->belongsTo(QualityUpgradeContract::class, 'quality_upgrade_contract_id'); }
    public function catalog(): BelongsTo { return $this->belongsTo(QualityUpgradeCatalog::class, 'quality_upgrade_catalog_id'); }
}
