<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSetting extends Model
{
    protected $fillable = ['cabang_perusahaan_id', 'check_in_time', 'check_out_time', 'late_tolerance_minutes', 'checkout_tolerance_minutes', 'work_days', 'is_active', 'record_status', 'locked_at', 'locked_by'];
    protected function casts(): array { return ['work_days' => 'array', 'is_active' => 'boolean', 'locked_at' => 'datetime']; }
    public function branch(): BelongsTo { return $this->belongsTo(CabangPerusahaan::class, 'cabang_perusahaan_id'); }
}
