<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KprMilestoneDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kpr_milestone_id',
        'uploaded_by',
        'nama_file',
        'path_file',
        'mime_type',
        'file_size',
        'keterangan',
    ];

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(KprMilestone::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
