<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankKprFinancing extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['sale_price' => 'decimal:2', 'approved_limit' => 'decimal:2', 'booking_fee' => 'decimal:2', 'down_payment' => 'decimal:2', 'shortfall' => 'decimal:2', 'developer_fee' => 'decimal:2', 'notary_fee' => 'decimal:2', 'expected_disbursement_date' => 'date', 'sp3k_date' => 'date', 'sp3k_expired_at' => 'date', 'locked_at' => 'datetime'];

    public function submission()
    {
        return $this->belongsTo(KprSubmission::class, 'kpr_submission_id');
    }
}
