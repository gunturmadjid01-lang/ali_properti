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
        'status',
        'record_status',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(KprSubmission::class);
    }
}
