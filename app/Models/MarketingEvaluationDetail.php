<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingEvaluationDetail extends Model
{
    protected $guarded = [];
    protected $casts = ['weight' => 'float', 'achievement' => 'float', 'score' => 'float', 'evidence' => 'array'];
    public function evaluation() { return $this->belongsTo(MarketingEvaluation::class, 'marketing_evaluation_id'); }
}
