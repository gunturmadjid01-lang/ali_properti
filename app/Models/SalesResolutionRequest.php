<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesResolutionRequest extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['locked_at' => 'datetime', 'applied_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function spr()
    {
        return $this->belongsTo(Spr::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'model')->latest('id');
    }
}
