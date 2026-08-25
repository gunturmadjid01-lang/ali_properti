<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialOpeningBalance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gudang_id',
        'barang_material_id',
        'tanggal_saldo',
        'qty',
        'input_qty',
        'input_unit_id',
        'input_unit_symbol',
        'conversion_to_base',
        'harga_satuan',
        'total_nilai',
        'catatan',
        'record_status',
        'locked_at',
        'locked_by',
        'stock_posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_saldo' => 'date',
        'qty' => 'float',
        'input_qty' => 'float',
        'conversion_to_base' => 'float',
        'harga_satuan' => 'float',
        'total_nilai' => 'float',
        'locked_at' => 'datetime',
        'stock_posted_at' => 'datetime',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
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
