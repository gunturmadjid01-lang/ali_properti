<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EmployeeAdvancePayrollAllocation extends Model{protected $fillable=['employee_advance_id','payroll_batch_item_id','amount'];protected function casts():array{return['amount'=>'decimal:2'];}public function advance():BelongsTo{return $this->belongsTo(EmployeeAdvance::class,'employee_advance_id');}public function payrollItem():BelongsTo{return $this->belongsTo(PayrollBatchItem::class,'payroll_batch_item_id');}}
