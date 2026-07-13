<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeAsset extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = ['inventory_item_id','kode_aset','nomor_seri','inventory_location_id','current_user_id','status','condition','notes','created_by','updated_by'];

    public function item(): BelongsTo { return $this->belongsTo(InventoryItem::class, 'inventory_item_id'); }
    public function location(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'inventory_location_id'); }
    public function currentUser(): BelongsTo { return $this->belongsTo(User::class, 'current_user_id'); }
    public function siteManpowerLogs(): BelongsToMany { return $this->belongsToMany(SiteManpowerLog::class)->withTimestamps(); }
}
