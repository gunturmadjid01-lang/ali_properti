<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpkWorkTemplate extends Model
{
    protected $fillable = [
        'perumahan_id',
        'konteks',
        'nama_template',
        'catatan',
        'created_by',
        'updated_by',
    ];

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(SpkWorkTemplateGroup::class)->orderBy('urutan');
    }
}
