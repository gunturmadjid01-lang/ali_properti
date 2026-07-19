<?php

namespace App\Services;

use App\Models\PrintTemplateAssignment;

class PrintTemplateService
{
    public function resolve(string $printKey): array
    {
        $template = PrintTemplateAssignment::query()->with('template')->where('print_key', $printKey)->first()?->template;
        if (! $template?->is_active) {
            return ['paper_size' => 'a4', 'orientation' => 'portrait', 'width_mm' => 210, 'height_mm' => 297, 'margin_top_mm' => 15, 'margin_right_mm' => 15, 'margin_bottom_mm' => 15, 'margin_left_mm' => 15];
        }
        [$width, $height] = match ($template->paper_size) {
            'legal' => [216, 356],
            'custom' => [$template->custom_width_mm, $template->custom_height_mm],
            default => [210, 297],
        };

        return [...$template->only(['paper_size', 'orientation', 'margin_top_mm', 'margin_right_mm', 'margin_bottom_mm', 'margin_left_mm']), 'width_mm' => $width, 'height_mm' => $height];
    }

    public function domPdfPaper(array $config): array|string
    {
        if ($config['paper_size'] !== 'custom') {
            return $config['paper_size'];
        }
        $factor = 72 / 25.4;

        return [0, 0, $config['width_mm'] * $factor, $config['height_mm'] * $factor];
    }
}
