<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingTemplate extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'kode_template', 'nama_template', 'kanal', 'tahapan', 'isi_template',
        'is_system', 'status', 'record_status', 'locked_at', 'locked_by',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'locked_at' => 'datetime',
    ];
}
