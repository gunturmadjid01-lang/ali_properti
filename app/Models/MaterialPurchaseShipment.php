<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialPurchaseShipment extends Model
{
    protected $fillable = ['material_purchase_id', 'shipment_no', 'delivery_note_no', 'expedition_provider', 'vehicle_no', 'driver_name', 'shipped_at', 'arrived_at', 'freight_cost', 'logistics_labor_cost', 'other_cost', 'status', 'notes'];
    protected $casts = ['shipped_at' => 'datetime', 'arrived_at' => 'datetime', 'freight_cost' => 'float', 'logistics_labor_cost' => 'float', 'other_cost' => 'float'];
}
