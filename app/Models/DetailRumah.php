<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailRumah extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'perumahan_id',
        'kode_nlok',
        'nomor_rumah',
        'tipe_rumah',
        'model_unit',
        'luas_bangunan',
        'luas_tanah',
        'jumlah_lantai',
        'kamar_tidur',
        'kamar_mandi',
        'daya_listrik',
        'sumber_air',
        'carport',
        'arah_hadap',
        'posisi_unit',
        'harga_jual',
        'status_penjualan',
        'booking_spr_id',
        'booking_at',
        'status_pembangunan',
        'progress_terakhir',
        'tanggal_mulai_bangun',
        'tanggal_selesai_bangun',
        'spesifikasi',
        'catatan',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'progress_terakhir' => 'float',
        'harga_jual' => 'float',
        'jumlah_lantai' => 'integer',
        'kamar_tidur' => 'integer',
        'kamar_mandi' => 'integer',
        'tanggal_mulai_bangun' => 'date',
        'tanggal_selesai_bangun' => 'date',
        'booking_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (DetailRumah $detailRumah) {
            if ($detailRumah->wasChanged(['status_pembangunan', 'progress_terakhir', 'tanggal_mulai_bangun', 'tanggal_selesai_bangun'])) {
                app(\App\Services\SalesProcessService::class)->syncLinkedUnitData($detailRumah->id);
            }
        });
        static::deleting(function (DetailRumah $detailRumah) {
            $detailRumah->progressPembangunans()->get()->each->delete();
            $detailRumah->detailRumahHpps()->get()->each->delete();
            $detailRumah->hppRealisasis()->get()->each->delete();
        });
    }

    public function getDisplayLabelAttribute(): string
    {
        $block = trim((string) $this->kode_nlok);
        $number = trim((string) $this->nomor_rumah);

        return collect([
            $block !== '' ? 'Blok '.$block : null,
            $number !== '' ? 'No. '.$number : null,
        ])->filter()->join(' ') ?: 'Unit belum ditentukan';
    }

    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class);
    }

    public function progressPembangunans(): HasMany
    {
        return $this->hasMany(ProgressPembangunan::class);
    }

    public function detailRumahHpps(): HasMany
    {
        return $this->hasMany(DetailRumahHpp::class);
    }

    public function hppRealisasis(): HasMany
    {
        return $this->hasMany(HppRealisasi::class);
    }

    public function materialRequests(): HasMany
    {
        return $this->hasMany(MaterialRequest::class);
    }

    public function qualityUpgradeContracts(): HasMany
    {
        return $this->hasMany(QualityUpgradeContract::class);
    }

    public function sprs(): HasMany
    {
        return $this->hasMany(Spr::class);
    }

    public function housingReservations(): HasMany
    {
        return $this->hasMany(HousingReservation::class, 'detail_rumah_id');
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(UnitOwnership::class);
    }

    public function currentOwnership(): HasOne
    {
        return $this->hasOne(UnitOwnership::class)->ofMany(
            ['acquired_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('is_active', true),
        );
    }

    public function bookingSpr(): BelongsTo
    {
        return $this->belongsTo(Spr::class, 'booking_spr_id');
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
