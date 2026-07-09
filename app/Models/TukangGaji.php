<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TukangGaji extends Model
{
    use HasUserAudit;

    protected $fillable = [
        'tukang_id',
        'nominal',
        'tanggal_berlaku',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'tanggal_berlaku' => 'date',
        ];
    }

    public function tukang(): BelongsTo
    {
        return $this->belongsTo(Tukang::class);
    }
}
