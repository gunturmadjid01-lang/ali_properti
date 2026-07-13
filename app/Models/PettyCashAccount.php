<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashAccount extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'branch_id', 'target_amount', 'balance', 'minimum_balance', 'status', 'created_by', 'updated_by'];

    protected $casts = ['target_amount' => 'decimal:2', 'balance' => 'decimal:2', 'minimum_balance' => 'decimal:2'];

    public function branch(): BelongsTo { return $this->belongsTo(CabangPerusahaan::class, 'branch_id'); }
    public function fundings(): HasMany { return $this->hasMany(PettyCashFunding::class); }
    public function expenses(): HasMany { return $this->hasMany(PettyCashExpense::class); }
    public function ledgers(): HasMany { return $this->hasMany(PettyCashLedger::class); }
}
