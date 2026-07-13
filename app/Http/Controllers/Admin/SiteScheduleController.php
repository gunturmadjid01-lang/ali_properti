<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\ScopesActivePerumahan;
use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\ProgressPembangunan;
use App\Models\SiteSchedule;
use App\Models\SpkKontraktor;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SiteScheduleController extends Controller
{
    use BuildsFieldOptions, HandlesCrudLock, ScopesActivePerumahan {
        HandlesCrudLock::lock as protected traitLock;
        HandlesCrudLock::unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        $this->authorizeSiteSchedule('view');
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');

        $query = SiteSchedule::query()
            ->with([
                'allocations',
                'perumahan:id,nama_perusahaan',
                'detailRumah:id,kode_nlok,nomor_rumah',
                'spkKontraktor:id,nomor_spk,judul_pekerjaan',
                'tahapanPembangunan:id,nama_tahapan',
                'creator:id,name',
                'updater:id,name',
            ])
            ->when($search !== '', fn (Builder $query) => $query
                ->where('kode_jadwal', 'like', "%{$search}%")
                ->orWhere('nama_pekerjaan', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%"))
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
            ->orderByDesc('tanggal_target')
            ->orderByDesc('id');

        $groupedRows = $query->get()
            ->groupBy(function (SiteSchedule $row): string {
                if ($row->batch_code) {
                    return 'batch-'.$row->batch_code;
                }

                return 'schedule-'.$row->id;
            })
            ->map(fn (Collection $group) => $this->mapScheduleGroup($group))
            ->values();

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pagedRows = $groupedRows->slice(($page - 1) * $perPage, $perPage)->values();
        $rows = new LengthAwarePaginator(
            $pagedRows,
            $groupedRows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'page']
        );

        return Inertia::render('Admin/SiteSchedule/Index', [
            'title' => 'Jadwal Lapangan',
            'baseUrl' => route('admin.site-schedule.index', absolute: false),
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'options' => $this->formOptions($request),
            'rows' => $rows,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeSiteSchedule('create');

        return Inertia::render('Admin/SiteSchedule/Form', [
            'title' => 'Buat Jadwal Lapangan',
            'mode' => 'create',
            'baseUrl' => route('admin.site-schedule.store', absolute: false),
            'indexUrl' => route('admin.site-schedule.index', absolute: false),
            'options' => $this->formOptions($request),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->authorizeSiteSchedule('update');
        $row = SiteSchedule::query()->findOrFail($id);
        $this->abortIfLocked($row);

        return Inertia::render('Admin/SiteSchedule/Form', [
            'title' => 'Edit Jadwal Lapangan',
            'mode' => 'edit',
            'baseUrl' => route('admin.site-schedule.update', $row->id, false),
            'indexUrl' => route('admin.site-schedule.index', absolute: false),
            'options' => $this->formOptions($request),
            'initialData' => [
                'id' => $row->id,
                'kode_jadwal' => $row->kode_jadwal,
                'perumahan_id' => (string) $row->perumahan_id,
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                'nama_pekerjaan' => $row->nama_pekerjaan,
                'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                'tanggal_target' => optional($row->tanggal_target)->format('Y-m-d'),
                'target_progress' => $row->target_progress,
                'realisasi_progress' => $row->realisasi_progress,
                'status' => $row->status,
                'kendala' => $row->kendala ?? '',
                'catatan' => $row->catatan ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSiteSchedule('create');
        if ($request->filled('spk_kontraktor_id')) {
            return $this->storeFromSpk($request);
        }

        if ($request->has('items')) {
            return $this->storeBatch($request);
        }

        $validated = $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_ids' => ['nullable', 'array'],
            'detail_rumah_ids.*' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'nama_pekerjaan' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_target' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'target_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'realisasi_progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:direncanakan,berjalan,terlambat,selesai,tertahan'],
            'kendala' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);
        $this->ensureTahapanContext($validated);

        SiteSchedule::query()->create([
            ...$this->schedulePayload($validated),
            'kode_jadwal' => 'JDL-'.now()->format('Ymd-His').'-'.random_int(10, 99),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.site-schedule.index')
            ->with('success', 'Jadwal lapangan berhasil dibuat.');
    }

    private function storeFromSpk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'spk_kontraktor_id' => ['required', 'exists:spk_kontraktors,id'],
            'tanggal_mulai' => ['required', 'date'],
            'jumlah_periode' => ['required', 'integer', 'min:1', 'max:52'],
            'status' => ['required', 'in:direncanakan,berjalan,terlambat,selesai,tertahan'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_pekerjaan' => ['required', 'string', 'max:255'],
            'items.*.tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'items.*.target_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.allocations' => ['nullable', 'array'],
            'items.*.allocations.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $spk = SpkKontraktor::query()
            ->with(['items', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah'])
            ->findOrFail($validated['spk_kontraktor_id']);

        abort_if($spk->status === 'batal', 422, 'SPK batal tidak dapat dipakai untuk jadwal.');

        $stageGroups = $spk->items
            ->sortBy('urutan')
            ->groupBy(fn ($item) => trim((string) $item->nama_tahap_pekerjaan) ?: 'Tahap');

        abort_unless($stageGroups->count() === count($validated['items']), 422, 'Jumlah tahapan jadwal harus sama dengan tahapan SPK yang dipilih.');

        $stageByName = $stageGroups->keys()->values()->all();
        $stageItemsByName = $stageGroups->map(function ($items) {
            return [
                'group_total' => (float) $items->sum('total'),
                'items' => $items->map(fn ($item) => [
                    'nama_pekerjaan' => $item->nama_pekerjaan,
                    'harga_satuan' => $item->harga_satuan,
                ])->values()->all(),
            ];
        })->all();

        $startDate = Carbon::parse($validated['tanggal_mulai']);
        $periodCount = (int) $validated['jumlah_periode'];
        $totalGroupValue = max(1, (float) collect($stageItemsByName)->sum('group_total'));
        $batchCode = 'JDLB-'.now()->format('Ymd-His').'-'.random_int(10, 99);
        $planRows = collect($validated['items'])
            ->values()
            ->map(function (array $item, int $index) use ($stageByName, $stageItemsByName, $totalGroupValue): array {
                $stageName = $stageByName[$index] ?? trim((string) $item['nama_pekerjaan']);
                $stageWeight = (float) ($stageItemsByName[$stageName]['group_total'] ?? 0);
                $targetProgress = $stageWeight > 0
                    ? round(($stageWeight / $totalGroupValue) * 100, 2)
                    : (float) ($item['target_progress'] ?? 0);

                return [
                    'urut' => $index + 1,
                    'nama_tahap_pekerjaan' => $stageName,
                    'target_progress' => $targetProgress,
                    'group_total' => $stageWeight,
                    'items' => $stageItemsByName[$stageName]['items'] ?? [],
                    'allocations' => collect($item['allocations'] ?? [])
                        ->map(fn ($value, $periodIndex) => [
                            'periode_ke' => ((int) $periodIndex) + 1,
                            'label_periode' => 'Minggu '.(((int) $periodIndex) + 1),
                            'bobot_persen' => (float) ($value ?? 0),
                        ])
                        ->filter(fn (array $allocation) => $allocation['bobot_persen'] > 0)
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $allocationTotals = collect($planRows)
            ->flatMap(fn (array $planRow) => $planRow['allocations'])
            ->groupBy('periode_ke')
            ->map(fn (Collection $allocations, int $periode) => [
                'periode_ke' => $periode,
                'label_periode' => 'Minggu '.$periode,
                'bobot_persen' => (float) $allocations->sum('bobot_persen'),
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($validated, $spk, $startDate, $periodCount, $batchCode, $planRows, $allocationTotals): void {
            $schedule = SiteSchedule::query()->create([
                'spk_kontraktor_id' => $spk->id,
                'spk_plan_json' => $planRows,
                'perumahan_id' => $spk->perumahan_id,
                'detail_rumah_id' => $spk->detail_rumah_id,
                'tahapan_pembangunan_id' => null,
                'nama_pekerjaan' => $spk->judul_pekerjaan,
                'tanggal_mulai' => $startDate->copy()->toDateString(),
                'tanggal_target' => $startDate->copy()->addWeeks($periodCount)->subDay()->toDateString(),
                'target_progress' => 100,
                'realisasi_progress' => 0,
                'status' => $validated['status'],
                'kendala' => null,
                'catatan' => $validated['catatan'] ?? null,
                'kode_jadwal' => $batchCode.'-001',
                'batch_code' => $batchCode,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($allocationTotals as $allocation) {
                if ((float) ($allocation['bobot_persen'] ?? 0) <= 0) {
                    continue;
                }

                $schedule->allocations()->create($allocation);
            }
        });

        return redirect()
            ->route('admin.site-schedule.index')
            ->with('success', 'Jadwal lapangan berhasil dibuat dari SPK.');
    }

    private function storeBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_ids' => ['nullable', 'array'],
            'detail_rumah_ids.*' => ['nullable', 'exists:detail_rumahs,id'],
            'tanggal_mulai' => ['required', 'date'],
            'jumlah_periode' => ['required', 'integer', 'min:1', 'max:52'],
            'status' => ['required', 'in:direncanakan,berjalan,terlambat,selesai,tertahan'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tahapan_pembangunan_id' => ['required', 'exists:tahapan_pembangunans,id'],
            'items.*.nama_pekerjaan' => ['required', 'string', 'max:255'],
            'items.*.target_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.allocations' => ['nullable', 'array'],
            'items.*.allocations.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $batchCode = 'JDLB-'.now()->format('Ymd-His').'-'.random_int(10, 99);
        $startDate = Carbon::parse($validated['tanggal_mulai']);
        $periodCount = (int) $validated['jumlah_periode'];

        DB::transaction(function () use ($validated, $batchCode, $startDate, $periodCount): void {
            $unitIds = collect($validated['detail_rumah_ids'] ?? [])
                ->filter()
                ->values();
            $targets = $unitIds->isEmpty() ? collect([null]) : $unitIds;
            $sequence = 1;

            if ($unitIds->isNotEmpty()) {
                $validUnitCount = DetailRumah::query()
                    ->where('perumahan_id', $validated['perumahan_id'])
                    ->whereIn('id', $unitIds)
                    ->count();

                abort_unless($validUnitCount === $unitIds->count(), 422, 'Unit rumah harus berasal dari perumahan yang dipilih.');
            }

            foreach ($targets as $detailRumahId) {
                foreach ($validated['items'] as $item) {
                    $stage = $this->resolveScheduleStage($item['tahapan_pembangunan_id'], $validated['perumahan_id'], $detailRumahId);
                    $payload = [
                        'perumahan_id' => $validated['perumahan_id'],
                        'detail_rumah_id' => $detailRumahId,
                        'tahapan_pembangunan_id' => $stage->id,
                        'nama_pekerjaan' => $this->stripRomanPrefix($item['nama_pekerjaan']),
                        'tanggal_mulai' => $startDate->copy()->toDateString(),
                        'tanggal_target' => $startDate->copy()->addWeeks($periodCount)->subDay()->toDateString(),
                        'target_progress' => $item['target_progress'],
                        'realisasi_progress' => 0,
                        'status' => $validated['status'],
                        'kendala' => null,
                        'catatan' => $validated['catatan'] ?? null,
                    ];

                    $this->ensureTahapanContext($payload);
                    $schedule = SiteSchedule::query()->create([
                        ...$this->schedulePayload($payload),
                        'kode_jadwal' => $batchCode.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                        'batch_code' => $batchCode,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                    $sequence++;

                    foreach (($item['allocations'] ?? []) as $periodIndex => $value) {
                        $weight = (float) ($value ?? 0);
                        if ($weight <= 0) {
                            continue;
                        }

                        $schedule->allocations()->create([
                            'periode_ke' => ((int) $periodIndex) + 1,
                            'label_periode' => 'Minggu '.(((int) $periodIndex) + 1),
                            'bobot_persen' => $weight,
                        ]);
                    }
                }
            }
        });

        return redirect()
            ->route('admin.site-schedule.index')
            ->with('success', 'Time schedule berhasil dibuat sekaligus dari tahapan RAB.');
    }

    private function mapScheduleGroup(Collection $group): array
    {
        /** @var SiteSchedule $row */
        $row = $group->sortByDesc('id')->first();
        $combinedAllocations = $group->flatMap(fn (SiteSchedule $item) => $item->allocations
            ->sortBy('periode_ke')
            ->map(fn ($allocation) => [
                'periode_ke' => $allocation->periode_ke,
                'label_periode' => $allocation->label_periode,
                'bobot_persen' => $allocation->bobot_persen,
            ])
            ->values()
            ->all())
            ->groupBy('periode_ke')
            ->map(fn (Collection $allocations, int $periode) => [
                'periode_ke' => $periode,
                'label_periode' => 'Minggu '.$periode,
                'bobot_persen' => (float) $allocations->sum('bobot_persen'),
            ])
            ->values()
            ->all();

        $spkPlan = ! empty($row->spk_plan_json)
            ? $row->spk_plan_json
            : $group->sortBy('id')->values()->map(function (SiteSchedule $item, int $index): array {
                return [
                    'urut' => $index + 1,
                    'nama_tahap_pekerjaan' => $this->stripRomanPrefix($item->nama_pekerjaan),
                    'target_progress' => $item->target_progress,
                    'group_total' => $item->target_progress,
                    'items' => [],
                    'allocations' => $item->allocations
                        ->sortBy('periode_ke')
                        ->map(fn ($allocation) => [
                            'periode_ke' => $allocation->periode_ke,
                            'label_periode' => $allocation->label_periode,
                            'bobot_persen' => $allocation->bobot_persen,
                        ])
                        ->values()
                        ->all(),
                ];
            })->all();

        return [
            'id' => $row->id,
            'kode_jadwal' => $row->kode_jadwal,
            'batch_code' => $row->batch_code,
            'perumahan_id' => (string) $row->perumahan_id,
            'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
            'spk_kontraktor_id' => (string) ($row->spk_kontraktor_id ?? ''),
            'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
            'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
            'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
            'spk' => $row->spkKontraktor ? trim($row->spkKontraktor->nomor_spk.' '.$row->spkKontraktor->judul_pekerjaan) : '-',
            'tahapan' => ! empty($spkPlan) ? count($spkPlan).' tahap' : ($this->stripRomanPrefix($row->tahapanPembangunan?->nama_tahapan) ?: '-'),
            'nama_pekerjaan' => $row->spkKontraktor ? trim($row->spkKontraktor->nomor_spk.' '.$row->spkKontraktor->judul_pekerjaan) : $this->stripRomanPrefix($group->pluck('nama_pekerjaan')->filter()->first() ?: $row->nama_pekerjaan),
            'tanggal_mulai' => optional($group->sortBy('tanggal_mulai')->first()?->tanggal_mulai ?? $row->tanggal_mulai)->format('Y-m-d'),
            'tanggal_target' => optional($group->sortByDesc('tanggal_target')->first()?->tanggal_target ?? $row->tanggal_target)->format('Y-m-d'),
            'target_progress' => $row->spk_plan_json ? $row->target_progress : (float) $group->sum('target_progress'),
            'allocations' => $combinedAllocations,
            'spk_plan' => $spkPlan,
            'realisasi_progress' => (float) $group->max('realisasi_progress'),
            'status' => $row->status,
            'kendala' => $row->kendala,
            'catatan' => $row->catatan,
            'created_by_name' => $row->creator?->name ?? '-',
            'updated_by_name' => $row->updater?->name ?? '-',
            'terlambat' => $row->status !== 'selesai' && $row->tanggal_target?->isPast(),
            'record_status' => $row->record_status ?? 'draft',
            'pdf_url' => route('admin.site-schedule.pdf', $row->id, false),
            'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && $this->canSiteSchedule('update'),
            'can_unlock' => $this->canSiteSchedule('unlock'),
            'can_edit' => ($row->record_status ?? 'draft') !== 'locked' && $this->canSiteSchedule('update'),
            'can_delete' => ($row->record_status ?? 'draft') !== 'locked' && $this->canSiteSchedule('delete'),
        ];
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeSiteSchedule('delete');
        $row = SiteSchedule::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Jadwal lapangan berhasil dihapus.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeSiteSchedule('update');

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        $this->authorizeSiteSchedule('unlock');

        return $this->traitUnlock($id);
    }

    public function exportPdf(string $id)
    {
        $this->authorizeSiteSchedule('view');
        $schedule = SiteSchedule::query()
            ->with(['allocations', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'spkKontraktor:id,nomor_spk,judul_pekerjaan'])
            ->findOrFail($id);

        $planRows = $this->schedulePdfPlanRows($schedule);
        $periodColumns = $this->schedulePdfPeriodColumns($planRows, max(1, $schedule->allocations->count()));
        $monthlyGroups = $this->schedulePdfMonthGroups($periodColumns);
        $weeklyTotals = $periodColumns->map(fn ($column) => $this->schedulePdfWeekTotal($planRows, $column['periode']));
        $cumulativeTotals = $this->schedulePdfCumulative($weeklyTotals->all());
        $pdf = $this->buildSchedulePdf($schedule, $planRows, $periodColumns, $monthlyGroups, $weeklyTotals->all(), $cumulativeTotals);
        $filename = 'TIME-SCHEDULE-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($schedule->kode_jadwal ?? 'jadwal')).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeSiteSchedule('update');
        $row = SiteSchedule::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $validated = $this->validatedPayload($request);
        $this->ensureTahapanContext($validated);
        $row->update([
            ...$this->schedulePayload($validated),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Jadwal lapangan berhasil diperbarui.');
    }

    protected function modelClass(): string
    {
        return SiteSchedule::class;
    }

    private function authorizeSiteSchedule(string $action): void
    {
        abort_unless($this->canSiteSchedule($action), 403, 'Anda tidak memiliki permission jadwal lapangan.');
    }

    private function canSiteSchedule(string $action): bool
    {
        $user = auth()->user();

        return ! $user
            || $user->hasRole('super_admin')
            || $user->can("site-schedule.{$action}")
            || $user->can('site-schedule.manage');
    }

    private function schedulePdfPlanRows(SiteSchedule $schedule): array
    {
        if (! empty($schedule->spk_plan_json)) {
            return collect($schedule->spk_plan_json)
                ->values()
                ->map(fn (array $planRow, int $index) => [
                    'urut' => (int) ($planRow['urut'] ?? ($index + 1)),
                    'nama_tahap_pekerjaan' => (string) ($planRow['nama_tahap_pekerjaan'] ?? '-'),
                    'target_progress' => (float) ($planRow['target_progress'] ?? 0),
                    'allocations' => collect($planRow['allocations'] ?? [])
                        ->map(fn (array $allocation) => [
                            'periode_ke' => (int) ($allocation['periode_ke'] ?? 0),
                            'label_periode' => (string) ($allocation['label_periode'] ?? ''),
                            'bobot_persen' => (float) ($allocation['bobot_persen'] ?? 0),
                        ])
                        ->values()
                        ->all(),
                ])
                ->all();
        }

        return $schedule->allocations
            ->sortBy('periode_ke')
            ->map(fn ($allocation, int $index) => [
                'urut' => $index + 1,
                'nama_tahap_pekerjaan' => $this->stripRomanPrefix($schedule->nama_pekerjaan ?: 'Tahap'),
                'target_progress' => (float) $schedule->target_progress,
                'allocations' => [[
                    'periode_ke' => (int) $allocation->periode_ke,
                    'label_periode' => $allocation->label_periode,
                    'bobot_persen' => (float) $allocation->bobot_persen,
                ]],
            ])
            ->values()
            ->all();
    }

    private function schedulePdfPeriodColumns(array $planRows, int $fallback): array
    {
        $maxFromPlan = max(0, ...collect($planRows)->flatMap(fn (array $row) => $row['allocations'] ?? [])->map(fn (array $allocation) => (int) ($allocation['periode_ke'] ?? 0))->all());
        $count = max(1, $fallback, $maxFromPlan);

        return collect(range(1, $count))
            ->map(fn (int $period) => [
                'periode' => $period,
                'month' => intdiv($period - 1, 4) + 1,
                'week' => (($period - 1) % 4) + 1,
            ])
            ->all();
    }

    private function schedulePdfMonthGroups(array $periodColumns): array
    {
        return collect($periodColumns)->reduce(function (array $groups, array $column): array {
            $last = $groups ? $groups[array_key_last($groups)] : null;
            if ($last && $last['month'] === $column['month']) {
                $groups[array_key_last($groups)]['count']++;
                return $groups;
            }

            $groups[] = ['month' => $column['month'], 'count' => 1];

            return $groups;
        }, []);
    }

    private function schedulePdfWeekTotal(array $planRows, int $period): float
    {
        return (float) collect($planRows)->sum(function (array $planRow) use ($period): float {
            $allocation = collect($planRow['allocations'] ?? [])->firstWhere('periode_ke', $period);

            return (float) ($allocation['bobot_persen'] ?? 0);
        });
    }

    private function schedulePdfCumulative(array $values): array
    {
        return collect($values)->reduce(function (array $carry, mixed $value): array {
            $next = (float) (count($carry) ? end($carry) : 0) + (float) $value;
            $carry[] = round($next, 2);

            return $carry;
        }, []);
    }

    private function buildSchedulePdf(SiteSchedule $schedule, array $planRows, array $periodColumns, array $monthlyGroups, array $weeklyTotals, array $cumulativeTotals): string
    {
        $width = 842;
        $height = 595;
        $margin = 26;
        $columns = [30, 180, 56, 56];
        foreach ($periodColumns as $column) {
            $columns[] = 40;
        }
        $tableWidth = array_sum($columns);
        $headerBg = '0.64 0.85 0.12 rg';
        $head2Bg = '0.85 0.97 0.61 rg';
        $totalBg = '0.99 0.89 0.28 rg';
        $summaryBg = '0.75 0.86 0.98 rg';
        $realisasiBg = '0.82 0.98 0.88 rg';
        $content = '';
        $pages = [];
        $lineHeight = 14;

        $line = fn (float $x1, float $y1, float $x2, float $y2) => sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
        $rect = fn (float $x, float $yy, float $w, float $h, bool $fill = false) => sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $yy, $w, $h, $fill ? 'f' : 'S');
        $text = fn (float $x, float $yy, string $value, int $size = 8, string $font = 'F1') => sprintf("BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $yy, $this->pdfEscape($value));
        $money = fn (float $value) => number_format($value, 2, ',', '.');

        $newPage = function () use (&$pages, &$content): void {
            if ($content !== '') {
                $pages[] = $content;
            }
            $content = '';
        };

        $drawHeader = function (float &$y) use (&$content, $margin, $tableWidth, $schedule, $text): void {
            $content .= $text($margin, $y, 'TIME SCHEDULE PEKERJAAN', 15, 'F2');
            $y -= 16;
            $content .= $text($margin, $y, ($schedule->spkKontraktor?->nomor_spk ?? '-') . ' - ' . ($schedule->spkKontraktor?->judul_pekerjaan ?? '-'), 10, 'F2');
            $y -= 12;
            $content .= $text($margin, $y, ($schedule->perumahan?->nama_perusahaan ?? '-') . ' / ' . ($schedule->detailRumah ? trim($schedule->detailRumah->kode_nlok.' '.$schedule->detailRumah->nomor_rumah) : 'Kawasan'), 9);
            $y -= 16;
            $info = [
                ['Jadwal', $schedule->kode_jadwal ?? '-'],
                ['Periode', optional($schedule->tanggal_mulai)->format('Y-m-d').' s/d '.optional($schedule->tanggal_target)->format('Y-m-d')],
                ['Status', $schedule->status ?? '-'],
                ['Realisasi', number_format((float) $schedule->realisasi_progress, 2, ',', '.').'%'],
            ];

            foreach ($info as [$label, $value]) {
                $content .= $text($margin, $y, $label.':', 8, 'F2');
                $content .= $text($margin + 55, $y, $value, 8);
                $y -= 11;
            }

            $y -= 6;
            $x = $margin;
            $content .= $headerBg = "0.64 0.85 0.12 rg\n";
            $content .= $rect($margin, $y - 16, $tableWidth, 16, true);
            $content .= "0 0 0 RG 0 0 0 rg\n";
            foreach (['NO', 'JENIS PEK', 'BOBOT (%)', 'REALISASI'] as $index => $header) {
                $w = $columns[$index];
                $content .= $rect($x, $y - 16, $w, 16);
                $content .= $text($x + 4, $y - 10, $header, 7, 'F2');
                $x += $w;
            }

            $x = $margin + array_sum(array_slice($columns, 0, 4));
            foreach ($monthlyGroups as $group) {
                $groupWidth = $group['count'] * 40;
                $content .= $rect($x, $y - 16, $groupWidth, 16);
                $content .= $text($x + 4, $y - 10, 'BULAN '.$group['month'], 7, 'F2');
                $x += $groupWidth;
            }

            $y -= 16;
            $x = $margin;
            $content .= "0.85 0.97 0.61 rg\n";
            foreach (['', '', '', ''] as $index => $_) {
                $w = $columns[$index];
                $content .= $rect($x, $y - 14, $w, 14, true);
                $content .= $rect($x, $y - 14, $w, 14);
                $x += $w;
            }
            foreach ($periodColumns as $column) {
                $w = 40;
                $content .= $rect($x, $y - 14, $w, 14, true);
                $content .= $rect($x, $y - 14, $w, 14);
                $content .= $text($x + 14, $y - 9, (string) $column['week'], 7, 'F2');
                $x += $w;
            }
            $content .= "0 0 0 RG 0 0 0 rg\n";
            $y -= 14;
            $content .= $line($margin, $y, $margin + $tableWidth, $y);
        };

        $y = $height - 30;
        $drawHeader($y);

        foreach ($planRows as $index => $planRow) {
            $needed = 16;
            if ($y < 110) {
                $newPage();
                $y = $height - 30;
                $drawHeader($y);
            }

            $content .= $rect($margin, $y - 16, $tableWidth, 16);
            $content .= $text($margin + 4, $y - 10, (string) ($planRow['urut'] ?? ($index + 1)), 7, 'F2');
            $content .= $text($margin + 36, $y - 10, $this->pdfTrim((string) ($planRow['nama_tahap_pekerjaan'] ?? '-'), 32), 7, 'F2');
            $content .= $text($margin + 216, $y - 10, number_format((float) ($planRow['target_progress'] ?? 0), 2, ',', '.'), 7, 'F2');
            $content .= $text($margin + 272, $y - 10, number_format((float) $schedule->realisasi_progress, 2, ',', '.'), 7, 'F2');

            $x = $margin + array_sum(array_slice($columns, 0, 4));
            foreach ($periodColumns as $column) {
                $planned = collect($planRow['allocations'] ?? [])->firstWhere('periode_ke', $column['periode']);
                $content .= $text($x + 12, $y - 10, $planned ? number_format((float) ($planned['bobot_persen'] ?? 0), 2, ',', '.') : '', 7, 'F2');
                $content .= $rect($x, $y - 16, 40, 16);
                $x += 40;
            }

            $y -= $needed;
        }

        $content .= "0.99 0.89 0.28 rg\n";
        $content .= $rect($margin, $y - 18, $tableWidth, 18, true);
        $content .= "0 0 0 RG 0 0 0 rg\n";
        $content .= $text($margin + 4, $y - 12, 'TOTAL', 8, 'F2');
        $content .= $text($margin + 216, $y - 12, number_format((float) ($schedule->target_progress ?? 0), 2, ',', '.'), 8, 'F2');
        $content .= $text($margin + 272, $y - 12, number_format((float) $schedule->realisasi_progress, 2, ',', '.'), 8, 'F2');

        $x = $margin + array_sum(array_slice($columns, 0, 4));
        foreach ($periodColumns as $column) {
            $content .= $text($x + 12, $y - 12, number_format((float) ($weeklyTotals[$column['periode'] - 1] ?? 0), 2, ',', '.'), 8, 'F2');
            $content .= $rect($x, $y - 18, 40, 18);
            $x += 40;
        }

        $pages[] = $content;

        return $this->assemblePdf($pages, $width, $height);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'nama_pekerjaan' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_target' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'target_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'realisasi_progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:direncanakan,berjalan,terlambat,selesai,tertahan'],
            'kendala' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);
    }

    private function schedulePayload(array $validated): array
    {
        $realisasi = $this->approvedProgressForSchedule(
            $validated['detail_rumah_id'] ?? null,
            $validated['tahapan_pembangunan_id'] ?? null,
        );
        $target = (float) ($validated['target_progress'] ?? 100);

        return [
            ...$validated,
            'nama_pekerjaan' => $this->stripRomanPrefix($validated['nama_pekerjaan'] ?? ''),
            'detail_rumah_id' => filled($validated['detail_rumah_id'] ?? null) ? $validated['detail_rumah_id'] : null,
            'tahapan_pembangunan_id' => filled($validated['tahapan_pembangunan_id'] ?? null) ? $validated['tahapan_pembangunan_id'] : null,
            'realisasi_progress' => $realisasi,
            'status' => $this->scheduleStatus($validated, $realisasi, $target),
        ];
    }

    private function spkKontraktorOptions(Request $request): array
    {
        $query = SpkKontraktor::query()
            ->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'items'])
            ->where('status', '!=', 'batal')
            ->whereNotNull('approved_at')
            ->whereDoesntHave('siteSchedules')
            ->orderByDesc('id');

        if ($this->shouldScopeToActivePerumahan($request)) {
            $activePerumahanId = $this->activePerumahanId($request);
            $query->where('perumahan_id', $activePerumahanId);
        }

        return $query
            ->get()
            ->map(function (SpkKontraktor $spk) {
                $total = max(1, (float) $spk->items->sum('total'));
                $groups = $spk->items
                    ->sortBy('urutan')
                    ->groupBy(fn ($item) => trim((string) $item->nama_tahap_pekerjaan) ?: 'Tahap')
                    ->map(function ($items, string $judulTahapan) use ($total) {
                        $groupTotal = (float) $items->sum('total');

                        return [
                            'judul_tahapan' => $judulTahapan,
                            'group_total' => $groupTotal,
                            'group_percent' => round(($groupTotal / $total) * 100, 2),
                            'items' => $items->map(fn ($item) => [
                                'nama_pekerjaan' => $item->nama_pekerjaan,
                                'harga_satuan' => $item->harga_satuan,
                            ])->values(),
                        ];
                    })
                    ->values();

                return [
                    'value' => (string) $spk->id,
                    'label' => $spk->nomor_spk.' - '.$spk->judul_pekerjaan.' | '.($spk->perumahan?->nama_perusahaan ?? '-').' / '.($spk->detailRumah ? trim($spk->detailRumah->kode_nlok.' '.$spk->detailRumah->nomor_rumah) : 'Kawasan'),
                    'perumahan_id' => (string) ($spk->perumahan_id ?? ''),
                    'detail_rumah_id' => (string) ($spk->detail_rumah_id ?? ''),
                    'perumahan_label' => $spk->perumahan?->nama_perusahaan ?? '-',
                    'unit_label' => $spk->detailRumah ? trim($spk->detailRumah->kode_nlok.' '.$spk->detailRumah->nomor_rumah) : 'Kawasan',
                    'status' => $spk->status,
                    'approved_at' => optional($spk->approved_at)->format('Y-m-d H:i'),
                    'has_schedule' => $spk->siteSchedules()->exists(),
                    'total_nilai' => (float) $spk->nilai_kontrak,
                    'group_count' => $groups->count(),
                    'item_count' => $spk->items->count(),
                    'groups' => $groups,
                ];
            })
            ->values()
            ->all();
    }

    private function formOptions(Request $request): array
    {
        return [
            ...$this->fieldOptions(),
            'spkKontraktors' => $this->spkKontraktorOptions($request),
            'tahapanPembangunansUnit' => $this->tahapanOptionsFor('unit'),
            'tahapanPembangunansKawasan' => $this->tahapanOptionsFor('kawasan'),
        ];
    }

    private function tahapanOptionsFor(string $context): array
    {
        return TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', $context)
            ->orderBy('urutan')
            ->get(['id', 'nama_tahapan', 'bobot_persen', 'perumahan_id', 'detail_rumah_id'])
            ->map(fn (TahapanPembangunan $row) => [
                'value' => (string) $row->id,
                'label' => $this->stripRomanPrefix($row->nama_tahapan).' ('.$row->bobot_persen.'%)',
                'nama_tahapan' => $this->stripRomanPrefix($row->nama_tahapan),
                'bobot_persen' => $row->bobot_persen,
                'perumahan_id' => (string) ($row->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
            ])
            ->values()
            ->all();
    }

    private function ensureTahapanContext(array $validated): void
    {
        if (blank($validated['tahapan_pembangunan_id'] ?? null)) {
            return;
        }

        $context = filled($validated['detail_rumah_id'] ?? null) ? 'unit' : 'kawasan';
        $tahapan = TahapanPembangunan::query()->findOrFail($validated['tahapan_pembangunan_id']);

        abort_unless(
            $tahapan->konteks === $context
                && (int) $tahapan->perumahan_id === (int) $validated['perumahan_id']
                && (
                    $context === 'kawasan'
                        ? $tahapan->detail_rumah_id === null
                        : (int) $tahapan->detail_rumah_id === (int) $validated['detail_rumah_id']
                ),
            422,
            'Tahapan harus berasal dari HPP perumahan atau unit yang dipilih.'
        );
    }

    private function resolveScheduleStage(int|string $sourceStageId, int|string $perumahanId, int|string|null $detailRumahId): TahapanPembangunan
    {
        $sourceStage = TahapanPembangunan::query()->findOrFail($sourceStageId);
        $context = filled($detailRumahId) ? 'unit' : 'kawasan';

        if (
            $sourceStage->konteks === $context
            && (int) $sourceStage->perumahan_id === (int) $perumahanId
            && (
                $context === 'kawasan'
                    ? $sourceStage->detail_rumah_id === null
                    : (int) $sourceStage->detail_rumah_id === (int) $detailRumahId
            )
        ) {
            return $sourceStage;
        }

        $matchingStage = TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', $context)
            ->where('perumahan_id', $perumahanId)
            ->where('nama_tahapan', $sourceStage->nama_tahapan)
            ->when(
                filled($detailRumahId),
                fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId),
                fn (Builder $query) => $query->whereNull('detail_rumah_id'),
            )
            ->first();

        if ($matchingStage) {
            return $matchingStage;
        }

        return TahapanPembangunan::query()->create([
            'perumahan_id' => $perumahanId,
            'detail_rumah_id' => filled($detailRumahId) ? $detailRumahId : null,
            'konteks' => $context,
            'nama_tahapan' => $sourceStage->nama_tahapan,
            'bobot_persen' => $sourceStage->bobot_persen,
            'urutan' => $sourceStage->urutan,
            'status' => 'aktif',
        ]);
    }

    private function resolveScheduleStageByName(string $namaTahapan, int|string $perumahanId, int|string|null $detailRumahId): TahapanPembangunan
    {
        $namaTahapan = $this->stripRomanPrefix($namaTahapan);
        $context = filled($detailRumahId) ? 'unit' : 'kawasan';

        $matchingStage = TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', $context)
            ->where('perumahan_id', $perumahanId)
            ->where('nama_tahapan', $namaTahapan)
            ->when(
                filled($detailRumahId),
                fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId),
                fn (Builder $query) => $query->whereNull('detail_rumah_id'),
            )
            ->first();

        if ($matchingStage) {
            return $matchingStage;
        }

        $maxUrutan = (int) TahapanPembangunan::query()
            ->where('perumahan_id', $perumahanId)
            ->when(
                filled($detailRumahId),
                fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId),
                fn (Builder $query) => $query->whereNull('detail_rumah_id'),
            )
            ->max('urutan');

        return TahapanPembangunan::query()->create([
            'perumahan_id' => $perumahanId,
            'detail_rumah_id' => filled($detailRumahId) ? $detailRumahId : null,
            'konteks' => $context,
            'nama_tahapan' => $namaTahapan,
            'bobot_persen' => 0,
            'urutan' => $maxUrutan + 1,
            'status' => 'aktif',
        ]);
    }

    private function stripRomanPrefix(?string $value): string
    {
        return trim((string) preg_replace('/^\s*[IVXLCDM]+\s*[\.\-]?\s+/i', '', (string) $value));
    }

    private function approvedProgressForSchedule(mixed $detailRumahId, mixed $tahapanPembangunanId): float
    {
        if (blank($detailRumahId) || blank($tahapanPembangunanId)) {
            return 0;
        }

        return min(100, (float) ProgressPembangunan::query()
            ->where('detail_rumah_id', $detailRumahId)
            ->where('tahapan_pembangunan_id', $tahapanPembangunanId)
            ->where('approval_status', 'approved')
            ->sum('persentase_total'));
    }

    private function scheduleStatus(array $payload, float $realisasi, float $target): string
    {
        if ($realisasi >= $target) {
            return 'selesai';
        }

        if (($payload['status'] ?? null) === 'tertahan') {
            return 'tertahan';
        }

        if (! empty($payload['tanggal_target']) && Carbon::parse($payload['tanggal_target'])->isPast()) {
            return 'terlambat';
        }

        return $realisasi > 0 ? 'berjalan' : ($payload['status'] ?? 'direncanakan');
    }
}
