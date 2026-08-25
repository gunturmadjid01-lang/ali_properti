<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingEvaluation extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $guarded = [];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'total_score' => 'float', 'locked_at' => 'datetime'];

    public function marketing() { return $this->belongsTo(User::class, 'marketing_id'); }
    public function perumahan() { return $this->belongsTo(Perumahan::class); }
    public function details() { return $this->hasMany(MarketingEvaluationDetail::class); }
    public function latestApproval() { return $this->morphOne(ApprovalRequest::class, 'model')->ofMany('id', 'max')->where('module_key', 'marketing-evaluation'); }
}
