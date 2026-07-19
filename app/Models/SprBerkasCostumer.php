<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SprBerkasCostumer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spr_id',
        'dokumen_costumer_id',
        'customer_document_id',
        'is_selected',
        'uploaded_by',
        'nama_file',
        'path_file',
        'mime_type',
        'file_size',
        'keterangan',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_selected' => 'boolean',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenCostumer::class, 'dokumen_costumer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function repositoryDocument(): BelongsTo
    {
        return $this->belongsTo(CustomerDocument::class, 'customer_document_id');
    }
}
