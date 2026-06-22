<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetUsageRequest extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_pengajuan', 'office_asset_id', 'perumahan_id', 'detail_rumah_id',
        'nama_peminjam', 'tanggal_mulai', 'tanggal_selesai_estimasi', 'tujuan_pemakaian',
        'lokasi_pemakaian', 'status', 'requested_by', 'approved_by',
        'approved_at', 'approval_note', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai_estimasi' => 'date',
        'approved_at' => 'datetime',
    ];

    public function asset(): BelongsTo { return $this->belongsTo(OfficeAsset::class, 'office_asset_id'); }
    public function perumahan(): BelongsTo { return $this->belongsTo(Perumahan::class); }
    public function detailRumah(): BelongsTo { return $this->belongsTo(DetailRumah::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
