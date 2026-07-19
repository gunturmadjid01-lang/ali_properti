<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintTemplate extends Model
{
    protected $fillable = ['name', 'paper_size', 'orientation', 'custom_width_mm', 'custom_height_mm', 'margin_top_mm', 'margin_right_mm', 'margin_bottom_mm', 'margin_left_mm', 'is_active'];

    protected $casts = ['custom_width_mm' => 'float', 'custom_height_mm' => 'float', 'margin_top_mm' => 'float', 'margin_right_mm' => 'float', 'margin_bottom_mm' => 'float', 'margin_left_mm' => 'float', 'is_active' => 'boolean'];

    public function assignments(): HasMany
    {
        return $this->hasMany(PrintTemplateAssignment::class);
    }
}
