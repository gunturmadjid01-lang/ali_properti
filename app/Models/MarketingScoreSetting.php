<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingScoreSetting extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $guarded = [];

    protected $casts = ['weight' => 'float', 'is_active' => 'boolean', 'locked_at' => 'datetime'];

    public function latestApproval()
    {
        return $this->morphOne(ApprovalRequest::class, 'model')->ofMany('id', 'max')->where('module_key', 'marketing-score-setting');
    }
}
