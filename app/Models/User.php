<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kantor_cabang_id',
        'gudang_id',
        'employee_number',
        'name',
        'job_title',
        'job_position_id',
        'join_date',
        'employment_type',
        'employment_status',
        'has_login_access',
        'attendance_pin',
        'phone',
        'tax_number',
        'bpjs_health_number',
        'bpjs_employment_number',
        'payroll_bank_name',
        'payroll_bank_account',
        'payroll_bank_holder',
        'avatar',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'attendance_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'join_date' => 'date',
            'has_login_access' => 'boolean',
            'attendance_pin' => 'hashed',
            'password' => 'hashed',
        ];
    }

    public function progressPembangunans(): HasMany
    {
        return $this->hasMany(ProgressPembangunan::class, 'users_id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function transaksiKeuangans(): HasMany
    {
        return $this->hasMany(TransaksiKeuangan::class);
    }

    public function perumahanHpps(): HasMany
    {
        return $this->hasMany(PerumahanHpp::class);
    }

    public function kantorCabang(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'kantor_cabang_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class, 'gudang_id');
    }

    public function gudangs(): BelongsToMany
    {
        return $this->belongsToMany(Gudang::class, 'gudang_user')->withTimestamps();
    }

    public function perumahans(): BelongsToMany
    {
        return $this->belongsToMany(Perumahan::class, 'perumahan_user')->withTimestamps();
    }

    public function realisasiHpps(): HasMany
    {
        return $this->hasMany(RealisasiHpp::class);
    }

    public function sprs(): HasMany
    {
        return $this->hasMany(Spr::class, 'created_by');
    }

    public function pettyCashAccounts(): HasMany
    {
        return $this->hasMany(PettyCashAccount::class, 'assigned_user_id');
    }

    public function costumers(): HasMany
    {
        return $this->hasMany(Costumer::class, 'assigned_marketing_id');
    }

    public function assignedCostumers(): HasMany
    {
        return $this->hasMany(Costumer::class, 'assigned_marketing_id');
    }

    public function marketingLeads(): HasMany
    {
        return $this->hasMany(MarketingLead::class, 'marketing_id');
    }

    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Costumer::class, 'created_by');
    }

    public function costumerFollowUps(): HasMany
    {
        return $this->hasMany(CostumerFollowUp::class, 'user_id');
    }

    public function surveySchedules(): HasMany
    {
        return $this->hasMany(MarketingSurveySchedule::class, 'marketing_id');
    }

    public function marketingVisits(): HasMany
    {
        return $this->hasMany(MarketingVisit::class, 'marketing_id');
    }

    public function marketingActionPlans(): HasMany
    {
        return $this->hasMany(MarketingActionPlan::class, 'marketing_id');
    }

    public function marketingReminders(): HasMany
    {
        return $this->hasMany(MarketingReminder::class, 'user_id');
    }

    public function kprSubmissions(): HasMany
    {
        return $this->hasMany(KprSubmission::class, 'handled_by');
    }

    public function assignedSalesWorkItems(): HasMany
    {
        return $this->hasMany(SalesWorkItem::class, 'assigned_to');
    }
}
