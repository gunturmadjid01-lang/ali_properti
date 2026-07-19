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
        'created_by',
        'updated_by',
        'perumahan_id',
        'marketing_lead_source_id',
        'marketing_campaign_id',
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

    public function unitOwnerships(): HasMany
    {
        return $this->hasMany(UnitOwnership::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(MarketingReminder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
