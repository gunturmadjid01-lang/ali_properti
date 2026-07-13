<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialUsageDetail extends Model
{
    protected $fillable = ['material_usage_id', 'site_material_stock_id', 'barang_material_id', 'detail_rumah_hpp_item_id', 'qty', 'satuan'];

    protected $casts = ['qty' => 'float'];

    public function materialUsage(): BelongsTo
    {
        return $this->belongsTo(MaterialUsage::class);
    }

    public function siteMaterialStock(): BelongsTo
    {
        return $this->belongsTo(SiteMaterialStock::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function detailRumahHppItem(): BelongsTo
    {
        return $this->belongsTo(DetailRumahHppItem::class);
    }
}
