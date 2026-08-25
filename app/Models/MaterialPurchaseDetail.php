<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_purchase_id',
        'barang_material_id',
        'material_unit_id',
        'qty',
        'qty_base',
        'qty_faktur',
        'qty_fisik_tiba',
        'qty_diterima',
        'qty_diterima_base',
        'qty_diterima_baik',
        'qty_cacat',
        'qty_ditolak',
        'qty_kurang',
        'qty_lebih',
        'inspection_status',
        'kondisi_fisik',
        'status_selisih',
        'inspection_note',
        'alasan_selisih',
        'checked_by',
        'checked_at',
        'satuan',
        'conversion_to_base',
        'harga_satuan',
        'invoice_unit_price',
        'price_variance',
        'price_variance_percent',
        'price_variance_requires_approval',
        'harga_satuan_base',
        'diskon',
        'subtotal',
        'biaya_ekspedisi_alokasi',
        'upah_buruh_alokasi',
        'biaya_lain_alokasi',
        'landed_cost_total',
        'landed_unit_cost',
    ];

    protected $casts = [
        'qty' => 'float',
        'qty_base' => 'float',
        'qty_faktur' => 'float',
        'qty_fisik_tiba' => 'float',
        'qty_diterima' => 'float',
        'qty_diterima_base' => 'float',
        'qty_diterima_baik' => 'float',
        'qty_cacat' => 'float',
        'qty_ditolak' => 'float',
        'qty_kurang' => 'float',
        'qty_lebih' => 'float',
        'conversion_to_base' => 'float',
        'harga_satuan' => 'float',
        'invoice_unit_price' => 'float',
        'price_variance' => 'float',
        'price_variance_percent' => 'float',
        'price_variance_requires_approval' => 'boolean',
        'harga_satuan_base' => 'float',
        'diskon' => 'float',
        'subtotal' => 'float',
        'biaya_ekspedisi_alokasi' => 'float',
        'upah_buruh_alokasi' => 'float',
        'biaya_lain_alokasi' => 'float',
        'landed_cost_total' => 'float',
        'landed_unit_cost' => 'float',
        'checked_at' => 'datetime',
    ];

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function materialPurchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class);
    }

    public function materialUnit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
