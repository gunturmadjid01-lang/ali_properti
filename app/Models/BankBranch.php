<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankBranch extends Model
{
    use SoftDeletes;

    protected $fillable = ['bank_kredit_id', 'branch_code', 'branch_name', 'address', 'city', 'pic_name', 'pic_position', 'phone', 'email', 'status', 'record_status', 'locked_at', 'locked_by'];

    protected $casts = ['locked_at' => 'datetime'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankKredit::class, 'bank_kredit_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(BankCreditProduct::class);
    }
}
