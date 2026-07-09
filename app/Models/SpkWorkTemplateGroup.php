<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpkWorkTemplateGroup extends Model
{
    protected $fillable = [
        'spk_work_template_id',
        'judul_tahapan',
        'urutan',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SpkWorkTemplate::class, 'spk_work_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SpkWorkTemplateItem::class, 'spk_work_template_group_id')->orderBy('urutan');
    }
}
