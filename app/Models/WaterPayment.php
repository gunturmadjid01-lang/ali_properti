<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterPayment extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $guarded = [];
    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2', 'locked_at' => 'datetime'];

    public function period(): BelongsTo { return $this->belongsTo(WaterBillingPeriod::class, 'water_billing_period_id'); }
    public function ownership(): BelongsTo { return $this->belongsTo(UnitOwnership::class, 'unit_ownership_id'); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function unit(): BelongsTo { return $this->belongsTo(DetailRumah::class, 'detail_rumah_id'); }
}
