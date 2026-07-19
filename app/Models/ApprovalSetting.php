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
        'approval_stages',
        'approver_role_ids',
        'approval_steps',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'approval_stages' => 'integer',
        'approver_role_ids' => 'array',
        'approval_steps' => 'array',
        'is_active' => 'boolean',
    ];
}
