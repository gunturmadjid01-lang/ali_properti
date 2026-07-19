<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollBatchItem extends Model
{
    protected $fillable = ['user_id', 'employee_number', 'employee_name', 'job_position', 'basic_salary', 'fixed_allowance', 'other_allowance', 'deductions', 'advance_deduction', 'net_salary', 'notes'];
    protected function casts(): array { return ['basic_salary' => 'decimal:2', 'fixed_allowance' => 'decimal:2', 'other_allowance' => 'decimal:2', 'deductions' => 'decimal:2', 'advance_deduction'=>'decimal:2', 'net_salary' => 'decimal:2']; }
    public function batch(): BelongsTo { return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
