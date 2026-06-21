<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BerkasCostumer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kpr_submission_id',
        'dokumen_costumer_id',
        'uploaded_by',
        'nama_file',
        'path_file',
        'mime_type',
        'file_size',
        'keterangan',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'locked_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KprSubmission::class, 'kpr_submission_id');
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenCostumer::class, 'dokumen_costumer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
