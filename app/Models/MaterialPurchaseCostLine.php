<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialPurchaseCostLine extends Model
{
    protected $fillable = ['material_purchase_id', 'material_purchase_shipment_id', 'cost_type', 'payee', 'worker_count', 'rate', 'amount', 'proof_path', 'description'];
    protected $casts = ['worker_count' => 'integer', 'rate' => 'float', 'amount' => 'float'];
}
