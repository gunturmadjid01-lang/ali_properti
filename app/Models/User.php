<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'kantor_cabang_id',
        'name',
        'phone',
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
            'password' => 'hashed',
        ];
    }

    public function progressPembangunans(): HasMany
    {
        return $this->hasMany(ProgressPembangunan::class, 'users_id');
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

    public function costumers(): HasMany
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

    public function marketingReminders(): HasMany
    {
        return $this->hasMany(MarketingReminder::class, 'user_id');
    }

    public function kprSubmissions(): HasMany
    {
        return $this->hasMany(KprSubmission::class, 'handled_by');
    }
}
