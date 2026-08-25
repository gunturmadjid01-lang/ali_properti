<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityUpgradeContract extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'contract_date' => 'date',
        'planned_start_date' => 'date',
        'planned_finish_date' => 'date',
        'subtotal' => 'float',
        'discount' => 'float',
        'tax_amount' => 'float',
        'contract_value' => 'float',
        'down_payment' => 'float',
        'company_snapshot' => 'array',
        'customer_snapshot' => 'array',
        'unit_snapshot' => 'array',
        'payment_snapshot' => 'array',
        'locked_at' => 'datetime',
        'approved_at' => 'datetime',
        'progress_percent' => 'float',
        'actual_material_cost' => 'float',
        'actual_labor_cost' => 'float',
        'actual_other_cost' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'warranty_end_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Costumer::class, 'costumer_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(DetailRumah::class, 'detail_rumah_id'); }
    public function spr(): BelongsTo { return $this->belongsTo(Spr::class); }
    public function company(): BelongsTo { return $this->belongsTo(CabangPerusahaan::class, 'company_id'); }
    public function bankAccount(): BelongsTo { return $this->belongsTo(MasterBank::class, 'master_bank_id'); }
    public function items(): HasMany { return $this->hasMany(QualityUpgradeContractItem::class); }
    public function schedules(): HasMany { return $this->hasMany(PaymentSchedule::class); }
    public function receipts(): HasMany { return $this->hasMany(CustomerReceipt::class); }
    public function progresses(): HasMany { return $this->hasMany(QualityUpgradeProgress::class); }
    public function addenda(): HasMany { return $this->hasMany(QualityUpgradeAddendum::class); }
    public function materialUsages(): HasMany { return $this->hasMany(MaterialUsage::class); }
    public function handover() { return $this->hasOne(QualityUpgradeHandover::class); }
    public function defects(): HasMany { return $this->hasMany(QualityUpgradeDefect::class); }
}
