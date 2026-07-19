<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintTemplate;
use App\Models\PrintTemplateAssignment;
use App\Support\PrintTargets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PrintTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSettings($request);

        return Inertia::render('Admin/PrintSettings/Index', [
            'title' => 'Pengaturan Cetak',
            'baseUrl' => route('admin.print-settings.update', absolute: false),
            'templates' => PrintTemplate::query()->orderBy('name')->get(),
            'targets' => collect(PrintTargets::all())->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'template_id' => (string) (PrintTemplateAssignment::where('print_key', $key)->value('print_template_id') ?? ''),
            ])->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);
        $data = $request->validate([
            'templates' => ['required', 'array', 'min:1'], 'templates.*.id' => ['nullable', 'integer'],
            'templates.*.name' => ['required', 'string', 'max:100'], 'templates.*.paper_size' => ['required', Rule::in(['a4', 'legal', 'custom'])],
            'templates.*.orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'templates.*.custom_width_mm' => ['nullable', 'numeric', 'between:50,1000'], 'templates.*.custom_height_mm' => ['nullable', 'numeric', 'between:50,1000'],
            'templates.*.margin_top_mm' => ['required', 'numeric', 'between:0,100'], 'templates.*.margin_right_mm' => ['required', 'numeric', 'between:0,100'],
            'templates.*.margin_bottom_mm' => ['required', 'numeric', 'between:0,100'], 'templates.*.margin_left_mm' => ['required', 'numeric', 'between:0,100'],
            'targets' => ['required', 'array'], 'targets.*.key' => ['required', Rule::in(array_keys(PrintTargets::all()))], 'targets.*.template_id' => ['nullable'],
        ]);
        $idMap = [];
        foreach ($data['templates'] as $index => $row) {
            abort_if($row['paper_size'] === 'custom' && (! filled($row['custom_width_mm'] ?? null) || ! filled($row['custom_height_mm'] ?? null)), 422, 'Ukuran kertas custom wajib diisi.');
            $template = PrintTemplate::updateOrCreate(['id' => $row['id'] ?? null], [...$row, 'is_active' => true]);
            $idMap[$index] = (string) $template->id;
        }
        foreach ($data['targets'] as $target) {
            $templateId = $target['template_id'] ?? null;
            if (str_starts_with((string) $templateId, 'new:')) {
                $templateId = $idMap[(int) substr($templateId, 4)] ?? null;
            }
            if ($templateId) {
                PrintTemplateAssignment::updateOrCreate(['print_key' => $target['key']], ['print_template_id' => $templateId]);
            } else {
                PrintTemplateAssignment::where('print_key', $target['key'])->delete();
            }
        }

        return back()->with('success', 'Template dan penugasan cetak berhasil disimpan.');
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()?->can('approval.settings'), 403);
    }
}
