<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialSupplierInvoice extends Model
{
    protected $fillable = ['material_purchase_id', 'supplier_id', 'invoice_no', 'invoice_date', 'gross_amount', 'accepted_amount', 'claim_amount', 'payable_amount', 'paid_amount', 'outstanding_amount', 'status'];
    protected $casts = ['invoice_date' => 'date', 'gross_amount' => 'float', 'accepted_amount' => 'float', 'claim_amount' => 'float', 'payable_amount' => 'float', 'paid_amount' => 'float', 'outstanding_amount' => 'float'];
}
