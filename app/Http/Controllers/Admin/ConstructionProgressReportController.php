<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Services\ConstructionProgressReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConstructionProgressReportController extends Controller
{
    public function index(Request $request, ConstructionProgressReportService $service): Response
    {
        $this->authorizeReport('view');
        [$filters, $start, $end, $units] = $this->reportInput($request);

        return Inertia::render('Admin/Reports/ConstructionProgress', [
            'title' => 'Laporan Progress Pembangunan',
            'description' => 'Laporan harian, rentang tanggal/mingguan, atau bulanan dengan perbandingan maksimal dua blok.',
            'baseUrl' => route('admin.construction-progress-report.index', absolute: false),
            'printUrl' => route('admin.construction-progress-report.print', absolute: false),
            'filters' => $filters,
            'options' => $this->options(),
            'report' => $service->build($units, $start, $end),
            'permissions' => ['canExport' => $this->canReport('export')],
        ]);
    }

    public function print(Request $request, ConstructionProgressReportService $service)
    {
        $this->authorizeReport('export');
        [$filters, $start, $end, $units] = $this->reportInput($request);

        return response()->view('reports.construction-progress', [
            'report' => $service->build($units, $start, $end),
            'filters' => $filters,
            'printedAt' => now()->format('d/m/Y H:i'),
        ]);
    }

    private function reportInput(Request $request): array
    {
        $validated = $request->validate([
            'period_type' => ['nullable', Rule::in(['daily', 'range', 'monthly'])],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'month' => ['nullable', 'date_format:Y-m'],
            'unit_ids' => ['nullable', 'array', 'max:2'],
            'unit_ids.*' => ['integer', 'distinct', 'exists:detail_rumahs,id'],
        ]);
        $type = $validated['period_type'] ?? 'range';

        if ($type === 'daily') {
            $start = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();
            $end = $start->copy()->endOfDay();
        } elseif ($type === 'monthly') {
            $start = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } else {
            $start = Carbon::parse($validated['date_from'] ?? now()->startOfWeek()->toDateString())->startOfDay();
            $end = Carbon::parse($validated['date_to'] ?? now()->endOfWeek()->toDateString())->endOfDay();
        }

        $unitIds = collect($validated['unit_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
        $units = DetailRumah::query()->finalized()
            ->with('perumahan:id,nama_perusahaan')
            ->whereIn('id', $unitIds)
            ->get()
            ->sortBy(fn (DetailRumah $unit) => $unitIds->search($unit->id))
            ->values();

        if ($units->count() > 1 && ($units->pluck('perumahan_id')->unique()->count() > 1 || $units->pluck('tipe_rumah')->unique()->count() > 1)) {
            throw ValidationException::withMessages([
                'unit_ids' => 'Dua blok harus berasal dari proyek dan tipe rumah yang sama agar harga serta bobot pekerjaan sebanding.',
            ]);
        }

        return [[
            'period_type' => $type,
            'date' => $start->toDateString(),
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'month' => $start->format('Y-m'),
            'unit_ids' => $unitIds->map(fn ($id) => (string) $id)->all(),
        ], $start, $end, $units];
    }

    private function options(): array
    {
        return [
            'periodTypes' => [
                ['value' => 'daily', 'label' => 'Harian (1 Hari)'],
                ['value' => 'range', 'label' => 'Mingguan / Rentang Tanggal'],
                ['value' => 'monthly', 'label' => 'Bulanan'],
            ],
            'units' => DetailRumah::query()->finalized()
                ->with('perumahan:id,nama_perusahaan')
                ->orderBy('perumahan_id')
                ->orderBy('kode_nlok')
                ->orderBy('nomor_rumah')
                ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah'])
                ->map(fn (DetailRumah $unit) => [
                    'value' => (string) $unit->id,
                    'label' => ($unit->perumahan?->nama_perusahaan ?? '-').' — Blok '.$unit->kode_nlok.' No. '.$unit->nomor_rumah.' (Tipe '.($unit->tipe_rumah ?: '-').')',
                    'project_id' => (string) $unit->perumahan_id,
                    'building_type' => (string) ($unit->tipe_rumah ?? ''),
                ])->values(),
        ];
    }

    private function canReport(string $action): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('super_admin')
            || $user?->hasAnyRole(['owner', 'petugas'])
            || $user?->can("laporan.{$action}")
            || $user?->can('progress.view'));
    }

    private function authorizeReport(string $action): void
    {
        abort_unless($this->canReport($action), 403, 'Anda tidak memiliki permission laporan progress pembangunan.');
    }
}
