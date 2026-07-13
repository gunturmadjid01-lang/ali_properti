<?php
namespace App\Models;
use App\Models\Concerns\HasUserAudit;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\SoftDeletes;
class HeavyEquipmentComponent extends Model { use HasUserAudit,SoftDeletes; protected $guarded=[]; }
