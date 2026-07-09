<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkWorkTemplateItem extends Model
{
    protected $fillable = [
        'spk_work_template_group_id',
        'nama_pekerjaan',
        'volume',
        'satuan',
        'harga_satuan',
        'urutan',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SpkWorkTemplateGroup::class, 'spk_work_template_group_id');
    }
}
