<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingVisit extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = ['visit_no', 'costumer_id', 'marketing_lead_id', 'contact_name', 'contact_phone', 'organization_name', 'lead_source_note', 'marketing_id', 'perumahan_id', 'planned_at', 'started_at', 'finished_at', 'visit_type', 'status', 'location', 'latitude', 'longitude', 'location_accuracy_m', 'location_captured_at', 'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_m', 'check_in_photo_path', 'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_m', 'check_out_photo_path', 'device_info', 'verification_status', 'verification_note', 'verified_by', 'verified_at', 'admin_review_status', 'admin_review_note', 'admin_reviewed_by', 'admin_reviewed_at', 'objective', 'customer_response', 'objections', 'result', 'interest_level', 'next_action', 'next_action_at', 'evidence_path', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by'];

    protected $casts = ['planned_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'next_action_at' => 'datetime', 'location_captured_at' => 'datetime', 'verified_at' => 'datetime', 'admin_reviewed_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'check_in_latitude' => 'decimal:7', 'check_in_longitude' => 'decimal:7', 'check_out_latitude' => 'decimal:7', 'check_out_longitude' => 'decimal:7', 'locked_at' => 'datetime'];

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function marketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'marketing_lead_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MarketingActivityContact::class);
    }
}
