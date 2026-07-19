<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBatch extends Model
{
    protected $fillable = ['perumahan_id', 'master_bank_id', 'batch_number', 'period', 'payment_date', 'notes', 'total_gross', 'total_deductions', 'total_net', 'status', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'locked_at' => 'datetime', 'total_gross' => 'decimal:2', 'total_deductions' => 'decimal:2', 'total_net' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollBatchItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function masterBank(): BelongsTo
    {
        return $this->belongsTo(MasterBank::class);
    }
}
