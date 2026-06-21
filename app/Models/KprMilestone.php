<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KprMilestone extends Model
{
    use HasUserAudit, SoftDeletes;

    public const AKAD = 'akad';
    public const SERAH_TERIMA = 'serah_terima';

    protected $fillable = [
        'kpr_submission_id',
        'jenis',
        'tanggal_proses',
        'lokasi',
        'nomor_dokumen',
        'pihak_terkait',
        'catatan',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_proses' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KprSubmission::class, 'kpr_submission_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KprMilestoneDocument::class);
    }
}
