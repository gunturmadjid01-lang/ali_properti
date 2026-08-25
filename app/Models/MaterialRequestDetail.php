<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestDetail extends Model
{
    use HasFactory;

    protected $fillable = ['material_request_id', 'barang_material_id', 'qty', 'input_qty', 'input_unit_id', 'conversion_to_base', 'qty_issued', 'satuan', 'catatan', 'created_by', 'updated_by'];

    protected $casts = ['qty' => 'float', 'input_qty' => 'float', 'conversion_to_base' => 'float', 'qty_issued' => 'float'];

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }
}
