<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingLeadSource extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_sumber',
        'nama_sumber',
        'kategori',
        'keterangan',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function costumers(): HasMany
    {
        return $this->hasMany(Costumer::class);
    }
}
