<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialPurchaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_SELESAI_SEBAGIAN = 'selesai_sebagian';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'kode_request',
        'tanggal',
        'gudang_id',
        'status',
        'keterangan',
        'requested_by',
        'processed_by',
        'processed_at',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'processed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(MaterialPurchaseRequestDetail::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
