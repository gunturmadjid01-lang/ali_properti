<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationTransactionArchive extends Model
{
    protected $guarded = [];

    protected $casts = ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'last_printed_at' => 'datetime'];
}
