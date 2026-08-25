<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDocumentChecklist extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = ['checklist_no', 'costumer_id', 'perumahan_id', 'process_stage', 'items', 'completion_percentage', 'validation_status', 'notes', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by'];

    protected $casts = ['items' => 'array', 'locked_at' => 'datetime'];

    public function costumer(): BelongsTo { return $this->belongsTo(Costumer::class); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
}
