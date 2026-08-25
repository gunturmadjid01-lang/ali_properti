<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityUpgradeDefect extends Model
{
    protected $guarded = [];
    protected $casts = ['reported_date' => 'date', 'target_date' => 'date', 'resolved_at' => 'datetime'];
    public function contract() { return $this->belongsTo(QualityUpgradeContract::class, 'quality_upgrade_contract_id'); }
    public function item() { return $this->belongsTo(QualityUpgradeContractItem::class, 'quality_upgrade_contract_item_id'); }
}
