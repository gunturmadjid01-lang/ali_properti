<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequirementSetItem extends Model
{
    protected $guarded = [];

    protected $casts = ['employment_categories' => 'array', 'marital_statuses' => 'array', 'is_required' => 'boolean'];

    public function document()
    {
        return $this->belongsTo(DokumenCostumer::class, 'dokumen_costumer_id');
    }

    public function requirementSet()
    {
        return $this->belongsTo(DocumentRequirementSet::class, 'document_requirement_set_id');
    }
}
