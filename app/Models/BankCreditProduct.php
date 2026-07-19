<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankCreditProduct extends Model
{
    use SoftDeletes;

    protected $fillable = ['bank_kredit_id', 'bank_branch_id', 'product_code', 'product_name', 'product_type', 'subsidy_type', 'scheme_type', 'minimum_ceiling', 'maximum_ceiling', 'minimum_down_payment', 'maximum_tenor_months', 'indicative_interest_margin', 'provision_fee', 'administration_fee', 'appraisal_fee', 'insurance_fee', 'notary_fee', 'disbursement_method', 'estimated_sla_days', 'effective_from', 'effective_until', 'current_version', 'status', 'notes', 'record_status', 'locked_at', 'locked_by'];

    protected $casts = ['minimum_ceiling' => 'float', 'maximum_ceiling' => 'float', 'minimum_down_payment' => 'float', 'indicative_interest_margin' => 'float', 'provision_fee' => 'float', 'administration_fee' => 'float', 'appraisal_fee' => 'float', 'insurance_fee' => 'float', 'notary_fee' => 'float', 'effective_from' => 'date', 'effective_until' => 'date', 'locked_at' => 'datetime'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankKredit::class, 'bank_kredit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BankCreditProductVersion::class)->orderByDesc('version_number');
    }
}
