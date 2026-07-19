<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesProcessStep extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['planned_date' => 'date', 'actual_date' => 'date', 'metadata' => 'array', 'locked_at' => 'datetime', 'started_at' => 'datetime'];

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function checklistItems()
    {
        return $this->hasMany(SalesProcessChecklistItem::class);
    }

    public function documents()
    {
        return $this->hasMany(SalesProcessDocument::class);
    }

    public function customerDocuments()
    {
        return $this->belongsToMany(CustomerDocument::class, 'sales_process_customer_documents')->withPivot(['document_requirement_set_item_id', 'validation_status', 'validation_notes', 'selected_by'])->withTimestamps();
    }
}
