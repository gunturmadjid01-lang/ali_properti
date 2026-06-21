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
        'uploaded_by',
        'nama_file',
        'path_file',
        'mime_type',
        'file_size',
        'keterangan',
    ];

    protected $casts = [
        'file_size' => 'integer',
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
}
