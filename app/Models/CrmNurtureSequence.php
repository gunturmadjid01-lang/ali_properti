<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmNurtureSequence extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function steps(): HasMany { return $this->hasMany(CrmNurtureStep::class, 'sequence_id')->orderBy('step_order'); }
    public function enrollments(): HasMany { return $this->hasMany(CrmNurtureEnrollment::class, 'sequence_id'); }
}
