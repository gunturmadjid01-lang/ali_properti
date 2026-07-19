<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KprSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_kpr',
        'spr_id',
        'bank_kredit_id',
        'bank_branch_id',
        'bank_credit_product_id',
        'bank_credit_product_version_id',
        'bank_product_snapshot',
        'handled_by',
        'tanggal_pengajuan',
        'nilai_pengajuan',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'nilai_pengajuan' => 'float',
        'bank_product_snapshot' => 'array',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankKredit::class, 'bank_kredit_id');
    }

    public function bankBranch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class);
    }

    public function bankCreditProduct(): BelongsTo
    {
        return $this->belongsTo(BankCreditProduct::class);
    }

    public function bankCreditProductVersion(): BelongsTo
    {
        return $this->belongsTo(BankCreditProductVersion::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(KprFollowUp::class);
    }

    public function berkasCostumers(): HasMany
    {
        return $this->hasMany(BerkasCostumer::class, 'kpr_submission_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(KprStageHistory::class);
    }

    public function financing()
    {
        return $this->hasOne(BankKprFinancing::class);
    }

    public function disbursements()
    {
        return $this->hasMany(BankKprDisbursement::class);
    }
}
