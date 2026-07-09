<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteScheduleAllocation extends Model
{
    protected $fillable = [
        'site_schedule_id',
        'periode_ke',
        'label_periode',
        'bobot_persen',
    ];

    protected $casts = [
        'bobot_persen' => 'float',
    ];

    public function siteSchedule(): BelongsTo
    {
        return $this->belongsTo(SiteSchedule::class);
    }
}
