<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankHousingPartnership extends Model
{
    use SoftDeletes;

    protected $fillable = ['bank_kredit_id', 'bank_branch_id', 'perumahan_id', 'agreement_number', 'agreement_name', 'effective_from', 'effective_until', 'current_version', 'status', 'notes', 'record_status', 'locked_at', 'locked_by'];

    protected $casts = ['effective_from' => 'date', 'effective_until' => 'date', 'locked_at' => 'datetime'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankKredit::class, 'bank_kredit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch_id');
    }

    public function housing(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class, 'perumahan_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BankHousingPartnershipVersion::class)->orderByDesc('version_number');
    }
}
