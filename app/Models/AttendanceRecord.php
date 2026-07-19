<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'user_id', 'cabang_perusahaan_id', 'attendance_date', 'type', 'recorded_at',
        'latitude', 'longitude', 'accuracy_meters', 'distance_meters', 'is_within_radius',
        'outside_radius_confirmed', 'time_status', 'schedule_difference_minutes', 'photo_path',
        'ip_address', 'user_agent', 'record_status', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'recorded_at' => 'datetime',
            'locked_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'distance_meters' => 'decimal:2',
            'is_within_radius' => 'boolean',
            'outside_radius_confirmed' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function branch(): BelongsTo { return $this->belongsTo(CabangPerusahaan::class, 'cabang_perusahaan_id'); }
}
