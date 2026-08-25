<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesLeadImportBatch extends Model
{
    protected $guarded = [];

    public function rows(): HasMany
    {
        return $this->hasMany(SalesLeadIntakeRow::class, 'batch_id');
    }
}
