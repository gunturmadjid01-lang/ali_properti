<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterBillingPeriod extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $guarded = [];
    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'amount' => 'decimal:2', 'is_active' => 'boolean', 'locked_at' => 'datetime'];

    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function payments(): HasMany { return $this->hasMany(WaterPayment::class); }
}
