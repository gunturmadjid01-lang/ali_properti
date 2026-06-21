<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenCostumer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_dokumen',
        'nama_dokumen',
        'kategori_pengajuan',
        'wajib',
        'keterangan',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'wajib' => 'boolean',
        'locked_at' => 'datetime',
    ];
}
