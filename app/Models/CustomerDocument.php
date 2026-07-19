<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDocument extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['document_date' => 'date', 'expires_at' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Costumer::class, 'costumer_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DokumenCostumer::class, 'dokumen_costumer_id');
    }

    public function selections()
    {
        return $this->hasMany(SprBerkasCostumer::class);
    }
}
