<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Services\MaterialUsageReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaterialUsageReportController extends Controller
{
    public function index(Request $request, MaterialUsageReportService $service): Response
    {
        $this->authorizeReport('view');
        [$filters, $perumahan, $unit, $start, $end] = $this->reportInput($request);

        return Inertia::render('Admin/Reports/MaterialUsage', [
            'title' => 'Laporan Pemakaian Barang',
            'description' => 'Pemakaian material yang terhubung dengan progress pembangunan, berdasarkan perumahan, unit, dan periode.',
            'baseUrl' => route('admin.material-usage-report.index', absolute: false),
            'printUrl' => route('admin.material-usage-report.print', absolute: false),
            'filters' => $filters,
            'options' => $this->options(),
            'report' => $service->build($perumahan, $unit, $start, $end),
            'permissions' => ['canExport' => $this->canReport('export')],
        ]);
    }

    public function print(Request $request, MaterialUsageReportService $service)
    {
        $this->authorizeReport('export');
        [$filters, $perumahan, $unit, $start, $end] = $this->reportInput($request);

        return response()->view('reports.material-usage', [
            'filters' => $filters,
            'report' => $service->build($perumahan, $unit, $start, $end),
            'printedAt' => now()->format('d/m/Y H:i'),
        ]);
    }

    private function reportInput(Request $request): array
    {
        $validated = $request->validate([
            'period_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'reference_date' => ['nullable', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'perumahan_id' => ['nullable', 'integer', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'integer', 'exists:detail_rumahs,id'],
        ]);

        $type = $validated['period_type'] ?? 'monthly';
        if ($type === 'monthly') {
            $start = Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } else {
            $reference = Carbon::parse($validated['reference_date'] ?? now()->toDateString());
            $start = $type === 'weekly' ? $reference->copy()->startOfWeek() : $reference->copy()->startOfDay();
            $end = $type === 'weekly' ? $reference->copy()->endOfWeek() : $reference->copy()->endOfDay();
        }

        $perumahan = isset($validated['perumahan_id'])
            ? Perumahan::query()->finalized()->findOrFail($validated['perumahan_id'])
            : null;
        $unit = isset($validated['detail_rumah_id'])
            ? DetailRumah::query()->finalized()->findOrFail($validated['detail_rumah_id'])
            : null;

        if ($unit && $perumahan && (int) $unit->perumahan_id !== (int) $perumahan->id) {
            throw ValidationException::withMessages([
                'detail_rumah_id' => 'Unit yang dipilih tidak berada pada perumahan tersebut.',
            ]);
        }
        if ($unit && ! $perumahan) {
            $perumahan = Perumahan::query()->finalized()->find($unit->perumahan_id);
        }

        return [[
            'period_type' => $type,
            'reference_date' => ($type === 'monthly' ? now() : $start)->toDateString(),
            'month' => $start->format('Y-m'),
            'perumahan_id' => $perumahan ? (string) $perumahan->id : '',
            'detail_rumah_id' => $unit ? (string) $unit->id : '',
        ], $perumahan, $unit, $start, $end];
    }

    private function options(): array
    {
        return [
            'periodTypes' => [
                ['value' => 'daily', 'label' => 'Harian'],
                ['value' => 'weekly', 'label' => 'Mingguan (Senin - Minggu)'],
                ['value' => 'monthly', 'label' => 'Bulanan'],
            ],
            'perumahans' => Perumahan::query()->finalized()->orderBy('nama_perusahaan')->get(['id', 'nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])->values(),
            'units' => DetailRumah::query()->finalized()->with('perumahan:id,nama_perusahaan')
                ->orderBy('perumahan_id')->orderBy('kode_nlok')->orderBy('nomor_rumah')
                ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah', 'tipe_rumah'])
                ->map(fn ($row) => [
                    'value' => (string) $row->id,
                    'label' => 'Blok '.$row->kode_nlok.' No. '.$row->nomor_rumah.' (Tipe '.($row->tipe_rumah ?: '-').')',
                    'perumahan_id' => (string) $row->perumahan_id,
                ])->values(),
        ];
    }

    private function canReport(string $action): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('super_admin')
            || $user?->hasAnyRole(['owner', 'petugas'])
            || $user?->can("laporan.{$action}")
            || $user?->can('laporan-persediaan-material.view')
            || $user?->can('material-usage.view'));
    }

    private function authorizeReport(string $action): void
    {
        abort_unless($this->canReport($action), 403, 'Anda tidak memiliki permission laporan pemakaian barang.');
    }
}
