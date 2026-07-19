<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentRequirementSet extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['application_types' => 'array', 'locked_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(DocumentRequirementSetItem::class)->orderBy('sort_order');
    }

    public function banks()
    {
        return $this->belongsToMany(BankKredit::class, 'document_requirement_set_banks');
    }

    public function products()
    {
        return $this->belongsToMany(BankCreditProduct::class, 'document_requirement_set_products');
    }

    public function housings()
    {
        return $this->belongsToMany(Perumahan::class, 'document_requirement_set_housings');
    }

    public function companies()
    {
        return $this->belongsToMany(CabangPerusahaan::class, 'document_requirement_set_companies');
    }

    public function partnerships()
    {
        return $this->belongsToMany(BankHousingPartnership::class, 'document_requirement_set_partnerships');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'model')->latest('id');
    }
}
