<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNurtureStep extends Model
{
    protected $guarded = [];
    protected $casts = ['stop_on_contact' => 'boolean'];
    public function sequence(): BelongsTo { return $this->belongsTo(CrmNurtureSequence::class, 'sequence_id'); }
}
