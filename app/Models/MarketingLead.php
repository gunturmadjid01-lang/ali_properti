<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingLead extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['first_contacted_at' => 'datetime', 'last_activity_at' => 'datetime', 'next_action_at' => 'datetime', 'qualified_at' => 'datetime', 'converted_at' => 'datetime', 'verified_at' => 'datetime', 'assigned_at' => 'datetime', 'first_response_due_at' => 'datetime', 'submitted_for_verification_at' => 'datetime', 'consent_channels' => 'array', 'consent_at' => 'datetime', 'do_not_contact' => 'boolean', 'recycle_at' => 'datetime', 'merged_at' => 'datetime', 'duplicate_checked_at' => 'datetime', 'budget_min' => 'decimal:2', 'budget_max' => 'decimal:2'];

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_id');
    }

    public function adminSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_sales_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(MarketingLeadSource::class, 'lead_source_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class, 'detail_rumah_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_perusahaan_id');
    }

    public function sourceVisit(): BelongsTo
    {
        return $this->belongsTo(MarketingVisit::class, 'source_visit_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class, 'converted_costumer_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CostumerFollowUp::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MarketingLeadAssignment::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(SalesWorkItem::class);
    }

    public function possibleDuplicate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'possible_duplicate_lead_id');
    }

    public function duplicateChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'duplicate_checked_by');
    }
}
