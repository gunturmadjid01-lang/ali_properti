<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOwnership extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'detail_rumah_id', 'costumer_id', 'spr_id', 'source_type', 'source_id',
        'acquisition_method', 'acquired_at', 'ended_at', 'owner_name', 'identity_type',
        'identity_number', 'phone', 'email', 'address', 'spouse_name', 'document_number',
        'attachment_path', 'notes', 'is_active', 'record_status', 'locked_at', 'locked_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'acquired_at' => 'date',
        'ended_at' => 'date',
        'is_active' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function costumer(): BelongsTo
    {
        return $this->belongsTo(Costumer::class);
    }

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
