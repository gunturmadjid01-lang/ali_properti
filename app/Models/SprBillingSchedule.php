<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SprBillingSchedule extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = [
        'spr_id', 'jenis_tagihan', 'termin_ke', 'tanggal_jatuh_tempo',
        'nominal_tagihan', 'nominal_dibayar', 'status', 'keterangan',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_jatuh_tempo' => 'date',
        'nominal_tagihan' => 'float',
        'nominal_dibayar' => 'float',
    ];

    public function spr(): BelongsTo
    {
        return $this->belongsTo(Spr::class);
    }
}
