<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kontraktor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_kontraktor',
        'nama_kontraktor',
        'jenis_badan',
        'bidang_pekerjaan',
        'penanggung_jawab',
        'phone',
        'email',
        'alamat',
        'catatan',
        'status',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Kontraktor $kontraktor) {
            $kontraktor->spkKontraktors()->get()->each->delete();
        });
    }

    public function spkKontraktors(): HasMany
    {
        return $this->hasMany(SpkKontraktor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
