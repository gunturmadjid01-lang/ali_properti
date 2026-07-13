<?php
namespace App\Models;
use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class InventoryItem extends Model { use HasUserAudit,SoftDeletes; protected $guarded=[]; public function category():BelongsTo{return $this->belongsTo(InventoryCategory::class,'inventory_category_id');} public function units():HasMany{return $this->hasMany(OfficeAsset::class);} }
