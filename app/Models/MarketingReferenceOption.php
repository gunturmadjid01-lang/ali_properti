<?php

namespace App\Models;

use App\Models\Concerns\HasUserAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class MarketingReferenceOption extends Model
{
    use HasUserAudit, SoftDeletes;

    protected $fillable = ['category', 'code', 'label', 'description', 'sort_order', 'is_active', 'record_status', 'locked_at', 'locked_by', 'created_by', 'updated_by'];

    protected $casts = ['is_active' => 'boolean', 'locked_at' => 'datetime'];

    public function latestApproval()
    {
        return $this->morphOne(ApprovalRequest::class, 'model')->ofMany('id', 'max')->where('module_key', 'marketing-reference-option');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('record_status', 'locked');
    }

    public static function options(string $category, array $fallback = []): array
    {
        if (! Schema::hasTable('marketing_reference_options')) {
            return $fallback;
        }
        $rows = static::query()->active()->where('category', $category)->orderBy('sort_order')->get(['code', 'label']);

        return $rows->isEmpty() ? $fallback : $rows->map(fn ($row) => ['value' => $row->code, 'label' => $row->label])->all();
    }
}
