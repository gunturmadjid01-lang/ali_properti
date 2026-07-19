<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankKredit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_bank',
        'nama_bank',
        'nama_pic',
        'telepon_pic',
        'email_pic',
        'bunga_tahunan',
        'tenor_min_bulan',
        'tenor_max_bulan',
        'minimal_dp_persen',
        'biaya_provisi_persen',
        'biaya_admin',
        'status',
        'jenis_bank',
        'alamat_pusat',
        'website',
        'logo',
        'nomor_telepon',
        'email',
        'catatan',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'bunga_tahunan' => 'float',
        'tenor_min_bulan' => 'integer',
        'tenor_max_bulan' => 'integer',
        'minimal_dp_persen' => 'float',
        'biaya_provisi_persen' => 'float',
        'biaya_admin' => 'float',
        'locked_at' => 'datetime',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(KprSubmission::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BankBranch::class);
    }

    public function creditProducts(): HasMany
    {
        return $this->hasMany(BankCreditProduct::class);
    }

    public function partnerships(): HasMany
    {
        return $this->hasMany(BankHousingPartnership::class);
    }
}
