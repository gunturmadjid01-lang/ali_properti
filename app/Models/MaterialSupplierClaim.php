<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;

class MaterialSupplierClaim extends Model
{
    use HasUserAudit;

    protected $fillable = ['material_purchase_id', 'material_purchase_detail_id', 'claim_no', 'claim_type', 'qty', 'amount', 'resolution', 'status', 'record_status', 'locked_at', 'locked_by', 'notes', 'resolved_at', 'resolved_by', 'created_by', 'updated_by'];
    protected $casts = ['qty' => 'float', 'amount' => 'float', 'resolved_at' => 'datetime', 'locked_at' => 'datetime'];
}
