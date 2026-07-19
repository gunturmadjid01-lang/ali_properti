<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes;

    public const KAS_BANK = '1-1000';
    public const KAS_KECIL = '1-1010';
    public const PIUTANG_CUSTOMER = '1-1100';
    public const PERSEDIAAN_MATERIAL = '1-1300';
    public const PERSEDIAAN_PROYEK = '1-1400';
    public const UANG_MUKA_CUSTOMER = '2-1000';
    public const HPP_KONSTRUKSI = '5-1100';
    public const HPP_MATERIAL = '5-1200';
    public const HUTANG_KONTRAKTOR = '2-2100';
    public const HUTANG_SUPPLIER = '2-2200';
    public const HUTANG_INVESTOR = '2-2500';
    public const PENDAPATAN_UNIT = '4-1000';
    public const PENDAPATAN_ADMIN = '4-2000';
    public const BEBAN_OPERASIONAL = '6-3000';

    public const BEBAN_GAJI = '6-1000';

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'kategori',
        'posisi_normal',
        'status',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class);
    }
}
