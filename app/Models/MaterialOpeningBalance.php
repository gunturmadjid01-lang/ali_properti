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
        'harga_satuan',
        'total_nilai',
        'catatan',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_saldo' => 'date',
        'qty' => 'float',
        'harga_satuan' => 'float',
        'total_nilai' => 'float',
        'locked_at' => 'datetime',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function barangMaterial(): BelongsTo
    {
        return $this->belongsTo(BarangMaterial::class);
    }
}
