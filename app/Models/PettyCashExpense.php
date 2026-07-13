<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashExpense extends Model
{
    use SoftDeletes;

    protected $fillable = ['petty_cash_account_id', 'number', 'expense_date', 'category', 'cost_type', 'perumahan_id', 'detail_rumah_id', 'kelompok_hpp_id', 'tahapan_pembangunan_id', 'amount', 'description', 'proof_path', 'created_by'];

    protected $casts = ['expense_date' => 'date', 'amount' => 'decimal:2'];

    public function account(): BelongsTo { return $this->belongsTo(PettyCashAccount::class, 'petty_cash_account_id'); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function ledger(): MorphOne { return $this->morphOne(PettyCashLedger::class, 'source'); }
}
