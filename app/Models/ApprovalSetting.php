<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_key',
        'module_label',
        'action',
        'requires_approval',
        'approver_role_ids',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'approver_role_ids' => 'array',
        'is_active' => 'boolean',
    ];
}
