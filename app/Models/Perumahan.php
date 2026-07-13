<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Perumahan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cabang_id',
        'kode_proyek',
        'nama_perusahaan',
        'developer_name',
        'alamat',
        'latitude',
        'longtitude',
        'logo',
        'luas_lahan',
        'luas_komersial',
        'luas_fasos_fasum',
        'jumlah_unit',
        'total_blok',
        'harga_mulai',
        'tanggal_mulai',
        'tanggal_target_selesai',
        'jenis_sertifikat',
        'nomor_sertifikat_induk',
        'nama_marketing',
        'phone_marketing',
        'email_marketing',
        'deskripsi',
        'status',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_target_selesai' => 'date',
        'jumlah_unit' => 'integer',
        'total_blok' => 'integer',
        'harga_mulai' => 'float',
        'locked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Perumahan $perumahan): void {
            if (blank($perumahan->kode_proyek)) {
                $perumahan->kode_proyek = static::nextKodeProyek();
            }
        });

        static::deleting(function (Perumahan $perumahan) {
            $perumahan->detailRumahs()->get()->each->delete();
            $perumahan->perumahanHpps()->get()->each->delete();
            $perumahan->hppRealisasis()->get()->each->delete();
            $perumahan->dokumenLegalitas()->get()->each->delete();
            $perumahan->dokumenLegalitasRumahs()->get()->each->delete();
        });
    }

    public static function nextKodeProyek(): string
    {
        $number = (int) (static::withTrashed()->max('id') ?? 0) + 1;

        do {
            $code = 'PRJ-'.Str::padLeft((string) $number, 5, '0');
            $number++;
        } while (static::withTrashed()->where('kode_proyek', $code)->exists());

        return $code;
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(CabangPerusahaan::class, 'cabang_id');
    }

    public function dokumenLegalitas(): HasMany
    {
        return $this->hasMany(DokumenLegalitas::class);
    }

    public function dokumenLegalitasRumahs(): HasMany
    {
        return $this->hasMany(DokumenLegalitasRumah::class);
    }

    public function detailRumahs(): HasMany
    {
        return $this->hasMany(DetailRumah::class);
    }

    public function perumahanHpps(): HasMany
    {
        return $this->hasMany(PerumahanHpp::class);
    }

    public function hppRealisasis(): HasMany
    {
        return $this->hasMany(HppRealisasi::class);
    }
}
