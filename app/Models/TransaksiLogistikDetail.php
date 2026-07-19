<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiLogistikDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaksi_logistik_id',
        'barang_material_id',
        'qty',
        'input_qty',
        'input_unit_id',
        'input_satuan',
        'conversion_to_base',
        'satuan',
        'harga_satuan',
        'subtotal',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty' => 'float',
        'input_qty' => 'float',
        'conversion_to_base' => 'float',
        'harga_satuan' => 'float',
        'subtotal' => 'float',
    ];

    public function transaksiLogistik(): BelongsTo
    {
        return $this->belongsTo(TransaksiLogistik::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }

    public function inputUnit(): BelongsTo
    {
        return $this->belongsTo(MaterialUnit::class, 'input_unit_id');
    }
}
