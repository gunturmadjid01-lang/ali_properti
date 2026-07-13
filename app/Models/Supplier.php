<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_supplier',
        'nama_supplier',
        'pic',
        'phone',
        'email',
        'alamat',
        'nama_bank',
        'nomor_rekening',
        'nama_rekening',
        'npwp',
        'catatan',
        'status',
        'record_status',
        'locked_by',
        'locked_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class);
    }
}
