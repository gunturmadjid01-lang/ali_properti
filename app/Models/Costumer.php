<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Costumer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_costumer',
        'source_marketing_lead_id',
        'customer_stage',
        'created_by',
        'updated_by',
        'perumahan_id',
        'assigned_marketing_id',
        'assigned_at',
        'lead_received_at',
        'first_response_due_at',
        'lead_priority',
        'interest_level',
        'budget_min',
        'budget_max',
        'preferred_payment_method',
        'first_contacted_at',
        'last_activity_at',
        'next_action_at',
        'lost_reason',
        'cancellation_reason',
        'marketing_lead_source_id',
        'marketing_campaign_id',
        'lead_ownership_type',
        'lead_source_channel',
        'lead_verification_status',
        'lead_verification_note',
        'lead_verified_by',
        'lead_verified_at',
        'assignment_status',
        'admin_sales_id',
        'status_lead',
        'nama',
        'jenis_kelamin',
        'jenis_identitas',
        'no_identitas',
        'tanggal_lahir',
        'tempat_lahir',
        'status_perkawinan',
        'alamat',
        'email',
        'npwp',
        'telepon',
        'file_identitas',
        'penghasilan',
        'pengeluaran_bulanan',
        'keterangan',
        'pekerjaan',
        'employment_category',
        'nama_perusahaan',
        'alamat_perusahaan',
        'telepon_perusahaan',
        'keterangan_perusahaan',
        'nama_lengkap_pasangan',
        'jenis_kelamin_pasangan',
        'jenis_identitas_pasangan',
        'no_identitas_pasangan',
        'tanggal_lahir_pasangan',
        'tempat_lahir_pasangan',
        'pekerjaan_pasangan',
        'penghasilan_pasangan',
        'pengeluaran_bulanan_pasangan',
        'daftar_cicilan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_lahir_pasangan' => 'date',
        'penghasilan' => 'float',
        'pengeluaran_bulanan' => 'float',
        'penghasilan_pasangan' => 'float',
        'pengeluaran_bulanan_pasangan' => 'float',
        'daftar_cicilan' => 'array',
        'assigned_at' => 'datetime',
        'lead_received_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'first_contacted_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'next_action_at' => 'datetime',
        'budget_min' => 'float',
        'budget_max' => 'float',
        'lead_verified_at' => 'datetime',
    ];

    public function followUps(): HasMany
    {
        return $this->hasMany(CostumerFollowUp::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(MarketingLeadSource::class, 'marketing_lead_source_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function surveySchedules(): HasMany
    {
        return $this->hasMany(MarketingSurveySchedule::class);
    }

    public function leadActivities(): HasMany
    {
        return $this->hasMany(MarketingLeadActivity::class);
    }

    public function sprs(): HasMany
    {
        return $this->hasMany(Spr::class);
    }

    public function housingReservations(): HasMany
    {
        return $this->hasMany(HousingReservation::class);
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function unitOwnerships(): HasMany
    {
        return $this->hasMany(UnitOwnership::class);
    }

    public function unitInterests(): HasMany
    {
        return $this->hasMany(CustomerUnitInterest::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(MarketingReminder::class);
    }

    public function assignedMarketing(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_marketing_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(MarketingVisit::class);
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(MarketingActionPlan::class);
    }

    public function documentChecklists(): HasMany
    {
        return $this->hasMany(CustomerDocumentChecklist::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function adminSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_sales_id');
    }

    public function leadVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_verified_by');
    }

    public function salesWorkItems(): HasMany
    {
        return $this->hasMany(SalesWorkItem::class, 'costumer_id');
    }

    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'source_marketing_lead_id');
    }
}
