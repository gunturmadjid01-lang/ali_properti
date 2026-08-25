<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityUpgradeAddendum extends Model
{
    protected $table = 'quality_upgrade_addenda';

    protected $guarded = [];
    protected $casts = ['addendum_date' => 'date', 'finish_date_change' => 'date', 'billing_due_date' => 'date', 'value_change' => 'float', 'change_snapshot' => 'array', 'locked_at' => 'datetime', 'applied_at' => 'datetime'];

    public function contract() { return $this->belongsTo(QualityUpgradeContract::class, 'quality_upgrade_contract_id'); }
}
