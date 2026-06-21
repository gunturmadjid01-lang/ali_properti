<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpkKontraktor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kontraktor_id',
        'perumahan_id',
        'detail_rumah_id',
        'nomor_spk',
        'judul_pekerjaan',
        'jenis_pekerjaan',
        'tanggal_spk',
        'tanggal_mulai',
        'tanggal_selesai',
        'nilai_kontrak_dasar',
        'nilai_kontrak',
        'total_penambahan',
        'metode_pembayaran',
        'approval_role',
        'lingkup_pekerjaan',
        'catatan',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_spk' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'nilai_kontrak_dasar' => 'float',
        'nilai_kontrak' => 'float',
        'total_penambahan' => 'float',
        'locked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (SpkKontraktor $spkKontraktor) {
            $spkKontraktor->payments()->get()->each->delete();
            $spkKontraktor->additions()->get()->each->delete();
        });
    }

    public function kontraktor(): BelongsTo
    {
        return $this->belongsTo(Kontraktor::class);
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function detailRumah(): BelongsTo
    {
        return $this->belongsTo(DetailRumah::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SpkKontraktorPayment::class);
    }

    public function additions(): HasMany
    {
        return $this->hasMany(SpkKontraktorAddition::class);
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
